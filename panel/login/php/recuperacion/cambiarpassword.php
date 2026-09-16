<?php 
// Ruta corregida apuntando a la carpeta /chatbot/
require_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/conexion.php';
/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);
    $p1 = trim($_POST['p1']);
    $p2 = trim($_POST['p2']);

    // Validar que las contraseñas coincidan
    if ($p1 === $p2) {
        $passHash = password_hash($p1, PASSWORD_DEFAULT);

        // Actualizar contraseña en la tabla usuarios
        $stmt = $conn->prepare("UPDATE usuarios SET contrasena = ? WHERE correo = ?");
        $stmt->bind_param("ss", $passHash, $correo);

        if ($stmt->execute()) {
            // Eliminar token usado de la tabla contrasena
            $stmtDel = $conn->prepare("DELETE FROM contrasena WHERE correo = ?");
            $stmtDel->bind_param("s", $correo);
            $stmtDel->execute();
            $stmtDel->close();

            // Redirigir al login con modal de éxito
            header("Location: /chatbot/panel/login/index.php?status=pass_updated");
            exit();
        } else {
            header("Location: /chatbot/panel/login/index.php?status=error_update");
            exit();
        }
        $stmt->close();

    } else {
        // Redirigir si las claves no coinciden
        header("Location: /chatbot/panel/login/index.php?status=pass_mismatch");
        exit();
    }
} else {
    header("Location: /chatbot/panel/login/index.php");
    exit();
}
?>