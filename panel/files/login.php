<?php

// =====================================================
// login.php
// =====================================================
// Formulario + conexión + validación, todo en un archivo.
//   1. Si ya hay sesión activa, manda al dashboard.
//   2. Si llega un POST, valida CSRF, intentos fallidos,
//      usuario y contraseña.
//   3. Si no, muestra el formulario.
// =====================================================

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/config_sesion.php';   // arranca la sesión con cookies seguras
require_once __DIR__ . '/../../conexion.php';

// =====================================================
// SI YA HAY SESIÓN, NO MOSTRAR EL LOGIN OTRA VEZ
// =====================================================

if (isset($_SESSION['admin_id'])) {

    header('Location: index.php');
    exit;

}

// =====================================================
// TOKEN CSRF
// =====================================================
// Se genera una sola vez por sesión y viaja en un campo
// oculto del formulario. Si el token que llega por POST
// no coincide con el guardado en sesión, se rechaza:
// eso evita que otro sitio pueda enviar este formulario
// "a nombre" del administrador sin que él lo sepa.
// =====================================================

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

$error = '';
$usuario = '';

// =====================================================
// PROCESAR EL FORMULARIO (cuando se envía por POST)
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tokenValido =
        isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

    if (!$tokenValido) {

        $error = 'Tu sesión de formulario expiró, intenta de nuevo.';

    } else {

        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';

        if ($usuario === '' || $contrasena === '') {

            $error = 'Escribe tu usuario y tu contraseña.';

        } else {

            // -------------------------------------------------
            // BUSCAR AL ADMINISTRADOR
            // -------------------------------------------------

            $stmt = $conn->prepare(
                "SELECT id, usuario, contrasena, nombre, activo,
                        intentos_fallidos, bloqueado_hasta
                 FROM administradores
                 WHERE usuario = ?
                 LIMIT 1"
            );

            $stmt->bind_param('s', $usuario);
            $stmt->execute();

            $resultado = $stmt->get_result();
            $admin = $resultado->fetch_assoc();

            $stmt->close();

            // -------------------------------------------------
            // ¿LA CUENTA ESTÁ BLOQUEADA TEMPORALMENTE?
            // -------------------------------------------------

            $bloqueadaAhora =
                $admin
                && $admin['bloqueado_hasta']
                && strtotime($admin['bloqueado_hasta']) > time();

            if ($bloqueadaAhora) {

                $minutosRestantes = (int) ceil(
                    (strtotime($admin['bloqueado_hasta']) - time()) / 60
                );

                $error = "Demasiados intentos fallidos. Intenta de nuevo en "
                    . "$minutosRestantes minuto(s).";

            } else {

                // -------------------------------------------------
                // VALIDAR CONTRASEÑA
                //
                // El mismo mensaje de error sirve tanto si el usuario
                // no existe como si la contraseña está mal, para no
                // revelar cuál de los dos datos falló.
                // -------------------------------------------------

                $credencialesValidas =
                    $admin
                    && password_verify($contrasena, $admin['contrasena']);

                if (!$credencialesValidas) {

                    // ---------------------------------------------
                    // SUMAR INTENTO FALLIDO (solo si el usuario existe)
                    // ---------------------------------------------

                    if ($admin) {

                        $intentos = $admin['intentos_fallidos'] + 1;

                        if ($intentos >= 5) {

                            // Bloquear 15 minutos y reiniciar el contador
                            $stmtBloquear = $conn->prepare(
                                "UPDATE administradores
                                 SET intentos_fallidos = 0,
                                     bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                 WHERE id = ?"
                            );
                            $stmtBloquear->bind_param('i', $admin['id']);
                            $stmtBloquear->execute();
                            $stmtBloquear->close();

                            $error = 'Demasiados intentos fallidos. '
                                . 'La cuenta se bloqueó 15 minutos.';

                        } else {

                            $stmtIntento = $conn->prepare(
                                "UPDATE administradores
                                 SET intentos_fallidos = ?
                                 WHERE id = ?"
                            );
                            $stmtIntento->bind_param('ii', $intentos, $admin['id']);
                            $stmtIntento->execute();
                            $stmtIntento->close();

                            $error = 'Usuario o contraseña incorrectos.';

                        }

                    } else {

                        $error = 'Usuario o contraseña incorrectos.';

                    }

                } elseif ((int) $admin['activo'] !== 1) {

                    $error = 'Esta cuenta está desactivada.';

                } else {

                    // -------------------------------------------------
                    // TODO CORRECTO
                    // -------------------------------------------------

                    $stmtActualizar = $conn->prepare(
                        "UPDATE administradores
                         SET ultimo_acceso = NOW(),
                             intentos_fallidos = 0,
                             bloqueado_hasta = NULL
                         WHERE id = ?"
                    );
                    $stmtActualizar->bind_param('i', $admin['id']);
                    $stmtActualizar->execute();
                    $stmtActualizar->close();

                    // Regenerar el ID de sesión previene
                    // "Session Fixation" (que alguien reutilice un
                    // ID de sesión que ya conocía de antes del login).
                    session_regenerate_id(true);

                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_usuario'] = $admin['usuario'];
                    $_SESSION['admin_nombre'] = $admin['nombre'];
                    $_SESSION['ultima_actividad'] = time();

                    unset($_SESSION['csrf_token']);

                    header('Location: index.php');
                    exit;

                }

            }

        }

    }

}

