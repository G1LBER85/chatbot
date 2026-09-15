<?php

// =====================================================
// crear_admin.php
// =====================================================
// Script de UN SOLO USO para crear la primera cuenta de
// administrador. BORRA este archivo del servidor en
// cuanto termines: si se queda publicado, cualquiera
// podría crear cuentas nuevas.
// =====================================================

//require_once __DIR__ . '/config_sesion.php';
// Corrección de la ruta a la raíz del proyecto
require_once __DIR__ . '/conexion.php'; // Conexión que define $conexion

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario = trim($_POST['usuario'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    if ($usuario === '' || $correo === '' || $nombre === '' || $contrasena === '') {

        $error = 'Todos los campos son obligatorios.';

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $error = 'El correo no es válido.';

    } elseif (strlen($contrasena) < 8) {

        $error = 'La contraseña debe tener al menos 8 caracteres.';

    } else {

        // Comprobación de duplicados en la tabla usuarios
        $stmt = $conn->prepare(
            "SELECT id FROM usuarios WHERE usuario = ? OR correo = ?"
        );
        $stmt->bind_param('ss', $usuario, $correo);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {

            $error = 'Ese usuario o correo ya existe.';

        } else {

            $hash = password_hash($contrasena, PASSWORD_DEFAULT);

            // Inserción ordenada según la estructura exacta de tu tabla (nombre_completo, correo, usuario, contrasena)
            $stmtInsert = $conn->prepare(
                "INSERT INTO usuarios (nombre_completo, correo, usuario, contrasena)
                 VALUES (?, ?, ?, ?)"
            );
            $stmtInsert->bind_param('ssss', $nombre, $correo, $usuario, $hash);

            if ($stmtInsert->execute()) {

                $mensaje = "Administrador '$usuario' creado correctamente. "
                    . "Ya puedes borrar este archivo (crear_admin.php).";

            } else {

                $error = 'No se pudo crear el administrador: ' . $stmtInsert->error;

            }

            $stmtInsert->close();

        }

        $stmt->close();

    }

}

$conn->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear administrador — ChecaBot</title>
<link rel="stylesheet" href="css/panel.css">
<style>
  body { background: var(--bg, #f4f6f9); font-family: Arial, sans-serif; }
  .contenedor { max-width: 480px; margin: 60px auto; padding: 20px; }
  .card { background: white; padding: 36px; border-radius: 14px; box-shadow: 0 3px 14px rgba(0,0,0,.08); }
  .card h1 { font-size: 22px; color: var(--navy, #1e293b); margin-bottom: 6px; }
  .card .sub { color: var(--muted, #64748b); font-size: 14px; margin-bottom: 24px; }
  .aviso { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 8px; padding: 12px 14px; font-size: 13px; margin-bottom: 22px; }
  .mensaje.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
  .mensaje.error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
  .form-group { margin-bottom: 15px; }
  .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
  .form-group input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; }
  .form-group small { display: block; font-size: 12px; color: #6c757d; margin-top: 3px; }
</style>
</head>
<body>

<div class="contenedor">
  <div class="card">

    <h1>Crear administrador</h1>
    <p class="sub">Este formulario es temporal, solo para dar de alta la primera cuenta.</p>

    <div class="aviso">
      ⚠️ Borra este archivo del servidor en cuanto termines de crear tu(s) cuenta(s).
    </div>

    <?php if ($mensaje): ?>
      <div class="mensaje success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">

      <div class="form-group">
        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" required>
      </div>

      <div class="form-group">
        <label for="correo">Correo</label>
        <input type="email" id="correo" name="correo" required>
        <small>A este correo llegará el enlace si algún día olvidas tu contraseña.</small>
      </div>

      <div class="form-group">
        <label for="nombre">Nombre completo</label>
        <input type="text" id="nombre" name="nombre" required>
      </div>

      <div class="form-group">
        <label for="contrasena">Contraseña</label>
        <input type="password" id="contrasena" name="contrasena" minlength="8" required>
        <small>Mínimo 8 caracteres.</small>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px; padding: 10px; background: #0d6efd; color: white; border: none; border-radius: 6px; cursor: pointer;">
        Crear administrador
      </button>

    </form>

  </div>
</div>

</body>
</html>