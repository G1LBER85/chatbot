<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no está logueado, redirige enviando el parámetro
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: /chatbot/panel/login/index.php?status=login_requerido");
    exit();
}
?>