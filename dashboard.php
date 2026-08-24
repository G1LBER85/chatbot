<?php
require 'includes/conexion.php';

$paginaActual = 'dashboard';

// [CONSULTA: DASHBOARD] — Alimenta las 4 tarjetas de abajo
$totalAlumnos = $conn->query("SELECT COUNT(*) AS n FROM alumnos WHERE activo = 1")->fetch_assoc()['n'];
$totalConTutor = $conn->query("SELECT COUNT(*) AS n FROM alumnos WHERE tutor_chat_id IS NOT NULL")->fetch_assoc()['n'];
$totalSinTutor = $conn->query("SELECT COUNT(*) AS n FROM alumnos WHERE tutor_chat_id IS NULL")->fetch_assoc()['n'];
$totalRegistrosHoy = $conn->query("SELECT COUNT(*) AS n FROM registros WHERE DATE(fecha_hora) = CURDATE()")->fetch_assoc()['n'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Panel de Administrador ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
</head>
<body>

<div class="layout">

  <?php include 'includes/sidebar.php'; ?>

  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>Dashboard</h1>
        <p>Resumen general del sistema</p>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="valor"><?= $totalAlumnos ?></div>
        <div class="etiqueta">Alumnos activos</div>
      </div>
      <div class="stat-card">
        <div class="valor"><?= $totalConTutor ?></div>
        <div class="etiqueta">Tutores registrados</div>
      </div>
      <div class="stat-card">
        <div class="valor"><?= $totalSinTutor ?></div>
        <div class="etiqueta">Tutores pendientes</div>
      </div>
      <div class="stat-card">
        <div class="valor"><?= $totalRegistrosHoy ?></div>
        <div class="etiqueta">Registros hoy</div>
      </div>
    </div>
  </main>

</div>

</body>
</html>
