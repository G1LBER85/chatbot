<?php

require '../conexion.php';

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Mexico_City');

try {

    // Recibir el JSON enviado desde cliente.html o cliente.php
    $contenido = file_get_contents('php://input');
    $datos = json_decode($contenido, true);

    if (!is_array($datos)) {
        http_response_code(400);

        echo json_encode([
            'ok' => false,
            'error' => 'La solicitud no contiene un JSON válido'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $codigo = trim($datos['CURP'] ?? '');   //Cambié de código a CURP

    if ($codigo === '') {
        http_response_code(400);

        echo json_encode([
            'ok' => false,
            'error' => 'Código QR vacío'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // ─────────────────────────────────────────────
    // 1. Buscar alumno activo por su código QR
    // Cambie de Where codigo a WHERE CURP
    $stmtAlumno = $conn->prepare("
        SELECT
            id,
            nombre,
            grado,
            tutor_chat_id
        FROM alumnos
        WHERE CURP = ?
          AND activo = 1
        LIMIT 1
    ");

    if (!$stmtAlumno) {
        throw new Exception(
            'Error preparando la búsqueda del alumno: ' . $conn->error
        );
    }

    $stmtAlumno->bind_param('s', $codigo);
    $stmtAlumno->execute();

    $alumno = $stmtAlumno->get_result()->fetch_assoc();

    $stmtAlumno->close();

    if (!$alumno) {
        http_response_code(404);

        echo json_encode([
            'ok' => false,
            'error' => 'Alumno no encontrado',
            'codigo' => $codigo
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $alumnoId = (int)$alumno['id'];

    // ─────────────────────────────────────────────
    // 2. Evitar registros duplicados rápidos
    // ─────────────────────────────────────────────
    $stmtDuplicado = $conn->prepare("
        SELECT id
        FROM registros
        WHERE alumno_id = ?
          AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 3 SECOND)
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmtDuplicado) {
        throw new Exception(
            'Error preparando la validación de duplicados: ' . $conn->error
        );
    }

    $stmtDuplicado->bind_param('i', $alumnoId);
    $stmtDuplicado->execute();

    $duplicado = $stmtDuplicado->get_result()->fetch_assoc();

    $stmtDuplicado->close();

    if ($duplicado) {
        http_response_code(429);

        echo json_encode([
            'ok' => false,
            'error' => 'El código fue leído nuevamente demasiado rápido'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // ─────────────────────────────────────────────
    // 3. Determinar si corresponde entrada o salida
    // ─────────────────────────────────────────────
    $hoy = date('Y-m-d');

    $stmtUltimo = $conn->prepare("
        SELECT tipo
        FROM registros
        WHERE alumno_id = ?
          AND DATE(fecha_hora) = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmtUltimo) {
        throw new Exception(
            'Error preparando la consulta del último movimiento: '
            . $conn->error
        );
    }

    $stmtUltimo->bind_param('is', $alumnoId, $hoy);
    $stmtUltimo->execute();

    $ultimoRegistro = $stmtUltimo->get_result()->fetch_assoc();

    $stmtUltimo->close();

    /*
     * Si no hay registros hoy, será entrada.
     * Si el último fue salida, será entrada.
     * Si el último fue entrada, será salida.
     */
    $tipo = (
        !$ultimoRegistro ||
        $ultimoRegistro['tipo'] === 'salida'
    ) ? 'entrada' : 'salida';

    // ─────────────────────────────────────────────
    // 4. Guardar el registro
    // ─────────────────────────────────────────────
    $stmtRegistro = $conn->prepare("
        INSERT INTO registros (
            alumno_id,
            tipo,
            fecha_hora,
            mostrado
        )
        VALUES (?, ?, NOW(), 0)
    ");

    if (!$stmtRegistro) {
        throw new Exception(
            'Error preparando el registro: ' . $conn->error
        );
    }

    $stmtRegistro->bind_param('is', $alumnoId, $tipo);
    $stmtRegistro->execute();

    if ($stmtRegistro->affected_rows !== 1) {
        throw new Exception('No se pudo guardar el registro');
    }

    $registroId = $stmtRegistro->insert_id;

    $stmtRegistro->close();

    // ─────────────────────────────────────────────
    // 5. Preparar datos del registro
    // ─────────────────────────────────────────────
    $hora = date('h:i A');

    /*
     * La notificación no debe impedir que se registre la asistencia.
     * Deja TU_TOKEN mientras no quieras usar Telegram.
     */
    $telegramEnviado = false;
    $token = 'TU_TOKEN';

    if (
        $token !== 'TU_TOKEN' &&
        !empty($alumno['tutor_chat_id'])
    ) {
        $mensaje = $tipo === 'entrada'
            ? "✅ *{$alumno['nombre']}* registró su *entrada* a las {$hora} 🏫"
            : "🚪 *{$alumno['nombre']}* registró su *salida* a las {$hora}";

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $payload = json_encode([
            'chat_id' => $alumno['tutor_chat_id'],
            'text' => $mensaje,
            'parse_mode' => 'Markdown'
        ], JSON_UNESCAPED_UNICODE);

        $contexto = stream_context_create([
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

        $respuestaTelegram = @file_get_contents(
            $url,
            false,
            $contexto
        );

        $telegramEnviado = $respuestaTelegram !== false;
    }

    // ─────────────────────────────────────────────
    // 6. Responder al JavaScript
    // ─────────────────────────────────────────────
    echo json_encode([
        'ok' => true,
        'registro_id' => $registroId,
        'codigo' => $codigo,
        'nombre' => $alumno['nombre'],
        'grado' => $alumno['grado'],
        'tipo' => $tipo,
        'hora' => $hora,
        'telegram_enviado' => $telegramEnviado
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>