$conn->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — ChecaBot</title>
<link rel="stylesheet" href="css/panel.css">
<style>

  body {
    background: linear-gradient(160deg, #2c3e50 0%, #1a2733 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: Arial, sans-serif;
  }

  .login-tarjeta {
    width: 100%;
    max-width: 380px;
    background: white;
    border-radius: 14px;
    padding: 40px 36px;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
  }

  .login-marca {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 26px;
  }

  .login-marca .logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: var(--teal);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
  }

  .login-marca strong {
    display: block;
    color: var(--navy);
    font-size: 17px;
  }

  .login-marca small {
    display: block;
    color: var(--muted);
    font-size: 12.5px;
  }

  .login-tarjeta h1 {
    font-size: 20px;
    color: var(--navy);
    margin-bottom: 4px;
  }

  .login-tarjeta > p {
    color: var(--muted);
    font-size: 13.5px;
    margin-bottom: 24px;
  }

  .campo-clave {
    position: relative;
  }

  .campo-clave button {
    position: absolute;
    right: 10px;
    top: 34px;
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    font-size: 13px;
  }

  .btn-entrar {
    width: 100%;
    margin-top: 6px;
  }

  .login-extra {
    display: flex;
    justify-content: flex-end;
    margin: -8px 0 18px;
  }

  .login-extra a {
    font-size: 12.5px;
    color: var(--teal-dark);
    text-decoration: none;
  }

  .login-extra a:hover {
    text-decoration: underline;
  }

  .login-pie {
    margin-top: 22px;
    text-align: center;
    font-size: 12px;
    color: var(--muted);
  }

</style>
</head>
<body>

<div class="login-tarjeta">

  <div class="login-marca">
    <div class="logo">🎒</div>
    <div>
      <strong>ChecaBot</strong>
      <small>Panel de administración</small>
    </div>
  </div>

  <h1>Inicia sesión</h1>
  <p>Ingresa tus credenciales para continuar.</p>

  <?php if ($error): ?>
    <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php" autocomplete="off">

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="form-group">
      <label for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario"
             value="<?= htmlspecialchars($usuario) ?>"
             autocomplete="username" required autofocus>
    </div>

    <div class="form-group campo-clave">
      <label for="contrasena">Contraseña</label>
      <input type="password" id="contrasena" name="contrasena"
             autocomplete="current-password" required>
      <button type="button" onclick="alternarClave()" id="btnAlternar">Mostrar</button>
    </div>

    <div class="login-extra">
      <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
    </div>

    <button type="submit" class="btn btn-primary btn-entrar">
      Entrar
    </button>

  </form>

  <p class="login-pie">Preparatoria No. 3 · Control de entradas y salidas</p>

</div>

<script>
function alternarClave() {
  const input = document.getElementById('contrasena');
  const boton = document.getElementById('btnAlternar');
  const oculto = input.type === 'password';
  input.type = oculto ? 'text' : 'password';
  boton.textContent = oculto ? 'Ocultar' : 'Mostrar';
}
</script>

</body>
</html>
