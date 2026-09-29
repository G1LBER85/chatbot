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
  <title>Monitor de Cola — Panel de Administrador ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
  <style>
    .monitor-container {
      background: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .stat-box {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: linear-gradient(135deg, #048A81 0%, #037773 100%);
      color: white;
      padding: 20px;
      border-radius: 8px;
      text-align: center;
    }
    
    .stat-card h3 {
      margin: 0 0 10px 0;
      font-size: 14px;
      opacity: 0.9;
    }
    
    .stat-card .numero {
      font-size: 48px;
      font-weight: bold;
    }
    
    .cola-status {
      background: #f8f9fa;
      border-left: 4px solid #048A81;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    
    .cola-status h3 {
      margin-top: 0;
    }
    
    .estado-badge {
      display: inline-block;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: bold;
      font-size: 14px;
    }
    
    .estado-activo {
      background: #d4edda;
      color: #155724;
    }
    
    .estado-parado {
      background: #f8d7da;
      color: #721c24;
    }
    
    .instrucciones {
      background: #e7f3ff;
      border-left: 4px solid #2196F3;
      padding: 20px;
      border-radius: 8px;
      margin-top: 30px;
    }
    
    .instrucciones h3 {
      margin-top: 0;
      color: #1976D2;
    }
    
    .instrucciones code {
      background: #f0f0f0;
      padding: 10px 15px;
      border-radius: 5px;
      display: block;
      margin: 10px 0;
      font-family: 'Courier New', monospace;
      overflow-x: auto;
    }
    
    .btn-limpiar {
      background: #e74c3c;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      margin-top: 20px;
    }
    
    .btn-limpiar:hover {
      background: #c0392b;
    }
    
    .refresh-auto {
      text-align: center;
      color: #999;
      margin-top: 20px;
      font-size: 12px;
    }
  </style>
</head>
<body>

<div class="layout">

  <?php include '../sidebar/sidebar.php'; ?>

  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>📊 Monitor de Cola Redis</h1>
        <p>Monitorea el estado de mensajes en la cola</p>
      </div>
    </div>

    <div class="monitor-container">
      
      <div class="stat-box">
        <div class="stat-card">
          <h3>📬 Mensajes en Cola</h3>
          <div class="numero"><?= $redis->tamanoCola() ?></div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
          <h3>✅ Redis Conectado</h3>
          <div class="numero" style="font-size: 32px;">✓</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
          <h3>⏱️ Última Actualización</h3>
          <div style="font-size: 14px; margin-top: 10px;"><?= date('H:i:s') ?></div>
        </div>
      </div>

      <div class="cola-status">
        <h3>⚙️ Estado del Sistema</h3>
        
        <p>
          <strong>Worker Redis:</strong>
          <span class="estado-badge estado-parado">⏹️ PARADO</span>
        </p>
        
        <p style="color: #666; margin: 15px 0;">
          El worker procesa automáticamente los mensajes de la cola. 
          Si está parado, debes iniciarlo manualmente.
        </p>
        
        <form method="POST" style="display: inline;">
          <button type="submit" name="limpiar_cola" value="1" class="btn-limpiar" onclick="return confirm('¿Borrar TODOS los mensajes pendientes?')">
            🗑️ Limpiar Cola
          </button>
        </form>
      </div>

      <div class="instrucciones">
        <h3>🚀 Cómo iniciar el Worker</h3>
        
        <p><strong>En Windows (PowerShell):</strong></p>
        <code>cd C:\xampp\htdocs\chatbot
php worker_mensajes.php</code>
        
        <p><strong>En Linux/Mac (Terminal):</strong></p>
        <code>cd /var/www/chatbot
php worker_mensajes.php</code>
        
        <p style="color: #666; font-size: 14px;">
          💡 <strong>Tip:</strong> Ejecuta el worker en una ventana terminal separada. 
          Deberá estar corriendo constantemente para procesar mensajes.
        </p>
        
        <p style="color: #666; font-size: 14px;">
          🔄 <strong>Producción:</strong> En servidor, usa <code>supervisor</code> o <code>systemd</code> 
          para mantener el worker activo automáticamente.
        </p>
      </div>

      <div class="refresh-auto">
        🔄 Actualizar cada 5 segundos
      </div>

    </div>
  </main>

</div>

<script>
  // Auto-refresh cada 5 segundos
  setTimeout(() => {
    location.reload();
  }, 5000);
</script>

<?php
// LIMPIAR COLA SI SE SOLICITA
if (isset($_POST['limpiar_cola'])) {
    $redis->limpiarCache();
    header('Location: monitor_cola.php');
    exit;
}
?>

</body>
</html>