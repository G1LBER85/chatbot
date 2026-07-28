<?php

require 'conexion.php';

header('Content-Type: text/plain; charset=utf-8');

$token = '8922581761:AAH_SEeEAOjedu18YI2BiWwcJghXv7i3vJE';

try {

    // Recibir actualización enviada por Telegram
    $update = json_decode(
        file_get_contents('php://input'),
        true
    );

    // Ignorar actualizaciones que no sean mensajes de texto
    if (
        !is_array($update) ||
        !isset($update['message']['chat']['id']) ||
        !isset($update['message']['text'])
    ) {
        http_response_code(200);
        echo 'ok';
        exit;
    }

    $chat_id = (string)$update['message']['chat']['id'];
    $texto = trim($update['message']['text']);
    $nombreTutor =
        $update['message']['chat']['first_name']
        ?? 'Tutor';

    // Solo atender el comando /start
    if (strpos($texto, '/start') !== 0) {
        http_response_code(200);
        echo 'ok';
        exit;
    }

    /*
     * Divide:
     * /start ALU002
     *
     * en:
     * [0] = /start
     * [1] = ALU002
     */
    $partes = preg_split('/\s+/', $texto);
    $codigo = strtoupper(trim($partes[1] ?? ''));

    if ($codigo === '') {

        $mensaje =
            "👋 ¡Hola {$nombreTutor}! Bienvenido a ChecaBot.\n\n" .
            "Para recibir notificaciones escribe:\n" .
            "/start CODIGO\n\n" .
            "Ejemplo:\n" .
            "/start ALU001\n\n" .
            "📞 Solicita tu código a la escuela.";

    } else {

        // Buscar al alumno
        $stmt = $conn->prepare("
            SELECT
                id,
                nombre,
                grado,
                codigo
            FROM alumnos
            WHERE codigo = ?
              AND activo = 1
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Error preparando la consulta: ' . $conn->error
            );
        }

        $stmt->bind_param('s', $codigo);
        $stmt->execute();

        $alumno = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$alumno) {

            $mensaje =
                "❌ Código no encontrado.\n\n" .
                "Verifica que esté escrito correctamente.\n\n" .
                "Ejemplo:\n" .
                "/start ALU002";

        } else {

            // Guardar el chat_id del tutor
            $stmt2 = $conn->prepare("
                UPDATE alumnos
                SET tutor_chat_id = ?
                WHERE codigo = ?
            ");

            if (!$stmt2) {
                throw new Exception(
                    'Error preparando la actualización: '
                    . $conn->error
                );
            }

            /*
             * Se usan dos strings para evitar problemas
             * con identificadores grandes de Telegram.
             */
            $stmt2->bind_param(
                'ss',
                $chat_id,
                $codigo
            );

            $stmt2->execute();

            if ($stmt2->affected_rows < 0) {
                throw new Exception(
                    'No se pudo guardar el chat de Telegram'
                );
            }

            $stmt2->close();

            $mensaje =
                "✅ ¡Listo, {$nombreTutor}!\n\n" .
                "Ahora recibirás notificaciones de:\n" .
                "👤 *{$alumno['nombre']}*\n" .
                "🎓 Grado: {$alumno['grado']}\n" .
                "🔑 Código: {$alumno['codigo']}\n\n" .
                "Cada vez que registre entrada o salida te avisaré. 🏫";
        }
    }

    // Responder al chat de Telegram
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $mensaje,
        'parse_mode' => 'Markdown'
    ];

    $payload = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
    );

    $opciones = [
        'http' => [
            'header' =>
                "Content-Type: application/json; charset=UTF-8\r\n",
            'method' => 'POST',
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];

    $contexto = stream_context_create($opciones);

    $respuestaTelegram = file_get_contents(
        $url,
        false,
        $contexto
    );

    if ($respuestaTelegram === false) {
        throw new Exception(
            'No se pudo conectar con Telegram'
        );
    }

    $resultadoTelegram = json_decode(
        $respuestaTelegram,
        true
    );

    if (
        !is_array($resultadoTelegram) ||
        !($resultadoTelegram['ok'] ?? false)
    ) {
        throw new Exception(
            $resultadoTelegram['description']
            ?? 'Telegram no pudo enviar la respuesta'
        );
    }

    http_response_code(200);
    echo 'ok';

} catch (Throwable $error) {

    /*
     * Guardar el error para revisarlo sin mostrarlo a Telegram.
     */
    file_put_contents(
        __DIR__ . '/telegram_error.log',
        date('Y-m-d H:i:s') .
        ' - ' .
        $error->getMessage() .
        PHP_EOL,
        FILE_APPEND
    );

    http_response_code(200);
    echo 'ok';
}