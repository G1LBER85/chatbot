<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** @var mysqli $conn */

require_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/vendor/autoload.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['correo_recuperacion'])) {
    $correo = trim($_POST['correo_recuperacion']);

    // Buscar el usuario por correo en la base de datos
    $stmt = $conn->prepare("SELECT id, usuario FROM usuarios WHERE correo = ? LIMIT 1");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Generar token y código único
        $bytes = random_bytes(5);
        $token = bin2hex($bytes);
        $codigo = rand(1000, 9999);

        // Guardar la solicitud en la tabla contrasena
        $stmtPass = $conn->prepare("INSERT INTO contrasena (correo, token, codigo) VALUES (?, ?, ?)");
        $stmtPass->bind_param("ssi", $correo, $token, $codigo);
        $stmtPass->execute();
        $stmtPass->close();

        // Configuración y envío del correo con PHPMailer
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jennifermezamancilla55@gmail.com';
            $mail->Password   = 'tbzrmprvbqfpgspg';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('jennifermezamancilla55@gmail.com', 'Sistema ChecaBot');
            $mail->addAddress($correo, $user['usuario']);

            // OPCIÓN 1: Detecta dinámicamente si entraste por IP (ej. 192.168.X.X) o por dominio
            $host = $_SERVER['HTTP_HOST'];
            $enlace = "http://" . $host . "/chatbot/panel/login/php/recuperacion/reset.php?email=" . urlencode($correo) . "&token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Restablecer contraseña — ChecaBot';
            $mail->Body    = "
                <h2>Hola, {$user['usuario']}</h2>
                <p>Has solicitado restablecer tu contraseña.</p>
                <p>Tu código de verificación es: <strong>{$codigo}</strong></p>
                <p><a href='{$enlace}' style='background:#46A2FD; color:#fff; padding:10px 15px; text-decoration:none; border-radius:5px;'>Haz clic aquí para restablecer tu contraseña</a></p>
                <p><small>Si no solicitaste este cambio, puedes ignorar este mensaje.</small></p>
            ";

            $mail->send();
            header("Location: ../../index.php?status=enviado");
            exit();

        } catch (Exception $e) {
            header("Location: ../../index.php?status=error_mail");
            exit();
        }
    } else {
        // Redirección cuando el correo NO está registrado
        header("Location: ../../index.php?status=no_registrado");
        exit();
    }
} else {
    header("Location: ../../index.php");
    exit();
}
?>