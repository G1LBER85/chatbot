<?php
$host     = "localhost";
$usuario  = "root";
$password = "";
$base     = "checabot";

$conn = new mysqli($host, $usuario, $password, $base);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>