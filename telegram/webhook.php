<?php
$t0 = microtime(true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/redis.php';

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    echo 'ok';
    exit;
}

// Responder de inmediato a Telegram para que el webhook no expire
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'ok';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ========== MENSAJES DE TEXTO ==========
if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $texto   = trim($update['message']['text']);
    $nombre  = $update['message']['chat']['first_name'] ?? 'tutor';

    if (strtoupper($texto) === '/REGISTRO') {
        encolarMensaje($chat_id, obtenerTextoRegistro());
    } elseif (strpos($texto, '/start') === 0) {
        procesarRegistroCurp($chat_id, $texto, $nombre, $conn);
    } else {
        encolarMenu($chat_id, $conn);
    }
}

// ========== CALLBACK QUERY (BOTONES) ==========
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $query_id = $callback['id'];
    $chat_id  = $callback['message']['chat']['id'] ?? $callback['from']['id'];
    $data_btn = $callback['data'] ?? '';

    // 1) Responder PRIMERO al callback (así el ID no caduca)
    responderCallback($query_id, '✅ Enviado');

    // 2) Luego procesar y encolar el contenido
    $stmt = $conn->prepare("SELECT CURP FROM alumnos WHERE tutor_chat_id = ?");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $alumno = $stmt->get_result()->fetch_assoc();

    if (!$alumno) {
        encolarMensaje($chat_id, "⚠️ *Aún no estás registrado*\n\n" . obtenerTextoRegistro());

    } elseif (!preg_match('/^opcion_(\d+)$/', $data_btn, $coincidencia)) {
        encolarMenu($chat_id, $conn);

    } else {
        $numero_opcion = (int) $coincidencia[1];
        $respuesta     = obtenerRespuesta($numero_opcion, $conn);

        if ($respuesta && !empty($respuesta['titulo']) && (int) $respuesta['activo'] === 1) {
            encolarMensaje(
                $chat_id,
                "📌 *" . escaparMd($respuesta['titulo']) . "*\n\n" . escaparMd($respuesta['respuesta_texto'])
            );

            if (!empty($respuesta['ruta_imagen'])) {
                encolarFoto($chat_id, URL_BASE . $respuesta['ruta_imagen'], "📸");
            }
        } else {
            encolarMensaje($chat_id, "⚠️ Esa opción ya no está disponible. Aquí tienes el menú actualizado:");
            encolarMenu($chat_id, $conn);
        }
    }
}

// ========== FUNCIONES AUXILIARES ==========

function escaparMd($texto) {
    return str_replace(
        ['_', '*', '`', '['],
        ['\_', '\*', '\`', '\['],
        (string) $texto
    );
}

function encolarMensaje($chat_id, $texto) {
    global $redis;
    $redis->agregarACola([
        'tipo'    => 'mensaje',
        'chat_id' => $chat_id,
        'texto'   => $texto
    ]);
}

function encolarFoto($chat_id, $url_imagen, $caption) {
    global $redis;
    $redis->agregarACola([
        'tipo'       => 'foto',
        'chat_id'    => $chat_id,
        'url_imagen' => $url_imagen,
        'caption'    => $caption
    ]);
}

function encolarMenu($chat_id, $conn) {
    global $redis;
    $redis->agregarACola([
        'tipo'    => 'menu',
        'chat_id' => $chat_id,
        'texto'   => "👋 ¿Qué necesitas?\n\nSelecciona una opción:",
        'botones' => obtenerBotonesMenu($conn)
    ]);
}

function obtenerBotonesMenu($conn) {
    $botones = [];
    $resultado = $conn->query(
        "SELECT numero, titulo FROM telegram_respuestas
         WHERE activo = 1 AND titulo != '' ORDER BY numero ASC"
    );
    while ($fila = $resultado->fetch_assoc()) {
        $botones[] = [[
            "text"          => $fila['titulo'],
            "callback_data" => "opcion_" . $fila['numero']
        ]];
    }
    return $botones;
}

function obtenerRespuesta($numero, $conn) {
    global $redis;

    $respuesta = $redis->getRespuesta($numero);
    if ($respuesta) {
        return $respuesta;
    }

    $stmt = $conn->prepare("SELECT * FROM telegram_respuestas WHERE numero = ?");
    $stmt->bind_param("i", $numero);
    $stmt->execute();
    $respuesta = $stmt->get_result()->fetch_assoc();

    if ($respuesta) {
        $redis->setRespuesta($numero, $respuesta);
    }
    return $respuesta;
}

function responderCallback($query_id, $texto) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/answerCallbackQuery";
    $data = [
        "callback_query_id" => $query_id,
        "text"              => $texto,
        "show_alert"        => false
    ];
    $contexto = stream_context_create([
        "http" => [
            "header"        => "Content-Type: application/json\r\n",
            "method"        => "POST",
            "content"       => json_encode($data),
            "ignore_errors" => true,
            "timeout"       => 5
        ]
    ]);
    $resp = @file_get_contents($url, false, $contexto);
    if ($resp === false || empty(json_decode($resp, true)['ok'])) {
        error_log("ERROR answerCallbackQuery: " . var_export($resp, true));
    }
}

function obtenerTextoRegistro() {
    return "📝 *Registro de Tutor*\n\n"
         . "Para registrarte y recibir notificaciones, envía:\n\n"
         . "*/start CURP*\n\n"
         . "Ejemplo:\n"
         . "`/start JPXM900115HDFXXX00`\n\n"
         . "⚠️ Usa el CURP del alumno (18 caracteres)\n"
         . "📞 Solicítalo a la escuela si no lo tienes.";
}

function procesarRegistroCurp($chat_id, $texto, $nombre, $conn) {
    global $redis;

    $partes = preg_split('/\s+/', $texto);

    if (!isset($partes[1]) || $partes[1] === '') {
        encolarMensaje($chat_id, obtenerTextoRegistro());
        return;
    }

    $curp = strtoupper(trim($partes[1]));

    $stmt = $conn->prepare("SELECT * FROM alumnos WHERE CURP = ?");
    $stmt->bind_param("s", $curp);
    $stmt->execute();
    $alumno = $stmt->get_result()->fetch_assoc();

    if (!$alumno) {
        encolarMensaje(
            $chat_id,
            "❌ *CURP no encontrado*\n\nVerifica el CURP e intenta de nuevo.\n\nEjemplo:\n`/start JPXM900115HDFXXX00`"
        );
        return;
    }

    $stmt2 = $conn->prepare("UPDATE alumnos SET tutor_chat_id = ? WHERE CURP = ?");
    $stmt2->bind_param("is", $chat_id, $curp);
    $stmt2->execute();

    $redis->setChatId($curp, $chat_id);

    encolarMensaje(
        $chat_id,
        "✅ *¡Listo, " . escaparMd($nombre) . "!*\n\n"
        . "Ahora recibirás notificaciones de:\n"
        . "*" . escaparMd($alumno['nombre']) . "*\n"
        . "🎓 Grado: " . escaparMd($alumno['grado'] . $alumno['grupo'])
    );

    encolarMenu($chat_id, $conn);
}