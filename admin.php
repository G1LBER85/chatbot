<?php
/*
 * ══════════════════════════════════════════════════════════════
 *  ADMIN.PHP — Panel de Administrador ChecaBot
 * ══════════════════════════════════════════════════════════════
 *  Mapa del archivo (usa Ctrl+F para saltar a cada bloque):
 *
 *  PHP (arriba del todo):
 *    [FUNCION: subirFotoAlumno]     → sube la foto al agregar/editar
 *    [PROCESO: AGREGAR ALUMNO]       → maneja el POST del formulario nuevo
 *    [PROCESO: EDITAR ALUMNO]        → maneja el POST del formulario editar
 *    [PROCESO: ELIMINAR ALUMNO]      → maneja el GET ?eliminar=ID
 *    [CONSULTA: LISTA DE ALUMNOS]    → datos de la tabla "Ver alumnos"
 *    [CONSULTA: ALUMNO A EDITAR]     → trae un alumno cuando entras a editar
 *    [CONSULTA: TABLAS]              → datos de las 4 sub-pestañas de "Tablas"
 *    [CONSULTA: DASHBOARD]           → estadísticas de las tarjetas de inicio
 *
 *  CSS (dentro de <style>):
 *    [CSS: VARIABLES Y BASE]
 *    [CSS: SIDEBAR]
 *    [CSS: CONTENIDO / SECCIONES]
 *    [CSS: MENSAJES DE ÉXITO/ERROR]
 *    [CSS: TARJETAS DE ESTADÍSTICAS]
 *    [CSS: FORMULARIO]
 *    [CSS: BOTONES]
 *    [CSS: TABLAS]
 *    [CSS: RESPONSIVE / MÓVIL]
 *
 *  HTML (dentro de <body>):
 *    [HTML: SIDEBAR]                 → menú lateral izquierdo
 *    [HTML: DASHBOARD]               → tarjetas de estadísticas
 *    [HTML: ALUMNOS - LISTA]         → tabla "Ver alumnos"
 *    [HTML: ALUMNOS - FORMULARIO]    → formulario "Registrar / editar"
 *    [HTML: TABLAS]                  → las 4 sub-pestañas
 *    [HTML: CONFIGURACIÓN]           → sección vacía (placeholder)
 *
 *  JS (dentro de <script>, hasta abajo):
 *    [JS: CAMBIAR DE SECCIÓN]        → mostrar/ocultar Dashboard, Alumnos, etc.
 *    [JS: COLAPSAR SIDEBAR]          → abrir/cerrar grupos Alumnos/Tablas
 *    [JS: SUB-PESTAÑAS DE TABLAS]    → cambiar entre las 4 vistas de Tablas
 *    [JS: ABRIR FORM SI VIENE DE EDITAR]
 * ══════════════════════════════════════════════════════════════
 */

require 'conexion.php';

$accion = $_GET['accion'] ?? '';
$mensaje = '';
$tipo_mensaje = '';

$extensionesPermitidas = ['jpg', 'jpeg', 'png'];

// [FUNCION: subirFotoAlumno] ─────────────────────────────────
/*
 * Sube la foto del alumno (si se envió una) y regresa la ruta
 * relativa para guardar en la base de datos, o null si no se
 * subió nada / hubo un error.
 *
 * Se usa tanto en [PROCESO: AGREGAR ALUMNO] como en
 * [PROCESO: EDITAR ALUMNO], más abajo.
 */
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
// Se activa cuando el formulario de "Registrar / editar" se
// envía SIN un id (alumno nuevo). Botón: name="agregar".
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre']);
    $grado = trim($_POST['grado']);
    $grupo = trim($_POST['grupo']);
    $curp = strtoupper(trim($_POST['curp']));

    if ($nombre && $grado && $grupo && $curp) {

        // Evitar CURP duplicada (la tabla no tiene UNIQUE todavía)
        $stmtDup = $conn->prepare("SELECT id FROM alumnos WHERE CURP = ? LIMIT 1");
        $stmtDup->bind_param("s", $curp);
        $stmtDup->execute();
        $existente = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();

        if ($existente) {
            $mensaje = "⚠️ Ya existe un alumno registrado con esa CURP";
            $tipo_mensaje = "warning";
        } else {
            $foto = subirFotoAlumno($curp, $extensionesPermitidas);

            $stmt = $conn->prepare("
                INSERT INTO alumnos (nombre, grado, grupo, CURP, foto)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $nombre, $grado, $grupo, $curp, $foto);

            if ($stmt->execute()) {
                $mensaje = "✅ Alumno '$nombre' agregado correctamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "❌ Error al agregar alumno";
                $tipo_mensaje = "error";
            }
        }
    } else {
        $mensaje = "⚠️ Completa todos los campos obligatorios (nombre, grado, grupo, teléfono y CURP)";
        $tipo_mensaje = "warning";
    }
}

