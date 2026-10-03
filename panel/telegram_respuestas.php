<?php
require_once __DIR__ . '/login/php/inicio/auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../redis_config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$paginaActual = 'telegram_respuestas';
$seccion = $_GET['seccion'] ?? 'respuestas'; // respuestas o masivos
$directorio_imagenes = __DIR__ . '/../imagenes_telegram/';

// Mensaje que viene de la redirección después de un POST
$mensaje = $_SESSION['flash']['mensaje'] ?? '';
$tipo_mensaje = $_SESSION['flash']['tipo'] ?? '';
unset($_SESSION['flash']);


// ========== FUNCIONES ==========

if (!function_exists('escaparMd')) {
    function escaparMd($texto) {
        return str_replace(['_', '*', '`', '['], ['\_', '\*', '\`', '\['], (string) $texto);
    }
}

/**
 * Revisa el CONTENIDO del archivo (no solo el nombre) para confirmar que es PNG
 */
function esPngValido($archivo) {
    $info = @getimagesize($archivo['tmp_name']);
    return $info !== false && $info[2] === IMAGETYPE_PNG;
}

/**
 * Evita que al recargar la página (F5) se reenvíe el formulario
 */
function redirigirConMensaje($seccion, $mensaje, $tipo) {
    $_SESSION['flash'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
    header("Location: ?seccion=" . urlencode($seccion));
    exit;
}


// ========== RESPUESTAS PREDETERMINADAS ==========

// GUARDAR/ACTUALIZAR RESPUESTA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar']) && $seccion === 'respuestas') {
    $numero          = intval($_POST['numero']);
    $titulo          = trim($_POST['titulo'] ?? '');
    $respuesta_texto = trim($_POST['respuesta_texto'] ?? '');
    $avisos          = [];
    $hubo_error      = false;

    // Obtener imagen actual de la BD
    $stmt_actual = $conn->prepare("SELECT ruta_imagen FROM telegram_respuestas WHERE numero = ?");
    $stmt_actual->bind_param("i", $numero);
    $stmt_actual->execute();
    $respuesta_actual = $stmt_actual->get_result()->fetch_assoc();

    $ruta_imagen = $respuesta_actual['ruta_imagen'] ?? '';

    // PROCESAR CARGA DE IMAGEN PNG (solo si se eligió un archivo)
    if (isset($_FILES['imagen_png']) && $_FILES['imagen_png']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['imagen_png']['error'] !== UPLOAD_ERR_OK) {
            $avisos[] = "❌ Error al subir la imagen (código {$_FILES['imagen_png']['error']})";
            $hubo_error = true;

        } elseif (!esPngValido($_FILES['imagen_png'])) {
            $avisos[] = "❌ Solo se permiten imágenes PNG";
            $hubo_error = true;

        } else {
            if (!is_dir($directorio_imagenes)) {
                mkdir($directorio_imagenes, 0755, true);
            }

            $nombre_unico = 'respuesta_' . $numero . '_' . time() . '.png';

            if (move_uploaded_file($_FILES['imagen_png']['tmp_name'], $directorio_imagenes . $nombre_unico)) {
                // CORRECCIÓN: la imagen vieja se borra SOLO después de subir la nueva
                if (!empty($ruta_imagen)) {
                    $archivo_antiguo = __DIR__ . '/../' . str_replace(URL_BASE, '', $ruta_imagen);
                    if (is_file($archivo_antiguo)) {
                        unlink($archivo_antiguo);
                    }
                }
                $ruta_imagen = 'imagenes_telegram/' . $nombre_unico;
                $avisos[] = "✅ Imagen cargada correctamente";
            } else {
                $avisos[] = "❌ Error al guardar la imagen en el servidor";
                $hubo_error = true;
            }
        }
    }

    // GUARDAR EN BASE DE DATOS
    $stmt = $conn->prepare("UPDATE telegram_respuestas SET titulo = ?, respuesta_texto = ?, ruta_imagen = ? WHERE numero = ?");
    $stmt->bind_param("sssi", $titulo, $respuesta_texto, $ruta_imagen, $numero);

    if ($stmt->execute()) {
        // NUEVO: borrar del caché para que el bot use la versión actualizada
        $redis->borrarRespuesta($numero);
        array_unshift($avisos, "✅ Respuesta #$numero guardada correctamente");
    } else {
        array_unshift($avisos, "❌ Error al guardar la respuesta #$numero");
        $hubo_error = true;
    }

    redirigirConMensaje('respuestas', implode(' — ', $avisos), $hubo_error ? 'error' : 'success');
}


// ========== MENSAJES MASIVOS ==========

