<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/vendor/autoload.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['correo_recuperacion'])) {
    $correo = trim($_POST['correo_recuperacion']);

    // Buscar el usuario por correo
    $stmt = $conn->prepare("SELECT id, usuario FROM usuarios WHERE correo = ? LIMIT 1");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $bytes = random_bytes(5);
        $token = bin2hex($bytes);
        $codigo = rand(1000, 9999);

        // Guardar en la tabla contrasena usando la columna 'correo'
        $stmtPass = $conn->prepare("INSERT INTO contrasena (correo, token, codigo) VALUES (?, ?, ?)");
        $stmtPass->bind_param("ssi", $correo, $token, $codigo);
        $stmtPass->execute();
        $stmtPass->close();

        // Configuración de PHPMailer
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

            $enlace = "http://" . $_SERVER['HTTP_HOST'] . "/chatbot/panel/login/php/recuperacion/reset.php?email=" . urlencode($correo) . "&token=" . $token;

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
            exit;

        } catch (Exception $e) {
            header("Location: ../../index.php?status=error");
            exit;
        }
    } else {
        // Muestra el mismo mensaje aunque el correo no exista por seguridad
        header("Location: ../../index.php?status=enviado");
        exit;
    }
} else {
    header("Location: ../../index.php");
    exit;
}
?>