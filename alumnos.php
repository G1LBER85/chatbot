<?php
require 'includes/conexion.php';

$paginaActual = 'alumnos';
$accion = $_GET['accion'] ?? '';
$mensaje = '';
$tipo_mensaje = '';

$extensionesPermitidas = ['jpg', 'jpeg', 'png'];

// [FUNCION: subirFotoAlumno] ─────────────────────────────────
function subirFotoAlumno($curp, $extensionesPermitidas)
{
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas, true)) {
        return null;
    }

    $carpetaFotos = __DIR__ . '/fotos';
    if (!is_dir($carpetaFotos)) {
        mkdir($carpetaFotos, 0755, true);
    }

    $nombreArchivo = strtoupper($curp) . '.' . $extension;
    $rutaDestino = $carpetaFotos . '/' . $nombreArchivo;

    if (move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino)) {
        return 'fotos/' . $nombreArchivo;
    }

    return null;
}

// [PROCESO: AGREGAR ALUMNO] ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre']);
    $grado = trim($_POST['grado']);
    $grupo = trim($_POST['grupo']);
    $curp = strtoupper(trim($_POST['curp']));

    if ($nombre && $grado && $grupo && $curp) {
        $stmtDup = $conn->prepare("SELECT id FROM alumnos WHERE CURP = ? LIMIT 1");
        $stmtDup->bind_param("s", $curp);
        $stmtDup->execute();
        $existente = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();

        if ($existente) {
            $mensaje = "⚠️ Ya existe un alumno registrado con esa CURP";
            $tipo_mensaje = "warning";
            $accion = 'nuevo';
        } else {
            $foto = subirFotoAlumno($curp, $extensionesPermitidas);

            $stmt = $conn->prepare("
                INSERT INTO alumnos (nombre, grado, grupo, CURP, foto)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $nombre, $grado, $grupo, $curp, $foto);

            if ($stmt->execute()) {
                header("Location: alumnos.php?agregado=1");
                exit;
            } else {
                $mensaje = "❌ Error al agregar alumno";
                $tipo_mensaje = "error";
                $accion = 'nuevo';
            }
        }
    } else {
        $mensaje = "⚠️ Completa todos los campos obligatorios (nombre, grado, grupo y CURP)";
        $tipo_mensaje = "warning";
        $accion = 'nuevo';
    }
}

// [PROCESO: EDITAR ALUMNO] ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $grado = trim($_POST['grado']);
    $grupo = trim($_POST['grupo']);
    $curp = strtoupper(trim($_POST['curp']));

    if ($nombre && $grado && $grupo && $curp) {
        $stmtDup = $conn->prepare("SELECT id FROM alumnos WHERE CURP = ? AND id != ? LIMIT 1");
        $stmtDup->bind_param("si", $curp, $id);
        $stmtDup->execute();
        $existente = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();

        if ($existente) {
            $mensaje = "⚠️ Esa CURP ya pertenece a otro alumno";
            $tipo_mensaje = "warning";
            $accion = 'editar';
            $_GET['id'] = $id;
        } else {
            $stmtActual = $conn->prepare("SELECT foto FROM alumnos WHERE id = ?");
            $stmtActual->bind_param("i", $id);
            $stmtActual->execute();
            $actual = $stmtActual->get_result()->fetch_assoc();
            $stmtActual->close();

            $fotoNueva = subirFotoAlumno($curp, $extensionesPermitidas);
            $foto = $fotoNueva ?? ($actual['foto'] ?? null);

            $stmt = $conn->prepare("
                UPDATE alumnos
                SET nombre = ?, grado = ?, grupo = ?, CURP = ?, foto = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssi", $nombre, $grado, $grupo, $curp, $foto, $id);

            if ($stmt->execute()) {
                header("Location: alumnos.php?editado=1");
                exit;
            } else {
                $mensaje = "❌ Error al actualizar";
                $tipo_mensaje = "error";
                $accion = 'editar';
                $_GET['id'] = $id;
            }
        }
    } else {
        $mensaje = "⚠️ Completa todos los campos obligatorios";
        $tipo_mensaje = "warning";
        $accion = 'editar';
        $_GET['id'] = $id;
    }
}

// [PROCESO: ELIMINAR ALUMNO] ─────────────────────────────────
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);

    $stmt = $conn->prepare("DELETE FROM registros WHERE alumno_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt2 = $conn->prepare("DELETE FROM alumnos WHERE id = ?");
    $stmt2->bind_param("i", $id);

    if ($stmt2->execute()) {
        header("Location: alumnos.php?eliminado=1");
        exit;
    }
}
if (isset($_GET['eliminado'])) { $mensaje = "✅ Alumno eliminado correctamente"; $tipo_mensaje = "success"; }
if (isset($_GET['agregado'])) { $mensaje = "✅ Alumno agregado correctamente"; $tipo_mensaje = "success"; }
if (isset($_GET['editado']))  { $mensaje = "✅ Alumno actualizado correctamente"; $tipo_mensaje = "success"; }

