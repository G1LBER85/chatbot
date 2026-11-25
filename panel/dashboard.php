<?php

require_once __DIR__ . '/login/php/inicio/auth.php';
/*
http://localhost/prueba/chatbot/panel/dashboard.php
*/
require_once __DIR__ . '/../conexion.php';

$paginaActual = 'dashboard';

// [CONSULTA: DASHBOARD] — Alimenta las 4 tarjetas de abajo
$totalAlumnos = $conn->query("SELECT COUNT(*) AS n FROM alumnos WHERE activo = 1")->fetch_assoc()['n'];
$totalConTutor = $conn->query("SELECT COUNT(*) AS n FROM alumnos WHERE tutor_chat_id IS NOT NULL")->fetch_assoc()['n'];
$totalSinTutor = $conn->query("SELECT COUNT(*) AS n FROM alumnos WHERE tutor_chat_id IS NULL")->fetch_assoc()['n'];
$totalRegistrosHoy = $conn->query("SELECT COUNT(*) AS n FROM registros WHERE DATE(fecha_hora) = CURDATE()")->fetch_assoc()['n'];

// Estadísticas
$mov = $conn->query("
    SELECT
        SUM(tipo = 'entrada') AS entradas,
        SUM(tipo = 'salida')  AS salidas
    FROM registros
    WHERE DATE(fecha_hora) = CURDATE()
")->fetch_assoc();

$entradasHoy = (int)($mov['entradas'] ?? 0);
$salidasHoy  = (int)($mov['salidas'] ?? 0);
$enPlantel   = max(0, $entradasHoy - $salidasHoy);
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

<?php include __DIR__ . '/../sidebar/sidebar.php'; ?>

  <main class="contenido">
    <!-- Encabezado con clase modificada -->
    <div class="panel-header">
      <div>
        <h1>Dashboard</h1>
        <p>Resumen general del sistema</p>
      </div>
    </div>

    <h2 style="text-align: center;">Alumnos</h2>
    <div class="stat-grid">
      <div class="stat-card">
        <div class="valor"><?= $totalAlumnos ?></div>
        <div class="etiqueta">Total alumnos</div>
      </div>
      <div class="stat-card">
        <div class="valor"><?= $entradasHoy ?></div>
        <div class="etiqueta">Entradas hoy</div>
      </div>
      <div class="stat-card">
        <div class="valor"><?= $salidasHoy ?></div>
        <div class="etiqueta">Salidas hoy</div>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="valor"><?= $enPlantel ?></div>
        <div class="etiqueta">En el plantel</div>
      </div>
    </div>

    <h2 style="text-align: center;">Tutores</h2>
    <div class="stat-grid">
      <div class="stat-card">
        <div class="valor"><?= $totalConTutor ?></div>
        <div class="etiqueta">Tutores registrados</div>
      </div>
      <div class="stat-card">
        <div class="valor"><?= $totalSinTutor ?></div>
        <div class="etiqueta">Tutores pendientes</div>
      </div>
    </div>
  </main>

</div>

</body>
</html>