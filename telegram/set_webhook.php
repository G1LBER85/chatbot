<?php
/**
 * REGISTRAR WEBHOOK DE TELEGRAM
 * Ejecutar con: php set_webhook.php
 * Usa el token de config.php, así no hay que copiarlo a mano.
 */

require __DIR__ . '/config.php';

$api         = "https://api.telegram.org/bot" . TELEGRAM_TOKEN;
$url_webhook = TELEGRAM_WEBHOOK_URL;

function llamarTelegram($url, $data = []) {
    $contexto = stream_context_create([
        "http" => [
            "header"        => "Content-Type: application/json\r\n",
            "method"        => "POST",
            "content"       => json_encode($data),
            "ignore_errors" => true,
            "timeout"       => 20
        ]
    ]);
    $raw = @file_get_contents($url, false, $contexto);
    return $raw === false ? ["ok" => false, "description" => "Sin conexión con Telegram"] : json_decode($raw, true);
}

function mostrar($datos) {
    echo json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
}

echo "1) Registrando webhook en: {$url_webhook}\n";
mostrar(llamarTelegram("{$api}/setWebhook", [
    "url"             => $url_webhook,
    "allowed_updates" => ["message", "callback_query"]  // <- la clave para que funcionen los botones
]));

echo "2) Estado actual del webhook:\n";
mostrar(llamarTelegram("{$api}/getWebhookInfo"));