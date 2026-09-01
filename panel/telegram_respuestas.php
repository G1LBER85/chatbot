<?php
require '../conexion.php';

$paginaActual = 'telegram_respuestas';
$mensaje = '';
$tipo_mensaje = '';

// GUARDAR/ACTUALIZAR RESPUESTA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $numero = intval($_POST['numero']);
    $titulo = trim($_POST['titulo']);
    $respuesta_texto = trim($_POST['respuesta_texto']);
    
    // Obtener respuesta actual de la BD
    $stmt_actual = $conn->prepare("SELECT ruta_imagen FROM telegram_respuestas WHERE numero = ?");
    $stmt_actual->bind_param("i", $numero);
    $stmt_actual->execute();
    $respuesta_actual = $stmt_actual->get_result()->fetch_assoc();
    
    $ruta_imagen = $respuesta_actual['ruta_imagen'] ?? ''; // Imagen anterior
    
    // PROCESAR CARGA DE IMAGEN PNG
    if (isset($_FILES['imagen_png']) && $_FILES['imagen_png']['error'] === UPLOAD_ERR_OK) {
        $archivo_tmp = $_FILES['imagen_png']['tmp_name'];
        $nombre_archivo = $_FILES['imagen_png']['name'];
        $tipo_archivo = $_FILES['imagen_png']['type'];
        
        // VALIDAR QUE SEA PNG
        if ($tipo_archivo === 'image/png' || pathinfo($nombre_archivo, PATHINFO_EXTENSION) === 'png') {
            $directorio = '../imagenes_telegram/';
            
            // Crear directorio si no existe
            if (!is_dir($directorio)) {
                mkdir($directorio, 0755, true);
            }
            
            // ELIMINAR IMAGEN ANTIGUA SI EXISTE
            if (!empty($respuesta_actual['ruta_imagen'])) {
                // Extraer la ruta relativa de la URL si es necesario
                $ruta_antigua_url = $respuesta_actual['ruta_imagen'];
                $ruta_antigua = str_replace('https://sash-sake-guidance.ngrok-free.dev/chatbot/', '', $ruta_antigua_url);
                $archivo_antigua = '../' . $ruta_antigua;
                
                if (file_exists($archivo_antigua)) {
                    unlink($archivo_antigua);
                    $mensaje = "🗑️ Imagen anterior eliminada. ";
                    $tipo_mensaje = "success";
                }
            }
            
            // Generar nombre único
            $nombre_unico = 'respuesta_' . $numero . '_' . time() . '.png';
            $ruta_destino = $directorio . $nombre_unico;
            
            // Mover archivo
            if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                $ruta_imagen_relativa = 'imagenes_telegram/' . $nombre_unico;
                $ruta_imagen = 'https://sash-sake-guidance.ngrok-free.dev/chatbot/' . $ruta_imagen_relativa;
                
                if (!$mensaje) {
                    $mensaje = "✅ Nueva imagen cargada correctamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje .= "✅ Nueva imagen cargada correctamente";
                }
            } else {
                $mensaje = "❌ Error al cargar la imagen";
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "❌ Solo se permiten imágenes PNG";
            $tipo_mensaje = "error";
        }
    }
    
    // GUARDAR EN BASE DE DATOS
    $stmt = $conn->prepare("UPDATE telegram_respuestas SET titulo = ?, respuesta_texto = ?, ruta_imagen = ? WHERE numero = ?");
    $stmt->bind_param("sssi", $titulo, $respuesta_texto, $ruta_imagen, $numero);
    
    if ($stmt->execute()) {
        if (!$mensaje) {
            $mensaje = "✅ Respuesta #$numero guardada correctamente";
            $tipo_mensaje = "success";
        }
    } else {
        $mensaje = "❌ Error al guardar";
        $tipo_mensaje = "error";
    }
}

