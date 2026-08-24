<?php
require 'includes/conexion.php';

$paginaActual = 'tablas';

// Whitelist de vistas válidas — evita que alguien meta un
// ?vista=cualquier-cosa e intente incluir un archivo arbitrario.
$vistasValidas = ['grados_grupos', 'tutores_registrados', 'tutores_pendientes', 'registros'];
$vistaActual = $_GET['vista'] ?? 'grados_grupos';
if (!in_array($vistaActual, $vistasValidas, true)) {
    $vistaActual = 'grados_grupos';
}
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

  <?php include 'includes/sidebar.php'; ?>

  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>Tablas de Información</h1>
        <p>Consulta rápida de datos del sistema</p>
      </div>
    </div>

    <div class="sub-tabs">
      <a href="tablas.php?vista=grados_grupos" class="sub-tab <?= $vistaActual === 'grados_grupos' ? 'activa' : '' ?>">📚 Grados y grupos</a>
      <a href="tablas.php?vista=tutores_registrados" class="sub-tab <?= $vistaActual === 'tutores_registrados' ? 'activa' : '' ?>">✅ Tutores registrados</a>
      <a href="tablas.php?vista=tutores_pendientes" class="sub-tab <?= $vistaActual === 'tutores_pendientes' ? 'activa' : '' ?>">⏳ Tutores pendientes</a>
      <a href="tablas.php?vista=registros" class="sub-tab <?= $vistaActual === 'registros' ? 'activa' : '' ?>">🕐 Registros</a>
    </div>

    <?php
    // Incluye SOLO el contenido de la vista elegida.
    // Cada archivo en tablas/ trae su propia consulta SQL y tabla HTML.
    include 'tablas/' . $vistaActual . '.php';
    ?>
  </main>
</div>

</body>
</html>
