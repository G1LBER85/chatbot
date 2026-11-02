<?php
require_once __DIR__ . '/login/php/inicio/auth.php';
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

// [PROCESO: ELIMINAR CHAT ID DEL TUTOR] ────────────────────────────
// Desvincula al tutor (pone tutor_chat_id en NULL) sin borrar al
// alumno. El alumno pasa automáticamente a "Tutores pendientes",
// porque esa vista filtra por tutor_chat_id IS NULL. Si el tutor
// quiere volver a recibir notificaciones, debe escribir /start de
// nuevo en el bot (o se le captura a mano con el candado de la tabla).
// Esta acción solo se ofrece DESPUÉS de abrir el candado 🔓.
if (isset($_GET['eliminar_chat_id'])) {
    $idAlumno = intval($_GET['eliminar_chat_id']);

    $stmtChat = $conn->prepare("UPDATE alumnos SET tutor_chat_id = NULL WHERE id = ?");
    $stmtChat->bind_param("i", $idAlumno);
    $stmtChat->execute();
    $stmtChat->close();

    header("Location: tablas.php?vista={$vistaActual}&chat_eliminado=1");
    exit;
}

// [PROCESO: EDITAR EL CHAT ID DEL TUTOR A MANO] ────────────────────
// Se usa desde el candado de "Tutores registrados". Es la ÚNICA forma de
// cambiar el Chat ID de un alumno que ya tiene tutor: desde Telegram ya no
// se puede (el bot no sobrescribe). Va por POST, con un token anti-CSRF,
// para que otro sitio no pueda hacer este cambio en nombre del administrador.
if (empty($_SESSION['csrf_tablas'])) {
    $_SESSION['csrf_tablas'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_tablas'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_chat_id'])) {
    $tokenRecibido = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    $idAlumno      = (int) ($_POST['alumno_id'] ?? 0);
    $nuevoChatId   = is_string($_POST['nuevo_chat_id'] ?? null) ? trim($_POST['nuevo_chat_id']) : '';

    // Un Chat ID de Telegram es un número entero positivo (5 a 18 dígitos)
    if (!hash_equals($_SESSION['csrf_tablas'], $tokenRecibido)) {
        $resultado = 'chat_sesion=1';
    } elseif ($idAlumno <= 0 || !preg_match('/^[1-9]\d{4,17}$/', $nuevoChatId)) {
        $resultado = 'chat_invalido=1';
    } else {
        // Solo se edita a alumnos que YA tienen tutor (el candado no sirve
        // para dar de alta a uno nuevo: eso se hace desde Telegram)
        $stmtEditar = $conn->prepare(
            "UPDATE alumnos SET tutor_chat_id = ? WHERE id = ? AND tutor_chat_id IS NOT NULL"
        );
        $stmtEditar->bind_param("si", $nuevoChatId, $idAlumno);
        $stmtEditar->execute();
        $resultado = $stmtEditar->affected_rows > 0 ? 'chat_actualizado=1' : 'chat_sin_cambios=1';
        $stmtEditar->close();
    }

    header("Location: tablas.php?vista=tutores_registrados&{$resultado}");
    exit;
}

// [MENSAJES DE CONFIRMACIÓN] ──────────────────────────────────────
if (isset($_GET['eliminado'])) { $mensaje = "✅ Eliminado correctamente"; $tipo_mensaje = "success"; }
if (isset($_GET['chat_eliminado'])) { $mensaje = "✅ Chat ID eliminado, el alumno pasó a Tutores pendientes"; $tipo_mensaje = "success"; }
if (isset($_GET['chat_actualizado'])) { $mensaje = "✅ Chat ID actualizado. El candado volvió a cerrarse"; $tipo_mensaje = "success"; }
if (isset($_GET['chat_sin_cambios'])) { $mensaje = "ℹ️ No hubo cambios: el Chat ID era el mismo o el alumno ya no tiene tutor"; $tipo_mensaje = "success"; }
if (isset($_GET['chat_invalido'])) { $mensaje = "⚠️ Chat ID no válido: debe ser solo números, de 5 a 18 dígitos"; $tipo_mensaje = "error"; }
if (isset($_GET['chat_sesion'])) { $mensaje = "⚠️ La página estaba caducada y no se hizo el cambio. Inténtalo de nuevo"; $tipo_mensaje = "error"; }
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
      <div class="mensaje <?= $tipo_mensaje ?>"<?= $tipo_mensaje === 'error' ? ' style="background:#fdeeee; color:#a12a2a; border:1px solid #f1c0c0;"' : '' ?>><?= $mensaje ?></div>
    <?php endif; ?>

    <div class="sub-tabs">
      <a href="tablas.php?vista=grados_grupos" class="sub-tab <?= $vistaActual === 'grados_grupos' ? 'activa' : '' ?>">📚 Grados y grupos</a>
      <a href="tablas.php?vista=tutores_registrados" class="sub-tab <?= $vistaActual === 'tutores_registrados' ? 'activa' : '' ?>">✅ Tutores registrados</a>
      <a href="tablas.php?vista=tutores_pendientes" class="sub-tab <?= $vistaActual === 'tutores_pendientes' ? 'activa' : '' ?>">⏳ Tutores pendientes</a>
      <a href="tablas.php?vista=registros" class="sub-tab <?= $vistaActual === 'registros' ? 'activa' : '' ?>">🕐 Registros</a>
    </div>

    <?php
    // ── BARRA DE BÚSQUEDA GENERAL ─────────────────────────────────
    // Una sola barra para toda la sección: busca dentro de la tabla que
    // esté abierta. En las vistas de alumnos filtra en el navegador (al
    // instante) y recuerda el filtro de cada tabla aunque se recargue la
    // página (ver tablas/_barra_filtros.php). "registros" NO usa esta
    // barra: trae la suya porque filtra en el servidor, ya que esa tabla
    // puede tener miles de filas y no basta con filtrar las que se ven.
    if ($vistaActual !== 'registros') {
        $placeholders = [
            'grados_grupos'       => 'Nombre, grado o grupo…',
            'tutores_registrados' => 'Nombre del alumno o Chat ID…',
            'tutores_pendientes'  => 'Nombre o CURP…',
        ];
        $filtroClave         = $vistaActual;
        $filtroTablaId       = 'tablaDatos';
        $filtroConGradoGrupo = true;
        $filtroPlaceholder   = $placeholders[$vistaActual] ?? 'Buscar…';
        include 'tablas/_barra_filtros.php';
    }

    // Incluye SOLO el contenido de la vista elegida.
    // Cada archivo en tablas/ trae su propia consulta SQL y tabla HTML
    // (ya NO procesa formularios ni redirige — eso vive arriba).
    include 'tablas/' . $vistaActual . '.php';
    ?>
  </main>
</div>

</body>
</html>