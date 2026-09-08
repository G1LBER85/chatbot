<?php
session_start();
include '../../../conexion.php';

/** @var mysqli $conn */

$correo = $_POST['correo'];
$contrasena = $_POST['contrasena'];

// 1. Buscar al usuario solo por correo
$validar_login = mysqli_query($conn, "SELECT * FROM usuarios WHERE correo='$correo'");

if(mysqli_num_rows($validar_login) > 0){
    $usuario = mysqli_fetch_assoc($validar_login);
    
    // 2. Verificar si la contraseña ingresada coincide con el hash almacenado
    if(password_verify($contrasena, $usuario['contrasena'])){
        $_SESSION['usuario'] = $correo;
        header("location: ../../dashboard.php");
        exit;
    } else {
        echo '
            <script>
                alert("Contraseña incorrecta, por favor verifique los datos");
                window.location = "../index.php";
            </script>
        ';
        exit;
    }
} else {
    echo '
        <script>
            alert("Usuario no existe, por favor verifique los datos introducidos");
            window.location = "../index.php";
        </script>
    ';
    exit;
}
?>