// [PROCESO: EDITAR ALUMNO] ───────────────────────────────────
// Se activa cuando el formulario se envía CON un id oculto
// (alumno existente). Botón: name="editar".
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $grado = trim($_POST['grado']);
    $grupo = trim($_POST['grupo']);
    $curp = strtoupper(trim($_POST['curp']));

    if ($nombre && $grado && $grupo && $curp) {

        // Evitar que la CURP choque con la de OTRO alumno
        $stmtDup = $conn->prepare("SELECT id FROM alumnos WHERE CURP = ? AND id != ? LIMIT 1");
        $stmtDup->bind_param("si", $curp, $id);
        $stmtDup->execute();
        $existente = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();

        if ($existente) {
            $mensaje = "⚠️ Esa CURP ya pertenece a otro alumno";
            $tipo_mensaje = "warning";
        } else {
            // Traer la foto actual por si no se sube una nueva
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
                $mensaje = "✅ Alumno actualizado correctamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "❌ Error al actualizar";
                $tipo_mensaje = "error";
            }
        }
    } else {
        $mensaje = "⚠️ Completa todos los campos obligatorios";
        $tipo_mensaje = "warning";
    }
}

// [PROCESO: ELIMINAR ALUMNO] ─────────────────────────────────
// Se activa desde el botón 🗑️ de la lista: admin.php?eliminar=ID
// Primero borra sus registros de entrada/salida (por la llave
// foránea), luego borra al alumno.
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);

    $stmt = $conn->prepare("DELETE FROM registros WHERE alumno_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt2 = $conn->prepare("DELETE FROM alumnos WHERE id = ?");
    $stmt2->bind_param("i", $id);

    if ($stmt2->execute()) {
        header("Location: admin.php?eliminado=1");
        exit;
    }
}
if (isset($_GET['eliminado'])) {
    $mensaje = "✅ Alumno eliminado correctamente";
    $tipo_mensaje = "success";
}

// [CONSULTA: LISTA DE ALUMNOS] ───────────────────────────────
// Alimenta la tabla de [HTML: ALUMNOS - LISTA]
$alumnos = $conn->query("SELECT * FROM alumnos ORDER BY id DESC");

// [CONSULTA: ALUMNO A EDITAR] ────────────────────────────────
// Cuando entras con admin.php?accion=editar&id=X, esto trae los
// datos de ESE alumno para precargar el formulario.
$alumno_edit = null;
if ($accion === 'editar' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM alumnos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $alumno_edit = $stmt->get_result()->fetch_assoc();
}

// [CONSULTA: TABLAS] ─────────────────────────────────────────
// Estas 4 consultas alimentan las 4 sub-pestañas de la sección
// [HTML: TABLAS] (Grados y grupos / Tutores registrados /
// Tutores pendientes / Registros).

