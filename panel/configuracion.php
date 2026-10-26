<?php
require_once '../conexion.php';

$paginaActual = 'configuracion';
$mensaje = '';
$tipo_mensaje = '';

// GUARDAR TOKEN Y REGISTRAR WEBHOOK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $token = trim($_POST['telegram_token']);
    $webhook = trim($_POST['telegram_webhook_url']);

    $stmt = $conn->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");

    $clave = 'telegram_token';
    $stmt->bind_param("ss", $token, $clave);
    $stmt->execute();

    $clave = 'telegram_webhook_url';
    $stmt->bind_param("ss", $webhook, $clave);
    $stmt->execute();

    // Registrar webhook con el nuevo token
    $url = "https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($webhook) . "&allowed_updates=" . urlencode('["message","callback_query"]');
    $resp = @json_decode(@file_get_contents($url), true);

    if (!empty($resp['ok'])) {
        $mensaje = "✅ Token guardado y webhook registrado correctamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "⚠️ Token guardado, pero Telegram respondió: " . ($resp['description'] ?? 'sin conexión');
        $tipo_mensaje = "error";
    }
}

// OBTENER VALORES ACTUALES
$config = [];
$res = $conn->query("SELECT clave, valor FROM configuracion");
while ($fila = $res->fetch_assoc()) {
    $config[$fila['clave']] = $fila['valor'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuración — ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
</head>
<body>
<div class="layout">
  <?php include '../sidebar/sidebar.php'; ?>
  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>⚙️ Configuración</h1>
        <p>Token del bot de Telegram y webhook</p>
      </div>
    </div>

    <?php if ($mensaje): ?>
      <div class="mensaje <?= $tipo_mensaje ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <div class="form-box">
      <form method="POST">
        <div class="form-group">
          <label>🔑 Token del bot de Telegram</label>
          <input type="text" name="telegram_token" required
                 value="<?= htmlspecialchars($config['telegram_token'] ?? '') ?>">
          <small>Lo obtienes en BotFather → API Token</small>
        </div>

        <div class="form-group">
          <label>🌐 URL del webhook</label>
          <input type="url" name="telegram_webhook_url" required
                 value="<?= htmlspecialchars($config['telegram_webhook_url'] ?? '') ?>">
          <small>Cambia solo si cambia la URL de ngrok</small>
        </div>

        <button type="submit" name="guardar" value="1" class="btn btn-primary">
          💾 Guardar y registrar webhook
        </button>
      </form>
    </div>
  </main>
</div>
</body>
</html>