// ENVIAR MENSAJE MASIVO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_masivo']) && $seccion === 'masivos') {
    $titulo_masivo = trim($_POST['titulo_masivo'] ?? '');
    $texto_masivo  = trim($_POST['texto_masivo'] ?? '');

    if ($titulo_masivo === '' || $texto_masivo === '') {
        redirigirConMensaje('masivos', "❌ El título y el mensaje son obligatorios", 'error');
    }

    if (!$redis->disponible()) {
        redirigirConMensaje('masivos', "❌ Redis no está disponible. El mensaje NO se envió.", 'error');
    }

    // PROCESAR IMAGEN (opcional)
    $ruta_imagen_masiva = '';
    $aviso_imagen = '';

    if (isset($_FILES['imagen_masiva']) && $_FILES['imagen_masiva']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['imagen_masiva']['error'] === UPLOAD_ERR_OK && esPngValido($_FILES['imagen_masiva'])) {
            if (!is_dir($directorio_imagenes)) {
                mkdir($directorio_imagenes, 0755, true);
            }
            $nombre_unico = 'masivo_' . time() . '.png';
            if (move_uploaded_file($_FILES['imagen_masiva']['tmp_name'], $directorio_imagenes . $nombre_unico)) {
                $ruta_imagen_masiva = 'imagenes_telegram/' . $nombre_unico;
            }
        }
        if (!$ruta_imagen_masiva) {
            $aviso_imagen = " (⚠️ la imagen no se pudo cargar; solo se envió el texto)";
        }
    }

    // OBTENER TODOS LOS TUTORES REGISTRADOS
    $tutores = $conn->query("SELECT tutor_chat_id FROM alumnos WHERE tutor_chat_id IS NOT NULL AND tutor_chat_id != 0 GROUP BY tutor_chat_id");

    // Se escapa el texto para que un "_" o "*" no haga que Telegram rechace el mensaje
    $texto_telegram = "🚨 *MENSAJE DE EMERGENCIA*\n\n*" . escaparMd($titulo_masivo) . "*\n\n" . escaparMd($texto_masivo);

    $tutores_alcanzados = 0;

    // AHORA SE USA LA COLA: el worker envía los mensajes, así la página
    // no se queda cargando con muchos tutores y se ven los errores en la consola
    while ($tutor = $tutores->fetch_assoc()) {
        $chat_id = $tutor['tutor_chat_id'];

        if ($redis->agregarACola([
            'tipo'    => 'mensaje',
            'chat_id' => $chat_id,
            'texto'   => $texto_telegram
        ]) === false) {
            continue;
        }

        if ($ruta_imagen_masiva) {
            $redis->agregarACola([
                'tipo'       => 'foto',
                'chat_id'    => $chat_id,
                'url_imagen' => URL_BASE . $ruta_imagen_masiva,
                'caption'    => "📸 Imagen adjunta"
            ]);
        }

        $tutores_alcanzados++;
    }

    // GUARDAR EN HISTORIAL
    $stmt_historial = $conn->prepare("INSERT INTO telegram_mensajes_masivos (titulo, texto, ruta_imagen, tutores_alcanzados) VALUES (?, ?, ?, ?)");
    $stmt_historial->bind_param("sssi", $titulo_masivo, $texto_masivo, $ruta_imagen_masiva, $tutores_alcanzados);
    $stmt_historial->execute();

    redirigirConMensaje(
        'masivos',
        "✅ Mensaje en cola para {$tutores_alcanzados} tutores. Se enviará en unos segundos.{$aviso_imagen}",
        $aviso_imagen ? 'error' : 'success'
    );
}


