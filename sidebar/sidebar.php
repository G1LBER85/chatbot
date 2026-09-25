<?php
require_once dirname(__DIR__) . '/panel/login/php/inicio/inactividad.php';

// Conexión a la base de datos
$rutaConexion = dirname(__DIR__) . '/conexion.php';
if (file_exists($rutaConexion)) {
    require_once $rutaConexion;
}

// 1. Detectar identificador de sesión del usuario
$identificadorSesion = '';

if (isset($_SESSION['correo'])) {
    if (is_array($_SESSION['correo'])) {
        $identificadorSesion = $_SESSION['correo']['correo'] ?? $_SESSION['correo']['email'] ?? $_SESSION['correo']['usuario'] ?? '';
    } else {
        $identificadorSesion = $_SESSION['correo'];
    }
}

if (empty($identificadorSesion) && isset($_SESSION['usuario'])) {
    if (is_array($_SESSION['usuario'])) {
        $identificadorSesion = $_SESSION['usuario']['usuario'] ?? $_SESSION['usuario']['correo'] ?? '';
    } else {
        $identificadorSesion = $_SESSION['usuario'];
    }
}

if (empty($identificadorSesion) && isset($_SESSION['id'])) {
    $identificadorSesion = $_SESSION['id'];
}

$nombreUsuario = '';
$rolUsuario    = 'Administrador';

// 2. Consulta a la tabla 'usuarios' para obtener el campo 'nombre_completo'
if (!empty($identificadorSesion) && isset($conexion)) {
    $stmt = $conexion->prepare("SELECT nombre_completo FROM usuarios WHERE correo = ? OR usuario = ? OR id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("sss", $identificadorSesion, $identificadorSesion, $identificadorSesion);
        $stmt->execute();
        $resultado = $stmt->get_result();
        if ($fila = $resultado->fetch_assoc()) {
            $nombreUsuario = $fila['nombre_completo'];
        }
        $stmt->close();
    }
}

// Respaldo de seguridad si no devuelve resultado de la BD
if (empty($nombreUsuario)) {
    if (isset($_SESSION['correo']) && is_array($_SESSION['correo']) && !empty($_SESSION['correo']['nombre_completo'])) {
        $nombreUsuario = $_SESSION['correo']['nombre_completo'];
    } else {
        $nombreUsuario = 'Usuario';
    }
}

$permisosUsuario = $_SESSION['correo']['permisos'] ?? 'todo';
$listaPermisos   = explode(',', $permisosUsuario);

function tienePermiso($modulo, $listaPermisos, $permisosUsuario) {
    if ($permisosUsuario === 'todo' || in_array('todo', $listaPermisos, true)) {
        return true;
    }
    return in_array($modulo, $listaPermisos, true);
}

$paginaActual        = $paginaActual ?? '';
$vistaActual         = $vistaActual ?? '';
$mostrandoFormulario = $mostrandoFormulario ?? false;
?>

<!-- Estilos CSS del Sidebar -->
<style>
.sidebar {
  width: 250px;
  height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #2b3d4f; /* Color azul original */
  color: #d1dbe5;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  box-sizing: border-box;
}

