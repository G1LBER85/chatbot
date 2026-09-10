<?php

require_once dirname(__DIR__) . '/panel/login/php/inicio/inactividad.php';


// Obtener los permisos guardados en la sesión (por defecto vacíos o "todo")
$permisosUsuario = $_SESSION['correo']['permisos'] ?? 'todo';
$listaPermisos = explode(',', $permisosUsuario);

// Función auxiliar para saber si el usuario tiene acceso a un módulo
function tienePermiso($modulo, $listaPermisos, $permisosUsuario) {
    if ($permisosUsuario === 'todo' || in_array('todo', $listaPermisos, true)) {
        return true;
    }
    return in_array($modulo, $listaPermisos, true);
}




/**
 * Sidebar reutilizable del panel de administrador.
 * Vive en /sidebar/sidebar.php (raíz del proyecto).
 *
 * Se incluye desde archivos dentro de panel/ así:
 *   include '../sidebar/sidebar.php';
 *
 * Usa las clases de panel/css/panel.css (.sidebar, .nav-item, .nav-grupo-btn,
 * .nav-subgrupo, .nav-subitem, .activo).
 *
 * Variables opcionales que la página debe definir ANTES del include
 * para resaltar el ítem/sub-ítem activo correcto:
 *
 *   $paginaActual        = 'dashboard' | 'alumnos' | 'tablas' | 'configuracion'
 *   $vistaActual          (solo en tablas.php) = 'grados_grupos' | 'tutores_registrados'
 *                                                | 'tutores_pendientes' | 'registros'
 *   $mostrandoFormulario  (solo en alumnos.php) = true|false
 *                          true  -> resalta "➕ Registrar / editar"
 *                          false -> resalta "Ver alumnos"
 *
 * Nota: los href del menú (dashboard.php, alumnos.php, etc.) son relativos
 * a la URL de la página que los muestra (panel/dashboard.php, panel/alumnos.php...),
 * así que no necesitan prefijo aunque este archivo físico viva fuera de panel/.
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
    <?php if (tienePermiso('dashboard', $listaPermisos, $permisosUsuario)): ?>
      <a href="dashboard.php" class="nav-item <?= $paginaActual === 'dashboard' ? 'activo' : '' ?>">
        <span class="nav-icono">🏠</span> Dashboard
      </a>
    <?php endif; ?>

    <?php if (tienePermiso('alumnos', $listaPermisos, $permisosUsuario)): ?>
      <div class="nav-grupo-btn"><span class="nav-icono">🎓</span> Alumnos</div>
      <div class="nav-subgrupo abierto">
        <a href="alumnos.php" class="nav-subitem <?= ($paginaActual === 'alumnos' && !$mostrandoFormulario) ? 'activo' : '' ?>">Ver alumnos</a>
        <a href="alumnos.php?accion=nuevo" class="nav-subitem <?= ($paginaActual === 'alumnos' && $mostrandoFormulario) ? 'activo' : '' ?>">➕ Registrar / editar</a>
      </div>
    <?php endif; ?>

    <?php if (tienePermiso('tablas', $listaPermisos, $permisosUsuario)): ?>
      <div class="nav-grupo-btn"><span class="nav-icono">📊</span> Tablas</div>
      <div class="nav-subgrupo abierto">
        <a href="tablas.php?vista=grados_grupos" class="nav-subitem <?= ($paginaActual === 'tablas' && $vistaActual === 'grados_grupos') ? 'activo' : '' ?>">📚 Grados y grupos</a>
        <a href="tablas.php?vista=tutores_registrados" class="nav-subitem <?= ($paginaActual === 'tablas' && $vistaActual === 'tutores_registrados') ? 'activo' : '' ?>">✅ Tutores registrados</a>
        <a href="tablas.php?vista=tutores_pendientes" class="nav-subitem <?= ($paginaActual === 'tablas' && $vistaActual === 'tutores_pendientes') ? 'activo' : '' ?>">⏳ Tutores pendientes</a>
        <a href="tablas.php?vista=registros" class="nav-subitem <?= ($paginaActual === 'tablas' && $vistaActual === 'registros') ? 'activo' : '' ?>">🕐 Registros</a>
      </div>
    <?php endif; ?>

    <?php if (tienePermiso('administradores', $listaPermisos, $permisosUsuario)): ?>
      <div class="nav-grupo-btn"><span class="nav-icono">👤</span> Administradores</div>
      <div class="nav-subgrupo abierto">
        <a href="administradores.php" class="nav-subitem <?= ($paginaActual === 'administradores' && !$mostrandoFormulario) ? 'activo' : '' ?>">
          Ver administradores
        </a>
        <a href="administradores.php?accion=nuevo" class="nav-subitem <?= ($paginaActual === 'administradores' && $mostrandoFormulario) ? 'activo' : '' ?>">
          ➕ Registrar / editar
        </a>
      </div>
    <?php endif; ?>

    <?php if (tienePermiso('telegram', $listaPermisos, $permisosUsuario)): ?>
      <a href="telegram_respuestas.php" class="nav-item <?= $paginaActual === 'telegram_respuestas' ? 'activo' : '' ?>">
        <span class="nav-icono">🤖</span> Respuestas Telegram
      </a>
    <?php endif; ?>

    <?php if (tienePermiso('importar', $listaPermisos, $permisosUsuario)): ?>
      <a href="importar_alumnos.php" class="nav-item <?= $paginaActual === 'importar_alumnos' ? 'activo' : '' ?>">
        <span class="nav-icono">📋</span> Importar alumnos
      </a>
    <?php endif; ?>

    <?php if (tienePermiso('imagenes', $listaPermisos, $permisosUsuario)): ?>
      <a href="cargar_imagenes.php" class="nav-item <?= $paginaActual === 'cargar_imagenes' ? 'activo' : '' ?>">
        <span class="nav-icono">🖼️</span> Cargar imágenes
      </a>
    <?php endif; ?>
  </nav>
</aside>