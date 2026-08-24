<?php
/**
 * MENU LATERAL COMPARTIDO
 * Sidebar reutilizable del panel de administrador.
 *
 * Usa las clases de css/panel.css (.sidebar, .nav-item, .nav-grupo-btn,
 * .nav-subgrupo, .nav-subitem, .activo) — las mismas que ya traían
 * dashboard.php, alumnos.php y tablas.php antes de esta refactorización.
 *
 * Variables opcionales que la página puede definir ANTES del include
 * para resaltar el ítem/sub-ítem activo correcto:
 *
 *   $paginaActual        = 'dashboard' | 'alumnos' | 'tablas' | 'configuracion'
 *   $vistaActual          (solo en tablas.php) = 'grados_grupos' | 'tutores_registrados'
 *                                                | 'tutores_pendientes' | 'registros'
 *   $mostrandoFormulario  (solo en alumnos.php) = true|false
 *                          true  -> resalta "➕ Registrar / editar"
 *                          false -> resalta "Ver alumnos"
 */
$paginaActual = $paginaActual ?? '';
$vistaActual = $vistaActual ?? '';
$mostrandoFormulario = $mostrandoFormulario ?? false;
?>
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
      <a href="alumnos.php" class="nav-subitem <?= ($paginaActual === 'alumnos' && !$mostrandoFormulario) ? 'activo' : '' ?>">Ver alumnos</a>
      <a href="alumnos.php?accion=nuevo" class="nav-subitem <?= ($paginaActual === 'alumnos' && $mostrandoFormulario) ? 'activo' : '' ?>">➕ Registrar / editar</a>
    </div>

    <div class="nav-grupo-btn"><span class="nav-icono">📊</span> Tablas</div>
    <div class="nav-subgrupo abierto">
      <a href="tablas.php?vista=grados_grupos" class="nav-subitem <?= ($paginaActual === 'tablas' && $vistaActual === 'grados_grupos') ? 'activo' : '' ?>">📚 Grados y grupos</a>
      <a href="tablas.php?vista=tutores_registrados" class="nav-subitem <?= ($paginaActual === 'tablas' && $vistaActual === 'tutores_registrados') ? 'activo' : '' ?>">✅ Tutores registrados</a>
      <a href="tablas.php?vista=tutores_pendientes" class="nav-subitem <?= ($paginaActual === 'tablas' && $vistaActual === 'tutores_pendientes') ? 'activo' : '' ?>">⏳ Tutores pendientes</a>
      <a href="tablas.php?vista=registros" class="nav-subitem <?= ($paginaActual === 'tablas' && $vistaActual === 'registros') ? 'activo' : '' ?>">🕐 Registros</a>
    </div>

    <a href="configuracion.php" class="nav-item <?= $paginaActual === 'configuracion' ? 'activo' : '' ?>">
      <span class="nav-icono">⚙️</span> Configuración
    </a>
  </nav>
</aside>