// [CONSULTA: ALUMNO A EDITAR] ────────────────────────────────
$alumno_edit = null;
if ($accion === 'editar' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM alumnos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $alumno_edit = $stmt->get_result()->fetch_assoc();
}

// [CONSULTA: LISTA DE ALUMNOS] ───────────────────────────────
// Solo se necesita cuando NO estamos en modo nuevo/editar
$alumnos = null;
if ($accion !== 'nuevo' && $accion !== 'editar') {
    $alumnos = $conn->query("SELECT * FROM alumnos ORDER BY id DESC");
}

$mostrandoFormulario = ($accion === 'nuevo' || $accion === 'editar');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Alumnos — Panel de Administrador ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
</head>
<body>

<div class="layout">

  <?php include 'includes/sidebar.php'; ?>

  <main class="contenido">

    <?php if ($mensaje): ?>
      <div class="mensaje <?= $tipo_mensaje ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if (!$mostrandoFormulario): ?>
      <!-- [VISTA: LISTA DE ALUMNOS] -->
      <div class="panel-header">
        <div>
          <h1>Alumnos Registrados</h1>
          <p>Consulta, edita o elimina alumnos</p>
        </div>
      </div>

      <?php if ($alumnos->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Foto</th>
              <th>Nombre</th>
              <th>Grado/Grupo</th>
              <th>CURP</th>
              <th>Tutor</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($alumno = $alumnos->fetch_assoc()): ?>
            <tr>
              <td>
                <?php if (!empty($alumno['foto'])): ?>
                  <img class="foto-mini" src="<?= htmlspecialchars(str_replace('\\', '/', $alumno['foto'])) ?>" alt="">
                <?php else: ?>
                  <span class="foto-mini" style="display:inline-flex;align-items:center;justify-content:center;">🧑</span>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($alumno['nombre']) ?></strong></td>
              <td><?= htmlspecialchars($alumno['grado']) ?><?= $alumno['grupo'] ? ' ' . htmlspecialchars($alumno['grupo']) : '' ?></td>
              <td><?= $alumno['CURP'] ? htmlspecialchars($alumno['CURP']) : '<span style="color:#bbb;">Sin capturar</span>' ?></td>
              <td>
                <?php if ($alumno['tutor_chat_id']): ?>
                  <span class="badge badge-success">✓ Registrado</span>
                <?php else: ?>
                  <span class="badge badge-warning">⏳ Pendiente</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($alumno['activo']): ?>
                  <span class="badge badge-success">Activo</span>
                <?php else: ?>
                  <span class="badge badge-danger">Inactivo</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex; gap:8px;">
                  <a href="alumnos.php?accion=editar&id=<?= $alumno['id'] ?>" class="btn btn-primary btn-small">✏️</a>
                  <a href="alumnos.php?eliminar=<?= $alumno['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('¿Eliminar a <?= addslashes($alumno['nombre']) ?>?')">🗑️</a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><p>No hay alumnos registrados aún.</p></div>
      <?php endif; ?>

    <?php else: ?>
      <!-- [VISTA: FORMULARIO NUEVO/EDITAR] -->
      <div class="panel-header">
        <div>
          <h1><?= $alumno_edit ? "✏️ Editar Alumno" : "➕ Agregar Nuevo Alumno" ?></h1>
          <p>Todos los campos son obligatorios</p>
        </div>
      </div>

      <div class="form-box">
        <?php if ($alumno_edit && !empty($alumno_edit['foto'])): ?>
          <img class="foto-preview" src="<?= htmlspecialchars(str_replace('\\', '/', $alumno_edit['foto'])) ?>" alt="Foto actual">
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <?php if ($alumno_edit): ?>
            <input type="hidden" name="id" value="<?= $alumno_edit['id'] ?>">
          <?php endif; ?>

          <div class="form-row">
            <div class="form-group">
              <label>Nombre Completo *</label>
              <input type="text" name="nombre" required value="<?= htmlspecialchars($alumno_edit['nombre'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>CURP *</label>
              <input type="text" name="curp" maxlength="18" required style="text-transform:uppercase" value="<?= htmlspecialchars($alumno_edit['CURP'] ?? '') ?>">
              <small>Identificador usado para el código QR</small>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Grado *</label>
              <input type="text" name="grado" placeholder="Ejemplo: 1, 2, 3" required value="<?= htmlspecialchars($alumno_edit['grado'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Grupo *</label>
              <input type="text" name="grupo" maxlength="1" placeholder="Ejemplo: A" required value="<?= htmlspecialchars($alumno_edit['grupo'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Fotografía <?= $alumno_edit ? '(déjalo vacío para conservar la actual)' : '' ?></label>
            <input type="file" name="foto" accept=".jpg,.jpeg,.png">
          </div>

          <div style="display: flex; gap: 10px;">
            <button type="submit" name="<?= $alumno_edit ? 'editar' : 'agregar' ?>" value="1" class="btn btn-primary">
              <?= $alumno_edit ? "💾 Guardar Cambios" : "➕ Agregar Alumno" ?>
            </button>
            <a href="alumnos.php" class="btn btn-secondary">← Cancelar</a>
          </div>
        </form>
      </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>
