<?php
/**
 * WORKER DE MENSAJES
 * Procesa la cola de mensajes en background
 * Ejecutar con: php worker_mensajes.php
 * ⚠️ Si cambias este archivo, detén el worker (Ctrl+C) y vuélvelo a iniciar
 */

require 'config.php';
require 'conexion.php';
require 'redis_config.php';

logMsg("Worker iniciado. Esperando mensajes...");

// LOOP INFINITO
while (true) {
    $mensaje = $redis->obtenerDeCola();

    if (!$mensaje) {
        sleep(1); // no saturar el CPU
        continue;
    }

    $tipo    = $mensaje['tipo'] ?? '';
    $chat_id = $mensaje['chat_id'] ?? null;
    logMsg("Procesando '{$tipo}' para Chat ID: {$chat_id}");

    switch ($tipo) {
        case 'mensaje':
            $ok = enviarTexto($chat_id, $mensaje['texto']);
            break;

        case 'foto':
            $ok = enviarFoto($chat_id, $mensaje['url_imagen'], $mensaje['caption'] ?? '');
            break;

        case 'menu':
            $ok = enviarMenu($chat_id, $mensaje['texto'], $mensaje['botones'] ?? []);
            break;

        default:
            logMsg("⚠️ Tipo de mensaje desconocido: " . json_encode($mensaje));
            $ok = false;
    }

    logMsg(($ok ? "✅ Enviado" : "❌ Falló") . ". Cola pendiente: " . $redis->tamanoCola());
}


// ========== FUNCIONES ==========

function logMsg($texto) {
    echo "[" . date('H:i:s') . "] {$texto}\n";
}

/**
 * Llama a la API de Telegram y SIEMPRE muestra el error si algo falla
 */
function llamarTelegram($metodo, $data) {
    $url = "https://api.telegram.org/bot" . '8922581761:AAH_SEeEAOjedu18YI2BiWwcJghXv7i3vJE' . "/{$metodo}";

    $contexto = stream_context_create([
        "http" => [
            "header"        => "Content-Type: application/json\r\n",
            "method"        => "POST",
            "content"       => json_encode($data),
            "ignore_errors" => true, // devuelve el cuerpo aunque sea error 400
            "timeout"       => 15
        ]
    ]);

    $raw = @file_get_contents($url, false, $contexto);

    if ($raw === false) {
        logMsg("❌ Sin conexión con Telegram ({$metodo})");
        return null;
    }

    $resp = json_decode($raw, true);
    if (empty($resp['ok'])) {
        logMsg("❌ Error de Telegram ({$metodo}): " . ($resp['description'] ?? $raw));
    }
    return $resp;
}

/**
 * Envía texto con Markdown. Si Telegram rechaza el formato,
 * lo reintenta como texto plano para que el mensaje no se pierda.
 */
function enviarTexto($chat_id, $texto, $reply_markup = null) {
    $data = [
        "chat_id"    => $chat_id,
        "text"       => $texto,
        "parse_mode" => "Markdown"
    ];
    if ($reply_markup) {
        $data["reply_markup"] = $reply_markup;
    }

    $resp = llamarTelegram('sendMessage', $data);

    if (isset($resp['description']) && stripos($resp['description'], "can't parse entities") !== false) {
        logMsg("↩️ Reintentando sin formato Markdown");
        unset($data["parse_mode"]);
        $data["text"] = str_replace(['\_', '\*', '\`', '\['], ['_', '*', '`', '['], $texto);
        $resp = llamarTelegram('sendMessage', $data);
    }

    return !empty($resp['ok']);
}

function enviarFoto($chat_id, $url_imagen, $caption) {
    $resp = llamarTelegram('sendPhoto', [
        "chat_id" => $chat_id,
        "photo"   => $url_imagen,
        "caption" => $caption
    ]);
    return !empty($resp['ok']);
}

function enviarMenu($chat_id, $texto, $botones) {
    // Asegurarse de que los botones sean array, no string
    if (is_string($botones)) {
        $botones = json_decode($botones, true) ?: [];
    }

    logMsg("Enviando menú con " . count($botones) . " botones");

    if (empty($botones)) {
        logMsg("⚠️ No hay opciones activas, se envía solo el texto");
        return enviarTexto($chat_id, $texto);
    }

    return enviarTexto($chat_id, $texto, ["inline_keyboard" => $botones]);
}