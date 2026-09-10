<?php
session_start();
include '../../../../conexion.php';

/** @var mysqli $conn */

$usuario = $_POST['usuario'];
$contrasena = $_POST['contrasena'];

// 1. Buscar al usuario solo por correo
$validar_login = mysqli_query($conn, "SELECT * FROM usuarios WHERE usuario='$usuario'");

if(mysqli_num_rows($validar_login) > 0){
    $correo = mysqli_fetch_assoc($validar_login);
    
    // 2. Verificar si la contraseña ingresada coincide con el hash almacenado
    if(password_verify($contrasena, $correo['contrasena'])){
        
        // --- AGREGA ESTA LÍNEA OBLIGATORIA ---
        $_SESSION['logueado'] = true;
        $_SESSION['correo'] = $correo;
        
        header("location: ../../../dashboard.php");
        exit;
    } else {
        echo '
            <script>
                alert("Contraseña incorrecta, por favor verifique los datos");
                window.location = "../../index.php";
            </script>
        ';
        exit;
    }
} else {
    echo '
        <script>
            alert("Usuario no existe, por favor verifique los datos introducidos");
            window.location = "../../index.php";
        </script>
    ';
    exit;
}
?>