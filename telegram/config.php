<?php
require_once __DIR__ . '/../conexion.php';

function obtenerConfig($clave) {
    global $conn;
    $stmt = $conn->prepare("SELECT valor FROM configuracion WHERE clave = ?");
    $stmt->bind_param("s", $clave);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila['valor'] ?? '';
}

// Token y URL leídos desde la base de datos
define('TELEGRAM_TOKEN', obtenerConfig('telegram_token'));
define('TELEGRAM_WEBHOOK_URL', obtenerConfig('telegram_webhook_url'));