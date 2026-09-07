<?php

require '../conexion.php';
$paginaActual = 'cargar_imagenes';
$mensaje = '';
$tipo_mensaje = '';

// ── Procesar subida de foto ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alumno_id'])) {

    $id   = intval($_POST['alumno_id']);
    $curp = trim($_POST['alumno_curp'] ?? '');

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {

        $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {

            $carpetaFotos = __DIR__ . '/../fotos';
            if (!is_dir($carpetaFotos)) {
                mkdir($carpetaFotos, 0755, true);
            }

            $nombreArchivo = strtoupper($curp) . '.' . $extension;
            $rutaDestino   = $carpetaFotos . '/' . $nombreArchivo;
            $rutaBD        = 'fotos/' . $nombreArchivo;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino)) {

                $stmt = $conn->prepare("UPDATE alumnos SET foto = ? WHERE id = ?");
                $stmt->bind_param("si", $rutaBD, $id);

                if ($stmt->execute()) {
                    $mensaje      = "✅ Foto actualizada correctamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje      = "❌ Error al guardar en la base de datos";
                    $tipo_mensaje = "error";
                }
                $stmt->close();

            } else {
                $mensaje      = "❌ No se pudo mover el archivo";
                $tipo_mensaje = "error";
            }

        } else {
            $mensaje      = "⚠️ Solo se permiten archivos JPG, JPEG o PNG";
            $tipo_mensaje = "warning";
        }

    } else {
        $mensaje      = "⚠️ No se recibió ningún archivo";
        $tipo_mensaje = "warning";
    }
}

// ── Consultar alumnos ──
$alumnos = $conn->query("SELECT id, nombre, grado, grupo, CURP, foto FROM alumnos ORDER BY id DESC");
?>


