<?php


require '../conexion.php';

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Mexico_City');

try {

    // Recibir el JSON enviado desde cliente.html
    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos)) {
        http_response_code(400);

        echo json_encode([
            'ok' => false,
            'error' => 'La solicitud no contiene un JSON válido'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $codigo = trim($datos['codigo'] ?? '');

    if ($codigo === '') {
        http_response_code(400);

        echo json_encode([
            'ok' => false,
            'error' => 'Código QR vacío'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // ── 1. Buscar alumno por código QR ──
    $stmt = $conn->prepare("
        SELECT
            id,
            nombre,
            grado,
            tutor_chat_id
        FROM alumnos
        WHERE codigo = ?
          AND activo = 1
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Error preparando búsqueda del alumno: ' . $conn->error
        );
    }

    $stmt->bind_param('s', $codigo);
    $stmt->execute();

    $alumno = $stmt->get_result()->fetch_assoc();

    $stmt->close();

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
    $hoy = date('Y-m-d');

    // ── 2. Evitar registros duplicados muy rápidos ──
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
            'Error preparando validación de duplicados: ' . $conn->error
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

    // ── 3. Determinar entrada o salida ──
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
            'Error preparando consulta del último movimiento: ' . $conn->error
        );
    }

    $stmtUltimo->bind_param('is', $alumnoId, $hoy);
    $stmtUltimo->execute();

    $ultimo = $stmtUltimo->get_result()->fetch_assoc();

    $stmtUltimo->close();

    $tipo = (!$ultimo || $ultimo['tipo'] === 'salida')
        ? 'entrada'
        : 'salida';

    // ── 4. Insertar registro ──
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

    $registroId = $stmtRegistro->insert_id;

    $stmtRegistro->close();

    // ── 5. Enviar Telegram ──
    $telegramEnviado = false;
    $hora = date('h:i A');

    if (!empty($alumno['tutor_chat_id'])) {

        $mensaje = ($tipo === 'entrada')
            ? "✅ *{$alumno['nombre']}* acaba de registrar su *entrada* a las {$hora} 🏫"
            : "🚪 *{$alumno['nombre']}* acaba de registrar su *salida* a las {$hora}";

        /*
         * Coloca aquí tu token real.
         * No publiques el token en GitHub.
         */
        $token = 'TU_TOKEN';

        if ($token !== 'TU_TOKEN') {

            $url = "https://api.telegram.org/bot{$token}/sendMessage";

            $payload = json_encode([
                'chat_id' => $alumno['tutor_chat_id'],
                'text' => $mensaje,
                'parse_mode' => 'Markdown'
            ], JSON_UNESCAPED_UNICODE);

            $context = stream_context_create([
                'http' => [
                    'header' => "Content-Type: application/json\r\n",
                    'method' => 'POST',
                    'content' => $payload,
                    'timeout' => 5,
                    'ignore_errors' => true
                ]
            ]);

            $resultadoTelegram = @file_get_contents(
                $url,
                false,
                $context
            );

            $telegramEnviado = $resultadoTelegram !== false;
        }
    }

    // ── 6. Respuesta exitosa ──
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
>>>>>>> b2dfea4 (lector QR)
