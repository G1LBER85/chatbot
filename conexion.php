<?php

date_default_timezone_set('America/Mexico_City');

$conn = new mysqli(
    'localhost',
    'root',
    '',
    'checabot'
);

if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

if (!$conn->query("SET time_zone = '-06:00'")) {
    die('Error configurando zona horaria: ' . $conn->error);
}
?>
