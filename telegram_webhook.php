<?php
require 'conexion.php';

$token = '8922581761:AAH_SEeEAOjedu18YI2BiWwcJghXv7i3vJE';

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) exit;

// CALLBACK QUERY - CUANDO TUTOR TOCA UN BOTÓN
if (isset($update['callback_query'])) {
    procesarCallbackQuery($update, $conn, $token);
}

// MENSAJE DE TEXTO
if (isset($update['message']['text'])) {
    $chat_id  = $update['message']['chat']['id'];
    $texto    = $update['message']['text'];
    $nombre   = $update['message']['chat']['first_name'];
    
    // COMANDO /registro
    if (strtoupper($texto) === '/REGISTRO') {
        mostrarFormularioRegistro($chat_id, $nombre, $conn, $token);
    }
    // COMANDO /start con CURP
    elseif (strpos($texto, '/start') === 0) {
        procesarRegistroCurp($chat_id, $texto, $nombre, $conn, $token);
    }
    // CUALQUIER OTRO MENSAJE
    else {
        mostrarMenuOpciones($chat_id, $conn, $token);
    }
}

// ========== FUNCIONES ==========

/**
 * Mostrar menú de opciones (botones)
 */
function mostrarMenuOpciones($chat_id, $conn, $token) {
    $mensaje = "👋 ¿Qué necesitas?\n\nSelecciona una opción:";
    
    $respuestas = $conn->query("SELECT numero, titulo FROM telegram_respuestas WHERE activo = 1 AND titulo != '' ORDER BY numero ASC");
    
    $botones = [];
    while ($resp = $respuestas->fetch_assoc()) {
        $botones[] = [
            [
                "text"          => $resp['titulo'],
                "callback_data" => "opcion_" . $resp['numero']
            ]
        ];
    }
    
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        "chat_id"      => $chat_id,
        "text"         => $mensaje,
        "parse_mode"   => "Markdown",
        "reply_markup" => [
            "inline_keyboard" => $botones
        ]
    ];
    
    $opciones = [
        "http" => [
            "header"  => "Content-Type: application/json\r\n",
            "method"  => "POST",
            "content" => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($opciones);
    file_get_contents($url, false, $context);
}

/**
 * Mostrar formulario de registro
 */
function mostrarFormularioRegistro($chat_id, $nombre, $conn, $token) {
    $mensaje = "📝 *Registro de Tutor*\n\n"
             . "Para registrarte y recibir notificaciones, envía:\n\n"
             . "**/start CURP**\n\n"
             . "Ejemplo:\n"
             . "`/start JPXM900115HDFXXX00`\n\n"
             . "⚠️ Usa el CURP del alumno (18 caracteres)\n"
             . "📞 Solicítalo a la escuela si no lo tienes.";
    
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        "chat_id"    => $chat_id,
        "text"       => $mensaje,
        "parse_mode" => "Markdown"
    ];
    
    $opciones = [
        "http" => [
            "header"  => "Content-Type: application/json\r\n",
            "method"  => "POST",
            "content" => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($opciones);
    file_get_contents($url, false, $context);
}

/**
 * Procesar /start CURP para registro
 */
function procesarRegistroCurp($chat_id, $texto, $nombre, $conn, $token) {
    $partes = explode(' ', $texto);
    
    if (isset($partes[1])) {
        $curp = strtoupper(trim($partes[1]));
        
        // BUSCAR ALUMNO POR CURP
        $stmt = $conn->prepare("SELECT * FROM alumnos WHERE CURP = ?");
        $stmt->bind_param("s", $curp);
        $stmt->execute();
        $alumno = $stmt->get_result()->fetch_assoc();
        
        if ($alumno) {
            // GUARDAR CHAT ID
            $stmt2 = $conn->prepare("UPDATE alumnos SET tutor_chat_id = ? WHERE CURP = ?");
            $stmt2->bind_param("is", $chat_id, $curp);
            $stmt2->execute();
            
            // CONFIRMACIÓN
            $mensaje = "✅ *¡Listo, {$nombre}!*\n\n"
                     . "Ahora recibirás notificaciones de:\n"
                     . "*{$alumno['nombre']}*\n"
                     . "🎓 Grado: {$alumno['grado']}{$alumno['grupo']}\n\n"
                     . "¿Qué necesitas?";
            
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $data = [
                "chat_id"    => $chat_id,
                "text"       => $mensaje,
                "parse_mode" => "Markdown"
            ];
            
            $opciones = [
                "http" => [
                    "header"  => "Content-Type: application/json\r\n",
                    "method"  => "POST",
                    "content" => json_encode($data)
                ]
            ];
            
            $context = stream_context_create($opciones);
            file_get_contents($url, false, $context);
            
            // MOSTRAR MENÚ
            mostrarMenuOpciones($chat_id, $conn, $token);
            
        } else {
            $mensaje = "❌ *CURP no encontrado*\n\n"
                     . "Verifica el CURP e intenta de nuevo.\n\n"
                     . "Ejemplo:\n"
                     . "`/start JPXM900115HDFXXX00`";
            
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $data = [
                "chat_id"    => $chat_id,
                "text"       => $mensaje,
                "parse_mode" => "Markdown"
            ];
            
            $opciones = [
                "http" => [
                    "header"  => "Content-Type: application/json\r\n",
                    "method"  => "POST",
                    "content" => json_encode($data)
                ]
            ];
            
            $context = stream_context_create($opciones);
            file_get_contents($url, false, $context);
        }
    } else {
        mostrarFormularioRegistro($chat_id, $nombre, $conn, $token);
    }
}

/**
 * Procesar cuando tutor toca un botón
 */
function procesarCallbackQuery($update, $conn, $token) {
    $callback = $update['callback_query'];
    $query_id = $callback['id'];
    $chat_id  = $callback['from']['id'];
    $data_btn = $callback['data'];
    
    // BUSCAR ALUMNO POR CHAT_ID
    $stmt = $conn->prepare("SELECT CURP FROM alumnos WHERE tutor_chat_id = ?");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $alumno = $stmt->get_result()->fetch_assoc();
    
    if ($alumno) {
        // OBTENER RESPUESTA PREDETERMINADA
        $numero_opcion = intval(substr($data_btn, -1));
        $stmt2 = $conn->prepare("SELECT * FROM telegram_respuestas WHERE numero = ?");
        $stmt2->bind_param("i", $numero_opcion);
        $stmt2->execute();
        $respuesta = $stmt2->get_result()->fetch_assoc();
        
        if ($respuesta && $respuesta['titulo']) {
            $mensaje_resp = "📌 *{$respuesta['titulo']}*\n\n{$respuesta['respuesta_texto']}";
            
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $data_resp = [
                "chat_id"    => $chat_id,
                "text"       => $mensaje_resp,
                "parse_mode" => "Markdown"
            ];
            
            $opciones = [
                "http" => [
                    "header"  => "Content-Type: application/json\r\n",
                    "method"  => "POST",
                    "content" => json_encode($data_resp)
                ]
            ];
            $context = stream_context_create($opciones);
            file_get_contents($url, false, $context);
            
            // ENVIAR IMAGEN PNG SI EXISTE
            if ($respuesta['ruta_imagen']) {
                $url_img = "https://api.telegram.org/bot{$token}/sendPhoto";
                $data_img = [
                    "chat_id" => $chat_id,
                    "photo"   => $respuesta['ruta_imagen'],
                    "caption" => "📸 Imagen adjunta"
                ];
                
                $opciones_img = [
                    "http" => [
                        "header"  => "Content-Type: application/json\r\n",
                        "method"  => "POST",
                        "content" => json_encode($data_img)
                    ]
                ];
                $context_img = stream_context_create($opciones_img);
                file_get_contents($url_img, false, $context_img);
            }
        }
    }
    
    // RESPUESTA AL CALLBACK
    $url_notify = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
    $data_notify = [
        "callback_query_id" => $query_id,
        "text"              => "✅ Enviado",
        "show_alert"        => false
    ];
    
    $opciones_notify = [
        "http" => [
            "header"  => "Content-Type: application/json\r\n",
            "method"  => "POST",
            "content" => json_encode($data_notify)
        ]
    ];
    $context_notify = stream_context_create($opciones_notify);
    file_get_contents($url_notify, false, $context_notify);
}

http_response_code(200);
echo "ok";
?>