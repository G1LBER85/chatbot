<?php
session_start();
include '../../../../conexion.php';

/** @var mysqli $conn */

$usuario = $_POST['usuario'];
$contrasena = $_POST['contrasena'];

// 1. Buscar al usuario por correo o nombre de usuario
$validar_login = mysqli_query($conn, "SELECT * FROM usuarios WHERE usuario='$usuario'");

if(mysqli_num_rows($validar_login) > 0){
    $correo = mysqli_fetch_assoc($validar_login);
    
    // 2. Verificar la contraseña encriptada
    if(password_verify($contrasena, $correo['contrasena'])){
        $_SESSION['logueado'] = true;
        $_SESSION['correo'] = $correo;
        
        header("Location: ../../../dashboard.php");
        exit();
    } else {
        // Redirige sin alert nativo para mostrar modal de contraseña incorrecta
        header("Location: ../../index.php?status=error_pass");
        exit();
    }
} else {
    // Redirige si el usuario no existe en la base de datos
    header("Location: ../../index.php?status=no_usuario");
    exit();
}
?>