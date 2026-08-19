<?php
$paginaActual = 'configuracion';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuración — Panel de Administrador ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
</head>
<body>

<div class="layout">

  <!-- [SIDEBAR] — este bloque se repite igual en dashboard.php, alumnos.php y tablas.php -->
  <aside class="sidebar">
    <div class="sidebar-marca">
      <span class="logo">🏫</span>
      <div>
        <strong>ChecaBot</strong>
        <small>Panel de Administrador</small>
      </div>
    </div>

    <nav class="nav">
      <a href="dashboard.php" class="nav-item">
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

      <a href="configuracion.php" class="nav-item activo">
        <span class="nav-icono">⚙️</span> Configuración
      </a>
    </nav>
  </aside>

  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>Configuración</h1>
        <p>Próximamente</p>
      </div>
    </div>
    <div class="form-box">
      <p style="color: var(--muted);">Esta sección se definirá más adelante.</p>
    </div>
  </main>
</div>

</body>
</html>