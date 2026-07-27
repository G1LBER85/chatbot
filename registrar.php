<?php

require '../conexion.php';

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Mexico_City');

try {

    // Recibir JSON enviado desde cliente.html
    $datos = json_decode(file_get_contents('php://input'), true);

    $codigo = trim($datos['codigo'] ?? '');

    if ($codigo === '') {
        http_response_code(400);

        echo json_encode([
            'ok' => false,
            'error' => 'Código QR vacío'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 1. Buscar alumno mediante el código QR
    $stmtAlumno = $conn->prepare("
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
    $hoy = date('Y-m-d');

    // 2. Consultar el último movimiento del alumno durante el día
    $stmtUltimo = $conn->prepare("
        SELECT tipo
        FROM registros
        WHERE alumno_id = ?
          AND DATE(fecha_hora) = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmtUltimo->bind_param('is', $alumnoId, $hoy);
    $stmtUltimo->execute();

    $ultimoRegistro = $stmtUltimo->get_result()->fetch_assoc();
    $stmtUltimo->close();

    // Si no tiene registro hoy o el último fue salida, registra entrada
    $tipo = (!$ultimoRegistro || $ultimoRegistro['tipo'] === 'salida')
        ? 'entrada'
        : 'salida';

    // 3. Evitar duplicados muy rápidos
    $stmtDuplicado = $conn->prepare("
        SELECT id
        FROM registros
        WHERE alumno_id = ?
          AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 3 SECOND)
        LIMIT 1
    ");

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

    // 4. Insertar el movimiento
    $stmtRegistro = $conn->prepare("
        INSERT INTO registros (
            alumno_id,
            tipo,
            fecha_hora,
            mostrado
        )
        VALUES (?, ?, NOW(), 0)
    ");

    $stmtRegistro->bind_param('is', $alumnoId, $tipo);
    $stmtRegistro->execute();

    $registroId = $stmtRegistro->insert_id;
    $stmtRegistro->close();

    // 5. Preparar notificación para n8n
    $hora = date('h:i A');

    $datosWebhook = json_encode([
        'nombre' => $alumno['nombre'],
        'tipo' => $tipo,
        'hora' => $hora,
        'tutor' => $alumno['tutor_chat_id'],
        'codigo' => $codigo
    ], JSON_UNESCAPED_UNICODE);

    $opciones = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => $datosWebhook,
            'timeout' => 3,
            'ignore_errors' => true
        ]
    ];

    $contexto = stream_context_create($opciones);

    // La falla de n8n no debe impedir el registro
    @file_get_contents(
        'http://localhost:5678/webhook/registro-alumno',
        false,
        $contexto
    );

    echo json_encode([
        'ok' => true,
        'registro_id' => $registroId,
        'codigo' => $codigo,
        'nombre' => $alumno['nombre'],
        'grado' => $alumno['grado'],
        'tipo' => $tipo,
        'hora' => $hora
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>