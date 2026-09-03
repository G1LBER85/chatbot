<?php
require '../conexion.php';

$paginaActual = 'tablas';
$mensaje = '';
$tipo_mensaje = '';

// Whitelist de vistas válidas — evita que alguien meta un
// ?vista=cualquier-cosa e intente incluir un archivo arbitrario.
$vistasValidas = ['grados_grupos', 'tutores_registrados', 'tutores_pendientes', 'registros'];
$vistaActual = $_GET['vista'] ?? 'grados_grupos';
if (!in_array($vistaActual, $vistasValidas, true)) {
    $vistaActual = 'grados_grupos';
}

// ───────────────────────────────────────────────────────────────
// IMPORTANTE: todo el procesamiento de acciones (eliminar/editar)
// va ANTES de imprimir cualquier HTML, para poder usar header()
// y redirigir sin errores de "headers already sent". Las vistas
// dentro de tablas/*.php solo hacen SELECT y muestran la tabla,
// no procesan formularios ni redirigen.
// ───────────────────────────────────────────────────────────────

// [PROCESO: ELIMINAR ALUMNO DESDE UNA TABLA] ──────────────────────
// Se usa desde las vistas que muestran alumnos (grados_grupos,
// tutores_registrados, tutores_pendientes). Reutiliza la misma
// lógica de borrado de alumnos.php: primero sus registros de
// asistencia (para no dejar registros huérfanos), luego el alumno.
// A diferencia de alumnos.php, aquí regresamos a la MISMA pestaña
// de tablas.php en vez de mandar al usuario a la vista de alumnos.
if (isset($_GET['eliminar_alumno'])) {
    $idAlumno = intval($_GET['eliminar_alumno']);

    $stmtBorraRegistros = $conn->prepare("DELETE FROM registros WHERE alumno_id = ?");
    $stmtBorraRegistros->bind_param("i", $idAlumno);
    $stmtBorraRegistros->execute();
    $stmtBorraRegistros->close();

    $stmtBorraAlumno = $conn->prepare("DELETE FROM alumnos WHERE id = ?");
    $stmtBorraAlumno->bind_param("i", $idAlumno);
    $stmtBorraAlumno->execute();
    $stmtBorraAlumno->close();

    header("Location: tablas.php?vista={$vistaActual}&eliminado=1");
    exit;
}

// [MENSAJES DE CONFIRMACIÓN] ──────────────────────────────────────
if (isset($_GET['eliminado'])) { $mensaje = "✅ Eliminado correctamente"; $tipo_mensaje = "success"; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tablas — Panel de Administrador ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
</head>
<body>

<div class="layout">

  <?php include '../sidebar/sidebar.php'; ?>

  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>Tablas de Información</h1>
        <p>Consulta rápida de datos del sistema</p>
      </div>
    </div>

    <?php if ($mensaje): ?>
      <div class="mensaje <?= $tipo_mensaje ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <div class="sub-tabs">
      <a href="tablas.php?vista=grados_grupos" class="sub-tab <?= $vistaActual === 'grados_grupos' ? 'activa' : '' ?>">📚 Grados y grupos</a>
      <a href="tablas.php?vista=tutores_registrados" class="sub-tab <?= $vistaActual === 'tutores_registrados' ? 'activa' : '' ?>">✅ Tutores registrados</a>
      <a href="tablas.php?vista=tutores_pendientes" class="sub-tab <?= $vistaActual === 'tutores_pendientes' ? 'activa' : '' ?>">⏳ Tutores pendientes</a>
      <a href="tablas.php?vista=registros" class="sub-tab <?= $vistaActual === 'registros' ? 'activa' : '' ?>">🕐 Registros</a>
    </div>

    <?php
    // Incluye SOLO el contenido de la vista elegida.
    // Cada archivo en tablas/ trae su propia consulta SQL y tabla HTML
    // (ya NO procesa formularios ni redirige — eso vive arriba).
    include 'tablas/' . $vistaActual . '.php';
    ?>
  </main>
</div>

</body>
</html>