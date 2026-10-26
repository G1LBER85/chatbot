<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/redis.php';

$token = TELEGRAM_TOKEN;

echo "[" . date('H:i:s') . "] Worker iniciado. Esperando mensajes...\n";

while (true) {
    $mensaje = $redis->obtenerDeCola();

    if (!$mensaje) {
        usleep(300000); // 0.3 s para no saturar CPU
        continue;
    }

    echo "[" . date('H:i:s') . "] Procesando '{$mensaje['tipo']}' para Chat ID: {$mensaje['chat_id']}\n";

    switch ($mensaje['tipo']) {
        case 'mensaje':
            $ok = telegramPost('sendMessage', [
                'chat_id'    => $mensaje['chat_id'],
                'text'       => $mensaje['texto'],
                'parse_mode' => 'Markdown'
            ]);
            break;

        case 'foto':
            $ok = telegramPost('sendPhoto', [
                'chat_id' => $mensaje['chat_id'],
                'photo'   => $mensaje['url_imagen'],
                'caption' => $mensaje['caption'] ?? ''
            ]);
            break;

        case 'menu':
            $ok = telegramPost('sendMessage', [
                'chat_id'      => $mensaje['chat_id'],
                'text'         => $mensaje['texto'],
                'parse_mode'   => 'Markdown',
                'reply_markup' => ['inline_keyboard' => $mensaje['botones'] ?? []]
            ]);
            break;

        default:
            $ok = false;
    }

    echo "[" . date('H:i:s') . "] " . ($ok ? "✅ Enviado" : "❌ Falló") . ". Cola pendiente: " . $redis->tamanoCola() . "\n";
}

function telegramPost($metodo, $data) {
    global $token;
    $url = "https://api.telegram.org/bot{$token}/{$metodo}";
    $contexto = stream_context_create([
        "http" => [
            "header"        => "Content-Type: application/json\r\n",
            "method"        => "POST",
            "content"       => json_encode($data, JSON_UNESCAPED_UNICODE),
            "ignore_errors" => true,
            "timeout"       => 10
        ]
    ]);
    $resp = @file_get_contents($url, false, $contexto);
    $json = json_decode($resp, true);
    if (empty($json['ok'])) {
        echo "[" . date('H:i:s') . "] Error de Telegram ({$metodo}): " . ($json['description'] ?? 'sin respuesta') . "\n";
        return false;
    }
    return true;
}