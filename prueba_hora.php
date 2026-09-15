<?php

require 'conexion.php';

$resultado = $conn->query("
    SELECT
        NOW() AS hora_mysql,
        @@session.time_zone AS zona_sesion
");

$datos = $resultado->fetch_assoc();

echo 'Hora PHP: ' . date('Y-m-d H:i:s') . '<br>';
echo 'Hora MySQL: ' . $datos['hora_mysql'] . '<br>';
echo 'Zona MySQL: ' . $datos['zona_sesion'];