<?php
/** @var mysqli $conn */
require_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/conexion.php';

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

// Validar que el token exista en la base de datos
$valido = false;
if (!empty($email) && !empty($token)) {
    $stmt = $conn->prepare("SELECT id FROM contrasena WHERE correo = ? AND token = ? LIMIT 1");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $valido = true;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña — ChecaBot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .card-reset {
            width: 100%;
            max-width: 420px;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: #fff;
        }
    </style>
</head>
<body>

<div class="card-reset">
    <h3 class="text-center mb-3">Nueva Contraseña</h3>

    <?php if ($valido): ?>
        <form action="cambiarpassword.php" method="POST">
            <input type="hidden" name="correo" value="<?php echo htmlspecialchars($email); ?>">
            
            <div class="mb-3">
                <label class="form-label">Nueva Contraseña</label>
                <input type="password" name="p1" class="form-control" required placeholder="Escribe tu nueva clave">
            </div>

            <div class="mb-3">
                <label class="form-label">Confirmar Contraseña</label>
                <input type="password" name="p2" class="form-control" required placeholder="Confirma tu nueva clave">
            </div>

            <button type="submit" class="btn btn-primary w-100" style="background-color: #46A2FD; border:none;">
                Actualizar Contraseña
            </button>
        </form>
    <?php else: ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Enlace inválido o expirado',
                    text: 'El enlace de recuperación no es válido o ya fue utilizado.',
                    confirmButtonColor: '#46A2FD'
                }).then(() => {
                    window.location.href = "../../index.php";
                });
            });
        </script>
    <?php endif; ?>
</div>

</body>
</html>