<!DOCTYPE html>
<html lang="es">

  <head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
      Cargar Imágenes — Panel de Administrador ChecaBot
    </title>

    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/cargar_imagenes.css">

  </head>

  <body>

    <div class="layout">

      <?php include '../sidebar/sidebar.php'; ?>

      <main class="contenido">

        <?php if ($mensaje): ?>
          <div class="mensaje <?= $tipo_mensaje ?>"><?= $mensaje ?></div>
        <?php endif; ?>

        <div class="panel-header">
          <div>
            <h1>Cargar Imágenes</h1>
            <p>Sube o actualiza la foto de cada alumno</p>
          </div>
        </div>

        <!-- Filtros -->
        <div class="form-box" style="margin-bottom: 20px;">
          <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:20px;">
            <div style="display:flex; gap:14px; flex-wrap:wrap;">
              <div class="form-group" style="margin-bottom:0;">
                <label>Grado</label>
                <select id="filtroGrado">
                  <option value="">Todos</option>
                  <?php for ($g = 1; $g <= 6; $g++): ?>
                    <option value="<?= $g ?>"><?= $g ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label>Grupo</label>
                <select id="filtroGrupo">
                  <option value="">Todos</option>
                  <?php foreach (range('A', 'J') as $letra): ?>
                    <option value="<?= $letra ?>"><?= $letra ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:220px;">
              <label>Buscar por nombre</label>
              <input type="text" id="filtroNombre" placeholder="Ejemplo: Juan Pérez" autocomplete="off">
            </div>
          </div>
        </div>

        <?php if ($alumnos && $alumnos->num_rows > 0): ?>
        <table id="tablaAlumnos">
          <thead>
            <tr>
              <th>ID</th>
              <th>Foto</th>
              <th>Nombre</th>
              <th>Grado/Grupo</th>
              <th>CURP</th>
              <th>Cargar foto</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($alumno = $alumnos->fetch_assoc()): ?>
            <tr
              data-grado="<?= htmlspecialchars($alumno['grado']) ?>"
              data-grupo="<?= htmlspecialchars($alumno['grupo']) ?>"
              data-nombre="<?= htmlspecialchars(mb_strtolower($alumno['nombre'], 'UTF-8')) ?>">
              <td><?= $alumno['id'] ?></td>
              <td>
                <?php if (!empty($alumno['foto'])): ?>
                  <img class="foto-mini" src="<?= htmlspecialchars('../' . str_replace('\\', '/', $alumno['foto'])) ?>" alt="">
                <?php else: ?>
                  <span class="foto-mini" style="display:inline-flex;align-items:center;justify-content:center;">🧑</span>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($alumno['nombre']) ?></strong></td>
              <td><?= htmlspecialchars($alumno['grado']) ?><?= $alumno['grupo'] ? ' ' . htmlspecialchars($alumno['grupo']) : '' ?></td>
              <td><?= $alumno['CURP'] ? htmlspecialchars($alumno['CURP']) : '<span style="color:#bbb;">Sin capturar</span>' ?></td>
              <td>
                <form method="POST" enctype="multipart/form-data" id="form-<?= $alumno['id'] ?>">
                  <input type="hidden" name="alumno_id"   value="<?= $alumno['id'] ?>">
                  <input type="hidden" name="alumno_curp" value="<?= htmlspecialchars($alumno['CURP']) ?>">
                  <input type="file"   name="foto" id="archivo-<?= $alumno['id'] ?>" accept=".jpg,.jpeg,.png" style="display:none"
                    onchange="this.closest('form').submit()">
                </form>
                <button
                  class="btn-camara"
                  title="Subir foto de <?= htmlspecialchars($alumno['nombre']) ?>"
                  onclick="abrirModalFoto(<?= $alumno['id'] ?>, '<?= htmlspecialchars(addslashes($alumno['nombre'])) ?>')">
                  📷
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
            <tr id="filaSinResultados" style="display:none;">
              <td colspan="6" class="empty-state">No se encontraron alumnos con esos filtros.</td>
            </tr>
          </tbody>
        </table>

        <script>
          (function () {
            const filtroGrado  = document.getElementById('filtroGrado');
            const filtroGrupo  = document.getElementById('filtroGrupo');
            const filtroNombre = document.getElementById('filtroNombre');
            const filas        = document.querySelectorAll('#tablaAlumnos tbody tr[data-nombre]');
            const filaSin      = document.getElementById('filaSinResultados');

            function aplicarFiltros() {
              const grado  = filtroGrado.value;
              const grupo  = filtroGrupo.value;
              const nombre = filtroNombre.value.trim().toLowerCase();
              let visibles = 0;

              filas.forEach(function (fila) {
                const ok = (grado  === '' || fila.dataset.grado  === grado)  &&
                          (grupo  === '' || fila.dataset.grupo  === grupo)  &&
                          (nombre === '' || fila.dataset.nombre.includes(nombre));
                fila.style.display = ok ? '' : 'none';
                if (ok) visibles++;
              });

              filaSin.style.display = visibles === 0 ? '' : 'none';
            }

            filtroGrado.addEventListener('change', aplicarFiltros);
            filtroGrupo.addEventListener('change', aplicarFiltros);
            filtroNombre.addEventListener('input',  aplicarFiltros);
            
          })();

        </script>

        <?php else: ?>
          <div class="empty-state"><p>No hay alumnos registrados aún.</p></div>
        <?php endif; ?>

      </main>
    </div>

    <!-- ── Modal: elegir opción ── -->
    <div class="modal-foto-overlay" id="modalFoto">
      <div class="modal-foto-caja">
        <div class="modal-foto-titulo">📷 Subir foto del alumno</div>
        <div class="modal-foto-alumno" id="modalFotoNombre"></div>
        <div class="modal-foto-opciones">
          <button class="btn-opcion btn-opcion-archivo">
            📁<span>Desde archivos</span>
            <input type="file" accept=".jpg,.jpeg,.png" onchange="seleccionarArchivo(this)">
          </button>
          <button class="btn-opcion btn-opcion-camara" onclick="abrirCamara()">
            📸<span>Tomar foto</span>
          </button>
        </div>
        <button class="btn-cancelar-modal" onclick="cerrarModalFoto()">Cancelar</button>
      </div>
    </div>

    <!-- ── Modal: cámara ── -->
    <div class="modal-camara-overlay" id="modalCamara">
      <div class="modal-camara-caja">
        <div class="modal-camara-titulo" id="modalCamaraNombre"></div>
        <video id="videoStream" autoplay playsinline></video>
        <canvas id="canvasCaptura"></canvas>
        <div class="camara-botones">
          <button class="btn-capturar" onclick="capturarFoto()">📸 Capturar</button>
          <button class="btn-cerrar-camara" onclick="cerrarCamara()">✕ Cancelar</button>
        </div>
      </div>
    </div>

    <script>
      let alumnoIdActual   = null;
      let streamActual     = null;

      function abrirModalFoto(id, nombre) {
        alumnoIdActual = id;
        document.getElementById('modalFotoNombre').textContent = nombre;
        document.getElementById('modalFoto').classList.add('visible');
      }

      function cerrarModalFoto() {
        document.getElementById('modalFoto').classList.remove('visible');
      }

      function seleccionarArchivo(input) {
        if (!input.files || !input.files[0]) return;
        const fileInput = document.getElementById('archivo-' + alumnoIdActual);
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(input.files[0]);
        fileInput.files = dataTransfer.files;
        cerrarModalFoto();
        document.getElementById('form-' + alumnoIdActual).submit();
      }

      function abrirCamara() {
        cerrarModalFoto();
        document.getElementById('modalCamaraNombre').textContent =
          document.getElementById('modalFotoNombre').textContent;
        document.getElementById('modalCamara').classList.add('visible');

        navigator.mediaDevices.getUserMedia({ video: true })
          .then(function(stream) {
            streamActual = stream;
            document.getElementById('videoStream').srcObject = stream;
          })
          .catch(function() {
            alert('No se pudo acceder a la cámara. Verifica que esté conectada y que el navegador tenga permiso.');
            cerrarCamara();
          });
      }

      function cerrarCamara() {
        if (streamActual) {
          streamActual.getTracks().forEach(function(track) { track.stop(); });
          streamActual = null;
        }
        document.getElementById('videoStream').srcObject = null;
        document.getElementById('modalCamara').classList.remove('visible');
      }

      function capturarFoto() {
        const video  = document.getElementById('videoStream');
        const canvas = document.getElementById('canvasCaptura');
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        canvas.toBlob(function(blob) {
          const archivo = new File([blob], 'foto.jpg', { type: 'image/jpeg' });
          const fileInput = document.getElementById('archivo-' + alumnoIdActual);
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(archivo);
          fileInput.files = dataTransfer.files;
          cerrarCamara();
          document.getElementById('form-' + alumnoIdActual).submit();
        }, 'image/jpeg', 0.92);
      }
    </script>

  </body>
</html>