// ========== DATOS PARA MOSTRAR ==========
$respuestas = $conn->query("SELECT * FROM telegram_respuestas ORDER BY numero ASC");
$historial_masivos = $conn->query("SELECT * FROM telegram_mensajes_masivos ORDER BY fecha_envio DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Respuestas Telegram — Panel de Administrador ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
  <style>
    .tabs-nav {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      border-bottom: 2px solid #ddd;
    }

    .tab-link {
      padding: 12px 20px;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: bold;
      color: #666;
      font-size: 16px;
      border-bottom: 3px solid transparent;
      transition: all 0.3s;
    }

    .tab-link.active {
      color: #048A81;
      border-bottom-color: #048A81;
    }

    .tab-link:hover {
      color: #048A81;
    }

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

    .btn-guardar, .btn-enviar {
      background: #048A81;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      width: 100%;
      transition: all 0.3s;
      font-size: 16px;
    }

    .btn-guardar:hover, .btn-enviar:hover {
      background: #037773;
    }

    .btn-enviar {
      background: #e74c3c;
    }

    .btn-enviar:hover {
      background: #c0392b;
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
      height: auto;
      border-radius: 5px;
      border: 1px solid #ddd;
      display: block;
      max-height: 250px;
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

    .historial-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      margin-top: 20px;
    }

    .historial-table th {
      background: #2c3e50;
      color: white;
      padding: 12px;
      text-align: left;
      font-weight: bold;
    }

    .historial-table td {
      padding: 12px;
      border-bottom: 1px solid #ddd;
    }

    .historial-table tr:hover {
      background: #f5f5f5;
    }

    .badge-enviado {
      background: #d4edda;
      color: #155724;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: bold;
    }
  </style>
</head>
<body>

<div class="layout">

  <?php include '../sidebar/sidebar.php'; ?>

  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>🤖 Telegram - Configuración</h1>
        <p>Gestiona respuestas automáticas y mensajes de emergencia</p>
      </div>
    </div>

    <?php if ($mensaje): ?>
      <div class="mensaje <?= htmlspecialchars($tipo_mensaje) ?>">
        <?= htmlspecialchars($mensaje) ?>
      </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs-nav">
      <a href="?seccion=respuestas" class="tab-link <?= $seccion === 'respuestas' ? 'active' : '' ?>">
        💬 Respuestas Automáticas
      </a>
      <a href="?seccion=masivos" class="tab-link <?= $seccion === 'masivos' ? 'active' : '' ?>">
        🚨 Mensajes de Emergencia
      </a>
    </div>

    <div class="form-box">

      <!-- SECCIÓN RESPUESTAS AUTOMÁTICAS -->
      <?php if ($seccion === 'respuestas'): ?>

        <div class="info-box">
          <strong>ℹ️ Instrucciones:</strong> Configura aquí los 10 mensajes predeterminados que recibirán los tutores cuando interactúen con el bot de Telegram.
        </div>

        <div class="respuestas-grid">
          <?php while ($respuesta = $respuestas->fetch_assoc()): ?>

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
              </div>

              <?php if (!empty($respuesta['ruta_imagen'])): ?>
                <div class="preview-container">
                  <p style="font-size: 12px; color: #666; margin-bottom: 8px;">📸 Imagen actual:</p>
                  <img src="../<?= htmlspecialchars($respuesta['ruta_imagen']) ?>" class="preview-imagen" alt="Preview">
                </div>
              <?php endif; ?>

              <button type="submit" name="guardar" value="1" class="btn-guardar">
                💾 Guardar opción #<?= $respuesta['numero'] ?>
              </button>
            </form>
          </div>

          <?php endwhile; ?>
        </div>

      <!-- SECCIÓN MENSAJES DE EMERGENCIA -->
      <?php else: ?>

        <div class="info-box">
          <strong>🚨 Mensajes de Emergencia:</strong> Envía mensajes masivos a todos los tutores registrados. Úsalo para comunicar emergencias, avisos importantes o eventos especiales.
        </div>

        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px;">
          <h3 style="margin-bottom: 20px;">✉️ Enviar Mensaje Masivo</h3>

          <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label>📌 Asunto/Título (Obligatorio)</label>
              <input type="text" name="titulo_masivo" placeholder="Ej: Cierre por mantenimiento" required>
            </div>

            <div class="form-group">
              <label>📝 Mensaje (Obligatorio)</label>
              <textarea name="texto_masivo" placeholder="Escribe el mensaje que llegará a todos los tutores..." required></textarea>
            </div>

            <div class="form-group">
              <label>🖼️ Imagen PNG (Opcional)</label>
              <input type="file" name="imagen_masiva" accept=".png,image/png">
            </div>

            <button type="submit" name="enviar_masivo" value="1" class="btn-enviar" onclick="return confirm('¿Estás seguro? El mensaje se enviará a TODOS los tutores registrados.')">
              🚨 ENVIAR A TODOS LOS TUTORES
            </button>
          </form>
        </div>

        <h3 style="margin-bottom: 20px;">📋 Historial de Mensajes Enviados</h3>

        <?php if ($historial_masivos->num_rows > 0): ?>
          <table class="historial-table">
            <thead>
              <tr>
                <th>Asunto</th>
                <th>Tutores Alcanzados</th>
                <th>Fecha de Envío</th>
                <th>Imagen</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($historial = $historial_masivos->fetch_assoc()): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($historial['titulo']) ?></strong><br>
                  <small style="color: #666;"><?= htmlspecialchars(mb_substr($historial['texto'], 0, 60, 'UTF-8')) ?>...</small>
                </td>
                <td><span class="badge-enviado"><?= (int) $historial['tutores_alcanzados'] ?> tutores</span></td>
                <td><?= date('d/m/Y H:i', strtotime($historial['fecha_envio'])) ?></td>
                <td><?= $historial['ruta_imagen'] ? '✅' : '❌' ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="color: #999; text-align: center; padding: 40px;">No hay mensajes enviados aún.</p>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </main>

</div>

</body>
</html>