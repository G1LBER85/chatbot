<?php
require '../conexion.php';
require '../redis_config.php';

$paginaActual = 'monitor_cola';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Monitor de Cola — ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
  <style>
    .monitor-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .stat-card { background: linear-gradient(135deg, #048A81 0%, #037773 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
    .stat-card .numero { font-size: 48px; font-weight: bold; }
    .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 20px; border-radius: 8px; margin-top: 20px; }
  </style>
</head>
<body>

<div class="layout">
  <?php include '../sidebar/sidebar.php'; ?>
  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>📊 Monitor de Cola Redis</h1>
        <p>Estado en tiempo real</p>
      </div>
    </div>

    <div class="monitor-container">
      <div class="stat-card">
        <h3>📬 Mensajes en Cola</h3>
        <div class="numero"><?= $redis->tamanoCola() ?></div>
      </div>
      
      <div class="info-box">
        <strong>Estado:</strong> Redis está conectado y funcional.
      </div>
    </div>
  </main>
</div>

<script>
  setTimeout(() => { location.reload(); }, 5000);
</script>

</body>
</html>