.sidebar * {
  box-sizing: border-box;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 18px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.brand-icon {
  font-size: 1.6rem;
}

.brand-info strong {
  display: block;
  color: #ffffff;
  font-size: 1.1rem;
  font-weight: 700;
  line-height: 1.2;
}

.brand-info small {
  color: #8fa3b8;
  font-size: 0.78rem;
}

.sidebar-nav {
  flex: 1;
  padding: 15px 10px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  color: #d1dbe5;
  text-decoration: none;
  border-radius: 6px;
  font-size: 0.92rem;
  font-weight: 600;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.nav-link:hover, .nav-link.activo {
  background-color: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}

.nav-dropdown-btn {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  background: transparent;
  border: none;
  color: #d1dbe5;
  font-size: 0.92rem;
  font-weight: 600;
  border-radius: 6px;
  cursor: pointer;
  text-align: left;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.nav-dropdown-btn:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}

.dropdown-title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.arrow {
  font-size: 1.1rem;
  line-height: 1;
  transition: transform 0.25s ease;
}

.nav-sub-list {
  display: none;
  flex-direction: column;
  margin-left: 15px;
  padding-left: 10px;
  margin-top: 4px;
  margin-bottom: 4px;
  gap: 2px;
}

.sub-link {
  padding: 8px 12px;
  color: #9cb0c5;
  text-decoration: none;
  font-size: 0.88rem;
  border-radius: 6px;
  transition: color 0.2s ease, background-color 0.2s ease;
}

.sub-link:hover, .sub-link.activo {
  color: #ffffff;
  background-color: rgba(255, 255, 255, 0.08);
}

.nav-dropdown.open .nav-sub-list {
  display: flex;
}

.nav-dropdown.open .arrow {
  transform: rotate(90deg);
}

/* Tarjeta Inferior de Usuario */
.sidebar-user-footer {
  display: flex;
  align-items: center;
  padding: 14px 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background-color: #223140;
  gap: 12px;
}

.user-avatar-wrapper {
  position: relative;
  width: 38px;
  height: 38px;
  flex-shrink: 0;
}

.user-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background-color: #3b4e63;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.status-indicator {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 10px;
  height: 10px;
  background-color: #2ecc71;
  border: 2px solid #223140;
  border-radius: 50%;
}

.user-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.user-name {
  color: #ffffff;
  font-size: 0.9rem;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-transform: capitalize;
}

.user-role {
  color: #8fa3b8;
  font-size: 0.75rem;
}

.logout-btn {
  color: #8fa3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  border-radius: 6px;
  text-decoration: none;
  flex-shrink: 0;
  transition: color 0.2s ease, background-color 0.2s ease;
}

.logout-btn:hover {
  color: #e74c3c;
  background-color: rgba(231, 76, 60, 0.12);
}

.logout-icon {
  width: 20px !important;
  height: 20px !important;
}
</style>

<aside class="sidebar">
  <!-- Encabezado / Logo -->
  <div class="sidebar-brand">
    <span class="brand-icon">🏫</span>
    <div class="brand-info">
      <strong>ChecaBot</strong>
      <small>Panel de Administrador</small>
    </div>
  </div>

  <!-- Menú de Navegación -->
  <nav class="sidebar-nav">
    <?php if (tienePermiso('dashboard', $listaPermisos, $permisosUsuario)): ?>
      <a href="dashboard.php" class="nav-link <?= $paginaActual === 'dashboard' ? 'activo' : '' ?>">
        <span class="nav-icon">🏠</span> <span>Dashboard</span>
      </a>
    <?php endif; ?>

    <?php if (tienePermiso('alumnos', $listaPermisos, $permisosUsuario)): ?>
      <div class="nav-dropdown <?= $paginaActual === 'alumnos' ? 'open' : '' ?>">
        <button type="button" class="nav-dropdown-btn">
          <span class="dropdown-title"><span class="nav-icon">🎓</span> Alumnos</span>
          <span class="arrow">›</span>
        </button>
        <div class="nav-sub-list">
          <a href="alumnos.php" class="sub-link <?= ($paginaActual === 'alumnos' && !$mostrandoFormulario) ? 'activo' : '' ?>">
            Ver alumnos
          </a>
          <a href="alumnos.php?accion=nuevo" class="sub-link <?= ($paginaActual === 'alumnos' && $mostrandoFormulario) ? 'activo' : '' ?>">
            ➕ Registrar / editar
          </a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (tienePermiso('tablas', $listaPermisos, $permisosUsuario)): ?>
      <div class="nav-dropdown <?= $paginaActual === 'tablas' ? 'open' : '' ?>">
        <button type="button" class="nav-dropdown-btn">
          <span class="dropdown-title"><span class="nav-icon">📊</span> Tablas</span>
          <span class="arrow">›</span>
        </button>
        <div class="nav-sub-list">
          <a href="tablas.php?vista=grados_grupos" class="sub-link <?= ($paginaActual === 'tablas' && $vistaActual === 'grados_grupos') ? 'activo' : '' ?>">
            📚 Grados y grupos
          </a>
          <a href="tablas.php?vista=tutores_registrados" class="sub-link <?= ($paginaActual === 'tablas' && $vistaActual === 'tutores_registrados') ? 'activo' : '' ?>">
            ✅ Tutores registrados
          </a>
          <a href="tablas.php?vista=tutores_pendientes" class="sub-link <?= ($paginaActual === 'tablas' && $vistaActual === 'tutores_pendientes') ? 'activo' : '' ?>">
            ⏳ Tutores pendientes
          </a>
          <a href="tablas.php?vista=registros" class="sub-link <?= ($paginaActual === 'tablas' && $vistaActual === 'registros') ? 'activo' : '' ?>">
            🕐 Registros
          </a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (tienePermiso('administradores', $listaPermisos, $permisosUsuario)): ?>
      <div class="nav-dropdown <?= $paginaActual === 'administradores' ? 'open' : '' ?>">
        <button type="button" class="nav-dropdown-btn">
          <span class="dropdown-title"><span class="nav-icon">👤</span> Administradores</span>
          <span class="arrow">›</span>
        </button>
        <div class="nav-sub-list">
          <a href="administradores.php" class="sub-link <?= ($paginaActual === 'administradores' && !$mostrandoFormulario) ? 'activo' : '' ?>">
            Ver administradores
          </a>
          <a href="administradores.php?accion=nuevo" class="sub-link <?= ($paginaActual === 'administradores' && $mostrandoFormulario) ? 'activo' : '' ?>">
            ➕ Registrar / editar
          </a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (tienePermiso('telegram', $listaPermisos, $permisosUsuario)): ?>
      <a href="telegram_respuestas.php" class="nav-link <?= $paginaActual === 'telegram_respuestas' ? 'activo' : '' ?>">
        <span class="nav-icon">🤖</span> <span>Respuestas Telegram</span>
      </a>
    <?php endif; ?>

    <?php if (tienePermiso('importar', $listaPermisos, $permisosUsuario)): ?>
      <a href="importar_alumnos.php" class="nav-link <?= $paginaActual === 'importar_alumnos' ? 'activo' : '' ?>">
        <span class="nav-icon">📋</span> <span>Importar alumnos</span>
      </a>
    <?php endif; ?>

    <?php if (tienePermiso('imagenes', $listaPermisos, $permisosUsuario)): ?>
      <a href="cargar_imagenes.php" class="nav-link <?= $paginaActual === 'cargar_imagenes' ? 'activo' : '' ?>">
        <span class="nav-icon">🖼️</span> <span>Cargar imágenes</span>
      </a>
    <?php endif; ?>
  </nav>

  <!-- Tarjeta del Usuario + Cerrar Sesión -->
  <div class="sidebar-user-footer">
    <div class="user-avatar-wrapper">
      <div class="user-avatar">👤</div>
      <span class="status-indicator"></span>
    </div>
    <div class="user-details">
      <span class="user-name"><?= htmlspecialchars($nombreUsuario) ?></span>
      <span class="user-role"><?= htmlspecialchars($rolUsuario) ?></span>
    </div>
    <a href="login/php/inicio/cerrar_sesion.php" class="logout-btn" title="Cerrar sesión">
      <svg class="logout-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
    </a>
  </div>
</aside>

<!-- Script para la animación y cierre automático de otros submenús -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const dropdowns = document.querySelectorAll('.nav-dropdown');

  dropdowns.forEach(dropdown => {
    const btn = dropdown.querySelector('.nav-dropdown-btn');
    
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      
      // Cerrar cualquier otro desplegable que esté abierto
      dropdowns.forEach(otherDropdown => {
        if (otherDropdown !== dropdown) {
          otherDropdown.classList.remove('open');
        }
      });

      // Alternar el estado del desplegable actual
      dropdown.classList.toggle('open');
    });
  });
});
</script>