// OBTENER TODAS LAS RESPUESTAS
$respuestas = $conn->query("SELECT * FROM telegram_respuestas ORDER BY numero ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Respuestas Telegram — Panel de Administrador ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
  <style>
    .respuestas-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .respuesta-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      padding: 20px;
      border-left: 4px solid #048A81;
    }
    
    .respuesta-numero {
      font-size: 12px;
      color: #999;
      margin-bottom: 10px;
      font-weight: bold;
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #333;
      font-size: 14px;
    }
    
    .form-group input[type="text"],
    .form-group input[type="file"],
    .form-group textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
      font-family: Arial, sans-serif;
    }
    
    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #048A81;
      box-shadow: 0 0 5px rgba(4, 138, 129, 0.3);
    }
    
    .btn-guardar {
      background: #048A81;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      width: 100%;
      transition: all 0.3s;
    }
    
    .btn-guardar:hover {
      background: #037773;
    }
    
    .mensaje {
      margin-bottom: 20px;
      padding: 15px;
      border-radius: 8px;
      font-weight: bold;
    }
    
    .success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .preview-imagen {
      margin-top: 10px;
      max-width: 100%;
      max-height: 200px;
      border-radius: 5px;
      border: 1px solid #ddd;
    }
    
    .preview-container {
      margin-top: 10px;
      text-align: center;
    }
    
    .info-box {
      background: #e7f3ff;
      border-left: 4px solid #2196F3;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    
    .info-box strong {
      color: #1976D2;
    }
  </style>
</head>
<body>

<div class="layout">

  <?php include '../sidebar/sidebar.php'; ?>

  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>🤖 Respuestas de Telegram</h1>
        <p>Configura las 10 respuestas predeterminadas con imágenes PNG</p>
      </div>
    </div>

    <?php if ($mensaje): ?>
      <div class="mensaje <?= $tipo_mensaje ?>">
        <?= $mensaje ?>
      </div>
    <?php endif; ?>

    <div class="form-box">
      <div class="info-box">
        <strong>ℹ️ Instrucciones:</strong> Carga imágenes PNG directamente desde tu computadora. Las imágenes antiguas se eliminarán automáticamente al subir una nueva.
      </div>

      <div class="respuestas-grid">
        <?php 
        while ($respuesta = $respuestas->fetch_assoc()): 
        ?>
        
        <div class="respuesta-card">
          <div class="respuesta-numero">📌 Opción #<?= $respuesta['numero'] ?></div>
          
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="numero" value="<?= $respuesta['numero'] ?>">
            
            <div class="form-group">
              <label>📝 Título/Pregunta</label>
              <input type="text" name="titulo" placeholder="Ej: Información de fotos" 
                     value="<?= htmlspecialchars($respuesta['titulo'] ?? '') ?>">
            </div>
            
            <div class="form-group">
              <label>💬 Respuesta (Texto)</label>
              <textarea name="respuesta_texto" placeholder="Escribe la respuesta que recibirán los tutores..."><?= htmlspecialchars($respuesta['respuesta_texto'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
              <label>🖼️ Imagen PNG (opcional)</label>
              <input type="file" name="imagen_png" accept=".png,image/png">
              <small style="color: #999; display: block; margin-top: 5px;">Solo se aceptan archivos PNG</small>
            </div>
            
            <?php if ($respuesta['ruta_imagen'] && !empty($respuesta['ruta_imagen'])): ?>
              <div class="preview-container">
                <p style="font-size: 12px; color: #666; margin-bottom: 8px;">📸 Imagen actual:</p>
                <img src="<?= $respuesta['ruta_imagen'] ?>?v=<?= time() ?>" class="preview-imagen" alt="Preview">
              </div>
            <?php endif; ?>
            
            <button type="submit" name="guardar" value="1" class="btn-guardar">
              💾 Guardar opción #<?= $respuesta['numero'] ?>
            </button>
          </form>
        </div>
        
        <?php endwhile; ?>
      </div>
    </div>
  </main>

</div>

</body>
</html>