// Grados y grupos: lista de alumnos activos ordenada por grado y grupo
$gradosGrupos = $conn->query("
    SELECT nombre, grado, grupo
    FROM alumnos
    WHERE activo = 1
    ORDER BY grado, grupo, nombre
");

// Tutores registrados: ya hicieron /start en Telegram
$tutoresRegistrados = $conn->query("
    SELECT * FROM alumnos
    WHERE tutor_chat_id IS NOT NULL
    ORDER BY nombre
");

// Tutores pendientes: aún no vinculan su Telegram
$tutoresPendientes = $conn->query("
    SELECT * FROM alumnos
    WHERE tutor_chat_id IS NULL
    ORDER BY nombre
");

// Últimos registros de entrada/salida
$registrosRecientes = $conn->query("
    SELECT r.id, r.tipo, r.fecha_hora, a.nombre, a.grado, a.grupo
    FROM registros r
    INNER JOIN alumnos a ON a.id = r.alumno_id
    ORDER BY r.id DESC
    LIMIT 100
");

// [CONSULTA: DASHBOARD] ──────────────────────────────────────
// Alimenta las 4 tarjetas de [HTML: DASHBOARD]
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
  <title>Panel de Administrador — ChecaBot</title>
  <style>
    /* [CSS: VARIABLES Y BASE] ──────────────────────────────── */
    :root {
      --navy: #2c3e50;
      --teal: #048A81;
      --teal-dark: #037773;
      --bg: #f0f2f5;
      --muted: #7f8c8d;
      --borde: #e2e6ea;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: var(--bg); }

    .layout { display: flex; min-height: 100vh; }

    /* [CSS: SIDEBAR] ───────────────────────────────────────── */
    .sidebar {
      width: 260px;
      flex-shrink: 0;
      background: var(--navy);
      color: white;
      padding: 20px 0;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
    }
    .sidebar-marca {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 0 20px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.12);
      margin-bottom: 16px;
    }
    .sidebar-marca .logo { font-size: 28px; }
    .sidebar-marca strong { display: block; font-size: 16px; }
    .sidebar-marca small { color: #a9b7c4; font-size: 12px; }

    .nav { display: flex; flex-direction: column; gap: 2px; padding: 0 10px; }

    .nav-item, .nav-grupo-btn {
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      padding: 11px 12px;
      border: none;
      background: transparent;
      color: #d7e0e8;
      text-decoration: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: bold;
      cursor: pointer;
      text-align: left;
    }
    .nav-item:hover, .nav-grupo-btn:hover { background: rgba(255,255,255,0.08); color: white; }
    .nav-item.activo { background: var(--teal); color: white; }
    .nav-icono { font-size: 16px; width: 20px; text-align: center; }
    .nav-flecha { margin-left: auto; font-size: 12px; transition: transform .2s; }
    .nav-grupo-btn.abierto .nav-flecha { transform: rotate(180deg); }

    .nav-subgrupo { display: none; flex-direction: column; padding-left: 30px; gap: 2px; margin-bottom: 6px; }
    .nav-subgrupo.abierto { display: flex; }
    .nav-subitem {
      padding: 9px 12px;
      color: #b7c4cf;
      text-decoration: none;
      font-size: 13px;
      border-radius: 6px;
      border: none;
      background: transparent;
      text-align: left;
      cursor: pointer;
    }
    .nav-subitem:hover { background: rgba(255,255,255,0.08); color: white; }
    .nav-subitem.activo { color: white; background: rgba(4,138,129,0.35); font-weight: bold; }

    /* [CSS: CONTENIDO / SECCIONES] ─────────────────────────── */
    .contenido { flex: 1; padding: 30px 36px; }

    .panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
    .panel-header h1 { font-size: 24px; color: var(--navy); }
    .panel-header p { color: var(--muted); font-size: 14px; margin-top: 4px; }

    .seccion { display: none; }
    .seccion.activa { display: block; }

    .vista-tabla { display: none; }
    .vista-tabla.activa { display: block; }

    .sub-tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
    .sub-tab {
      padding: 9px 16px;
      border-radius: 20px;
      border: 1px solid var(--borde);
      background: white;
      font-size: 13px;
      font-weight: bold;
      color: var(--muted);
      cursor: pointer;
    }
    .sub-tab.activa { background: var(--teal); color: white; border-color: var(--teal); }

    /* [CSS: MENSAJES DE ÉXITO/ERROR] ───────────────────────── */
    .mensaje { margin-bottom: 20px; padding: 15px; border-radius: 8px; font-weight: bold; }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

    /* [CSS: TARJETAS DE ESTADÍSTICAS] (Dashboard) ──────────── */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid var(--borde); }
    .stat-card .valor { font-size: 30px; font-weight: 700; color: var(--navy); }
    .stat-card .etiqueta { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: bold; margin-top: 4px; }

    /* [CSS: FORMULARIO] (Registrar / editar alumno) ────────── */
    .form-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid var(--borde); margin-bottom: 20px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; font-size: 14px; }
    .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid var(--borde); border-radius: 5px; font-size: 14px; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 5px rgba(4,138,129,0.3); }
    .form-group small { color: var(--muted); font-size: 12px; }

    .foto-preview { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; background: #eee; margin-bottom: 8px; }

    /* [CSS: BOTONES] ───────────────────────────────────────── */
    .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 14px; }
    .btn-primary { background: var(--teal); color: white; }
    .btn-primary:hover { background: var(--teal-dark); }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-danger:hover { background: #c0392b; }
    .btn-secondary { background: #95a5a6; color: white; }
    .btn-secondary:hover { background: #7f8c8d; }
    .btn-small { padding: 6px 12px; font-size: 12px; }

    /* [CSS: TABLAS] (todas las tablas del panel) ───────────── */
    table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-radius: 8px; overflow: hidden; }
    th { background: var(--navy); color: white; padding: 13px 15px; text-align: left; font-weight: bold; font-size: 13px; }
    td { padding: 11px 15px; border-bottom: 1px solid var(--borde); font-size: 14px; }
    tr:hover { background: #f8f9fa; }
    tr:last-child td { border-bottom: none; }

    .badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-entrada { background: #d4edda; color: #155724; }
    .badge-salida { background: #f8d7da; color: #721c24; }

    .foto-mini { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: #eee; vertical-align: middle; }

    .empty-state { text-align: center; padding: 40px; color: #999; }

    /* [CSS: RESPONSIVE / MÓVIL] ─────────────────────────────── */
    @media (max-width: 860px) {
      .layout { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: static; }
      .contenido { padding: 20px; }
      .form-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="layout">

  <!-- [HTML: SIDEBAR] -->
  <aside class="sidebar">
    <div class="sidebar-marca">
      <span class="logo">🏫</span>
      <div>
        <strong>ChecaBot</strong>
        <small>Panel de Administrador</small>
      </div>
    </div>

    <nav class="nav">
      <button type="button" class="nav-item activo" data-seccion="dashboard">
        <span class="nav-icono">🏠</span> Dashboard
      </button>

      <button type="button" class="nav-grupo-btn abierto" data-grupo="alumnos">
        <span class="nav-icono">🎓</span> Alumnos <span class="nav-flecha">▾</span>
      </button>
      <div class="nav-subgrupo abierto" data-subgrupo="alumnos">
        <button type="button" class="nav-subitem activo" data-seccion="alumnos-lista">Ver alumnos</button>
        <a href="admin.php#alumnos-form" class="nav-subitem" data-seccion="alumnos-form">➕ Registrar / editar</a>
      </div>

      <button type="button" class="nav-grupo-btn" data-grupo="tablas">
        <span class="nav-icono">📊</span> Tablas <span class="nav-flecha">▾</span>
      </button>
      <div class="nav-subgrupo" data-subgrupo="tablas">
        <button type="button" class="nav-subitem" data-seccion="tablas" data-vista="grados_grupos">📚 Grados y grupos</button>
        <button type="button" class="nav-subitem" data-seccion="tablas" data-vista="tutores_registrados">✅ Tutores registrados</button>
        <button type="button" class="nav-subitem" data-seccion="tablas" data-vista="tutores_pendientes">⏳ Tutores pendientes</button>
        <button type="button" class="nav-subitem" data-seccion="tablas" data-vista="registros">🕐 Registros</button>
      </div>

      <button type="button" class="nav-item" data-seccion="configuracion">
        <span class="nav-icono">⚙️</span> Configuración
      </button>
    </nav>
  </aside>

  <!-- [HTML: CONTENIDO PRINCIPAL] -->
  <main class="contenido">

    <?php if ($mensaje): ?>
      <div class="mensaje <?= $tipo_mensaje ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <!-- [HTML: DASHBOARD] -->
    <section class="seccion activa" id="seccion-dashboard">
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
    </section>

    <!-- [HTML: ALUMNOS - LISTA] -->
    <section class="seccion" id="seccion-alumnos-lista">
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
                  <a href="admin.php?accion=editar&id=<?= $alumno['id'] ?>#alumnos-form" class="btn btn-primary btn-small btn-editar-alumno">✏️</a>
                  <a href="admin.php?eliminar=<?= $alumno['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('¿Eliminar a <?= addslashes($alumno['nombre']) ?>?')">🗑️</a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><p>No hay alumnos registrados aún.</p></div>
      <?php endif; ?>
    </section>

    <!-- [HTML: ALUMNOS - FORMULARIO] -->
    <section class="seccion" id="seccion-alumnos-form">
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
            <?php if ($alumno_edit): ?>
              <a href="admin.php#alumnos-form" class="btn btn-secondary">← Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </section>

    <!-- [HTML: TABLAS] -->
    <section class="seccion" id="seccion-tablas">
      <div class="panel-header">
        <div>
          <h1>Tablas de Información</h1>
          <p>Consulta rápida de datos del sistema</p>
        </div>
      </div>

      <div class="sub-tabs">
        <button type="button" class="sub-tab activa" data-vista-tabla="grados_grupos">📚 Grados y grupos</button>
        <button type="button" class="sub-tab" data-vista-tabla="tutores_registrados">✅ Tutores registrados</button>
        <button type="button" class="sub-tab" data-vista-tabla="tutores_pendientes">⏳ Tutores pendientes</button>
        <button type="button" class="sub-tab" data-vista-tabla="registros">🕐 Registros</button>
      </div>

      <!-- [HTML: TABLAS] sub-vista: Grados y grupos -->
      <div class="vista-tabla activa" id="vista-grados_grupos">
        <?php if ($gradosGrupos->num_rows > 0): ?>
          <table>
            <thead><tr><th>Nombre</th><th>Grado</th><th>Grupo</th></tr></thead>
            <tbody>
              <?php while ($fila = $gradosGrupos->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                <td><?= htmlspecialchars($fila['grado']) ?></td>
                <td><?= htmlspecialchars($fila['grupo'] ?: '—') ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><p>Aún no hay alumnos activos.</p></div>
        <?php endif; ?>
      </div>

      <!-- [HTML: TABLAS] sub-vista: Tutores registrados -->
      <div class="vista-tabla" id="vista-tutores_registrados">
        <?php if ($tutoresRegistrados->num_rows > 0): ?>
          <table>
            <thead><tr><th>Alumno</th><th>Chat ID</th></tr></thead>
            <tbody>
              <?php while ($fila = $tutoresRegistrados->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                <td><?= htmlspecialchars($fila['tutor_chat_id']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><p>Ningún tutor se ha registrado todavía.</p></div>
        <?php endif; ?>
      </div>

      <!-- [HTML: TABLAS] sub-vista: Tutores pendientes -->
      <div class="vista-tabla" id="vista-tutores_pendientes">
        <?php if ($tutoresPendientes->num_rows > 0): ?>
          <table>
            <thead><tr><th>Alumno</th><th>Grado/Grupo</th><th>CURP</th></tr></thead>
            <tbody>
              <?php while ($fila = $tutoresPendientes->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                <td><?= htmlspecialchars($fila['grado']) ?> <?= htmlspecialchars($fila['grupo'] ?? '') ?></td>
                <td><span style="font-family: monospace;"><?= $fila['CURP'] ? htmlspecialchars($fila['CURP']) : '<span style="color:#bbb;">Sin capturar</span>' ?></span></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><p>Todos los tutores ya se registraron. 🎉</p></div>
        <?php endif; ?>
      </div>

      <!-- [HTML: TABLAS] sub-vista: Registros -->
      <div class="vista-tabla" id="vista-registros">
        <?php if ($registrosRecientes->num_rows > 0): ?>
          <table>
            <thead><tr><th>Alumno</th><th>Grado/Grupo</th><th>Tipo</th><th>Fecha y hora</th></tr></thead>
            <tbody>
              <?php while ($fila = $registrosRecientes->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                <td><?= htmlspecialchars($fila['grado']) ?> <?= htmlspecialchars($fila['grupo'] ?? '') ?></td>
                <td>
                  <span class="badge badge-<?= $fila['tipo'] ?>">
                    <?= $fila['tipo'] === 'entrada' ? '✅ Entrada' : '🚪 Salida' ?>
                  </span>
                </td>
                <td><?= date('d/m/Y h:i A', strtotime($fila['fecha_hora'])) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><p>No hay registros todavía.</p></div>
        <?php endif; ?>
      </div>
    </section>

    <!-- [HTML: CONFIGURACIÓN] -->
    <section class="seccion" id="seccion-configuracion">
      <div class="panel-header">
        <div>
          <h1>Configuración</h1>
          <p>Próximamente</p>
        </div>
      </div>
      <div class="form-box">
        <p style="color: var(--muted);">Esta sección se definirá más adelante.</p>
      </div>
    </section>

  </main>
</div>

<script>
  // [JS: CAMBIAR DE SECCIÓN] ──────────────────────────────────
  // Oculta todas las <section class="seccion"> y muestra solo
  // la que corresponde al botón del sidebar que se presionó.
  function mostrarSeccion(idSeccion) {
    document.querySelectorAll('.seccion').forEach(el => el.classList.remove('activa'));
    document.getElementById('seccion-' + idSeccion).classList.add('activa');

    document.querySelectorAll('.nav-item, .nav-subitem').forEach(el => el.classList.remove('activo'));
  }

  document.querySelectorAll('[data-seccion]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      const idSeccion = boton.dataset.seccion;
      mostrarSeccion(idSeccion);
      boton.classList.add('activo');

      if (boton.dataset.vista) {
        mostrarVistaTabla(boton.dataset.vista);
      }
    });
  });

  // [JS: COLAPSAR SIDEBAR] ────────────────────────────────────
  // Abre/cierra los grupos "Alumnos" y "Tablas" del menú lateral.
  document.querySelectorAll('.nav-grupo-btn').forEach(function (boton) {
    const grupo = boton.dataset.grupo;
    const subgrupo = document.querySelector('.nav-subgrupo[data-subgrupo="' + grupo + '"]');
    boton.addEventListener('click', function () {
      subgrupo.classList.toggle('abierto');
      boton.classList.toggle('abierto');
    });
  });

  // [JS: SUB-PESTAÑAS DE TABLAS] ──────────────────────────────
  // Cambia entre las 4 vistas dentro de la sección "Tablas"
  // (Grados y grupos / Tutores registrados / Tutores pendientes / Registros)
  function mostrarVistaTabla(idVista) {
    document.querySelectorAll('.vista-tabla').forEach(el => el.classList.remove('activa'));
    document.getElementById('vista-' + idVista).classList.add('activa');

    document.querySelectorAll('.sub-tab').forEach(el => el.classList.remove('activa'));
    document.querySelector('.sub-tab[data-vista-tabla="' + idVista + '"]').classList.add('activa');
  }

  document.querySelectorAll('.sub-tab').forEach(function (boton) {
    boton.addEventListener('click', function () {
      mostrarVistaTabla(boton.dataset.vistaTabla);
    });
  });

  // [JS: ABRIR FORM SI VIENE DE EDITAR] ───────────────────────
  // Función compartida: activa una sección y resalta su botón
  // correspondiente en el sidebar.
  function activarSeccionInicial(idSeccion) {
    mostrarSeccion(idSeccion);
    document.querySelectorAll('.nav-item, .nav-subitem').forEach(el => el.classList.remove('activo'));
    const boton = document.querySelector('[data-seccion="' + idSeccion + '"]');
    if (boton) boton.classList.add('activo');
  }

  // Caso 1: se llegó por el link "➕ Registrar / editar" del sidebar
  // o por "← Cancelar" (ambos navegan a admin.php#alumnos-form,
  // recargando la página SIN accion=editar, garantizando modo
  // "Agregar Alumno" limpio, sin arrastrar datos de una edición previa).
  if (window.location.hash === '#alumnos-form') {
    activarSeccionInicial('alumnos-form');
  }

  // Caso 2: se llegó por el ✏️ de la lista (admin.php?accion=editar&id=X)
  <?php if ($alumno_edit): ?>
  activarSeccionInicial('alumnos-form');
  <?php endif; ?>
</script>

</body>
</html>