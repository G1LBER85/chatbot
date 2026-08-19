<?php
require '../conexion.php';

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

  <!-- [SIDEBAR] — este bloque se repite igual en alumnos.php y tablas.php -->
  <aside class="sidebar">
    <div class="sidebar-marca">
      <span class="logo">🏫</span>
      <div>
        <strong>ChecaBot</strong>
        <small>Panel de Administrador</small>
      </div>
    </div>

    <nav class="nav">
      <a href="dashboard.php" class="nav-item <?= $paginaActual === 'dashboard' ? 'activo' : '' ?>">
        <span class="nav-icono">🏠</span> Dashboard
      </a>

      <div class="nav-grupo-btn"><span class="nav-icono">🎓</span> Alumnos</div>
      <div class="nav-subgrupo abierto">
        <a href="alumnos.php" class="nav-subitem">Ver alumnos</a>
        <a href="alumnos.php?accion=nuevo" class="nav-subitem">➕ Registrar / editar</a>
      </div>

      <div class="nav-grupo-btn"><span class="nav-icono">📊</span> Tablas</div>
      <div class="nav-subgrupo abierto">
        <a href="tablas.php?vista=grados_grupos" class="nav-subitem">📚 Grados y grupos</a>
        <a href="tablas.php?vista=tutores_registrados" class="nav-subitem">✅ Tutores registrados</a>
        <a href="tablas.php?vista=tutores_pendientes" class="nav-subitem">⏳ Tutores pendientes</a>
        <a href="tablas.php?vista=registros" class="nav-subitem">🕐 Registros</a>
      </div>

      <a href="configuracion.php" class="nav-item <?= $paginaActual === 'configuracion' ? 'activo' : '' ?>">
        <span class="nav-icono">⚙️</span> Configuración
      </a>
    </nav>
  </aside>

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