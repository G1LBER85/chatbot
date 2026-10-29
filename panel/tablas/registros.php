<?php
// ═══════════════════════════════════════════════════════════════
// panel/tablas/registros.php
// ─────────────────────────────────────────────────────────────
// Registros de entrada/salida, con FILTROS EN EL SERVIDOR y paginación.
//
// ¿Por qué en el servidor y no en el navegador como las otras tablas?
// Antes esta vista traía solo los últimos 100 registros; un filtro en el
// navegador habría buscado únicamente dentro de esos 100. Aquí el filtro
// se vuelve parte de la consulta SQL, así que busca en TODO el historial,
// y solo se traen de la base los 50 de la página que se está viendo.
//
// CÓMO SE EVITA QUE EL FILTRO SE REINICIE: el filtro se guarda en la
// SESIÓN del servidor ($_SESSION). Por eso sobrevive a cambiar de pestaña
// de tabla y volver, a ir a otra pantalla y regresar, a F5 y al botón
// "Atrás". Solo se borra con "Limpiar filtros" (?limpiar=1) o cuando se
// cierra la sesión. La URL con filtros (?filtrar=1&q=...) también se puede
// recargar o compartir tal cual.
//
// Orden: se conserva el pedido original (grado de mayor a menor y, dentro
// del grado, lo más reciente primero).
// $conn viene de tablas.php (require '../conexion.php'), y la sesión ya la
// abrió auth.php, que tablas.php carga al inicio.
// ═══════════════════════════════════════════════════════════════

// ─── Funciones de apoyo (prefijo "reg" para no chocar con otras) ───
if (!function_exists('regParam')) {
    // Lee un parámetro GET SOLO si es texto (si alguien manda ?q[]=x,
    // llegaría un arreglo y PHP 8 truena al usarlo en preg_match/trim).
    function regParam(string $clave): string
    {
        return (isset($_GET[$clave]) && is_string($_GET[$clave])) ? $_GET[$clave] : '';
    }
}
if (!function_exists('regFecha')) {
    // Devuelve la fecha si es válida en formato AAAA-MM-DD; si no, ''.
    function regFecha(string $valor): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})\z/', $valor, $m)) {
            return '';
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $valor : '';
    }
}

$porPagina = 50;
$claveSesion = 'filtros_tablas_registros';
$filtroPorDefecto = ['q' => '', 'grado' => '', 'grupo' => '', 'tipo' => '', 'desde' => '', 'hasta' => '', 'pagina' => 1];

// ─── 1. Estado del filtro: lo guardado en la sesión, o lo que mande el formulario ───
$estado = $filtroPorDefecto;
if (isset($_SESSION[$claveSesion]) && is_array($_SESSION[$claveSesion])) {
    $estado = array_merge($filtroPorDefecto, $_SESSION[$claveSesion]);
}

if (isset($_GET['limpiar'])) {
    // Botón "Limpiar filtros"
    $estado = $filtroPorDefecto;
} elseif (isset($_GET['filtrar'])) {
    // Se envió el formulario de filtros: se validan TODOS los valores
    // (cada uno se acepta solo si es uno de los valores permitidos).
    $grado = regParam('grado');
    $grupo = strtoupper(regParam('grupo'));
    $tipo = regParam('tipo');
    $estado = [
        'q'      => trim(mb_substr(regParam('q'), 0, 80)),
        'grado'  => preg_match('/^[1-6]\z/', $grado) ? $grado : '',
        'grupo'  => preg_match('/^[A-J]\z/', $grupo) ? $grupo : '',
        'tipo'   => in_array($tipo, ['entrada', 'salida'], true) ? $tipo : '',
        'desde'  => regFecha(regParam('desde')),
        'hasta'  => regFecha(regParam('hasta')),
        'pagina' => 1,   // al cambiar un filtro se vuelve a la primera página
    ];
    if ($estado['desde'] !== '' && $estado['hasta'] !== '' && $estado['desde'] > $estado['hasta']) {
        // Si pusieron las fechas al revés, se acomodan en vez de mostrar 0 resultados.
        [$estado['desde'], $estado['hasta']] = [$estado['hasta'], $estado['desde']];
    }
} elseif (isset($_GET['pagina'])) {
    // Solo cambió de página: los filtros se quedan como estaban en la sesión.
    $estado['pagina'] = max(1, (int) regParam('pagina'));
}

// ─── 2. Armar el WHERE (todo con parámetros "?" — nada se pega al SQL) ───
$condiciones = [];
$tipos = '';
$parametros = [];

if ($estado['q'] !== '') {
    // Cada palabra debe aparecer en el nombre o en la CURP. Se escapan los
    // comodines de LIKE (% _ \) para que se busquen como texto normal.
    // La comparación ya ignora mayúsculas y acentos (collation utf8_general_ci).
    $palabras = array_slice(preg_split('/\s+/u', $estado['q'], -1, PREG_SPLIT_NO_EMPTY), 0, 6);
    foreach ($palabras as $palabra) {
        $condiciones[] = '(a.nombre LIKE ? OR a.CURP LIKE ?)';
        $patron = '%' . addcslashes($palabra, '%_\\') . '%';
        $tipos .= 'ss';
        $parametros[] = $patron;
        $parametros[] = $patron;
    }
}
if ($estado['grado'] !== '') {
    $condiciones[] = 'a.grado = ?';
    $tipos .= 's';
    $parametros[] = $estado['grado'];
}
if ($estado['grupo'] !== '') {
    $condiciones[] = 'a.grupo = ?';
    $tipos .= 's';
    $parametros[] = $estado['grupo'];
}
if ($estado['tipo'] !== '') {
    $condiciones[] = 'r.tipo = ?';
    $tipos .= 's';
    $parametros[] = $estado['tipo'];
}
if ($estado['desde'] !== '') {
    $condiciones[] = 'r.fecha_hora >= ?';
    $tipos .= 's';
    $parametros[] = $estado['desde'] . ' 00:00:00';
}
if ($estado['hasta'] !== '') {
    $condiciones[] = 'r.fecha_hora <= ?';
    $tipos .= 's';
    $parametros[] = $estado['hasta'] . ' 23:59:59';
}
$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

// ─── 3. Cuántos registros hay con estos filtros (para la paginación) ───
$stmtCuenta = $conn->prepare("SELECT COUNT(*) FROM registros r INNER JOIN alumnos a ON a.id = r.alumno_id $where");
if ($tipos !== '') {
    $stmtCuenta->bind_param($tipos, ...$parametros);
}
$stmtCuenta->execute();
$stmtCuenta->bind_result($totalRegistros);
$stmtCuenta->fetch();
$stmtCuenta->close();
$totalRegistros = (int) $totalRegistros;

$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$pagina = min(max(1, (int) $estado['pagina']), $totalPaginas);   // por si la página guardada ya no existe
$estado['pagina'] = $pagina;
$_SESSION[$claveSesion] = $estado;                                // ← aquí se "recuerda" el filtro
$offset = ($pagina - 1) * $porPagina;

// ─── 4. La página de registros (LIMIT y OFFSET son enteros calculados arriba, no vienen del usuario) ───
$stmt = $conn->prepare("
    SELECT r.id, r.tipo, r.fecha_hora, a.nombre, a.grado, a.grupo
    FROM registros r
    INNER JOIN alumnos a ON a.id = r.alumno_id
    $where
    ORDER BY a.grado DESC, r.id DESC
    LIMIT $porPagina OFFSET $offset
");
if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$registros = $stmt->get_result();

$filtrosActivos = count(array_filter(
    [$estado['q'], $estado['grado'], $estado['grupo'], $estado['tipo'], $estado['desde'], $estado['hasta']],
    function ($valor) { return $valor !== ''; }
));
$desdeNumero = $totalRegistros === 0 ? 0 : $offset + 1;
$hastaNumero = min($offset + $porPagina, $totalRegistros);
$enlacePagina = function (int $n) { return 'tablas.php?vista=registros&pagina=' . $n; };
?>

<!-- ═══════════════ BARRA DE FILTROS (envía por GET, se aplica sola) ═══════════════ -->
<form method="get" action="tablas.php" id="formFiltroRegistros" class="form-box" style="margin-bottom: 16px;">
  <input type="hidden" name="vista" value="registros">
  <input type="hidden" name="filtrar" value="1">

  <div style="display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end;">
    <div class="form-group" style="margin-bottom:0; flex:1; min-width:230px;">
      <label for="registros_q">🔎 Buscar</label>
      <input type="search" id="registros_q" name="q" value="<?= htmlspecialchars($estado['q']) ?>" placeholder="Nombre del alumno o CURP…" autocomplete="off" maxlength="80">
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label for="registros_grado">Grado</label>
      <select id="registros_grado" name="grado">
        <option value="">Todos</option>
        <?php for ($g = 1; $g <= 6; $g++): ?>
          <option value="<?= $g ?>" <?= $estado['grado'] === (string) $g ? 'selected' : '' ?>><?= $g ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label for="registros_grupo">Grupo</label>
      <select id="registros_grupo" name="grupo">
        <option value="">Todos</option>
        <?php foreach (range('A', 'J') as $letra): ?>
          <option value="<?= $letra ?>" <?= $estado['grupo'] === $letra ? 'selected' : '' ?>><?= $letra ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label for="registros_tipo">Tipo</label>
      <select id="registros_tipo" name="tipo">
        <option value="">Entrada y salida</option>
        <option value="entrada" <?= $estado['tipo'] === 'entrada' ? 'selected' : '' ?>>✅ Entrada</option>
        <option value="salida" <?= $estado['tipo'] === 'salida' ? 'selected' : '' ?>>🚪 Salida</option>
      </select>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label for="registros_desde">Desde</label>
      <input type="date" id="registros_desde" name="desde" value="<?= htmlspecialchars($estado['desde']) ?>">
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label for="registros_hasta">Hasta</label>
      <input type="date" id="registros_hasta" name="hasta" value="<?= htmlspecialchars($estado['hasta']) ?>">
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <a href="tablas.php?vista=registros&limpiar=1" class="btn btn-secondary">Limpiar filtros</a>
    </div>
  </div>

  <div style="margin-top:10px; font-size:13px; color:#64748b;" aria-live="polite">
    <?php if ($filtrosActivos > 0): ?><strong style="color:#048A81;">Filtro activo (<?= $filtrosActivos ?>)</strong> · <?php endif; ?>
    <?= number_format($totalRegistros) ?> registro<?= $totalRegistros === 1 ? '' : 's' ?>
    <?php if ($totalRegistros > 0): ?> · mostrando <?= number_format($desdeNumero) ?>–<?= number_format($hastaNumero) ?><?php endif; ?>
  </div>
</form>

<?php if ($registros->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Alumno</th>
        <th>Grado/Grupo</th>
        <th>Tipo</th>
        <th>Fecha y hora</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($fila = $registros->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['grado']) ?> <?= htmlspecialchars($fila['grupo'] ?? '') ?></td>
        <td>
          <span class="badge badge-<?= htmlspecialchars($fila['tipo']) ?>">
            <?= $fila['tipo'] === 'entrada' ? '✅ Entrada' : '🚪 Salida' ?>
          </span>
        </td>
        <td><?= date('d/m/Y h:i A', strtotime($fila['fecha_hora'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <?php if ($totalPaginas > 1): ?>
    <nav style="display:flex; gap:6px; flex-wrap:wrap; justify-content:center; align-items:center; margin-top:16px;" aria-label="Paginación de registros">
      <?php if ($pagina > 1): ?>
        <a class="btn btn-secondary btn-small" href="<?= $enlacePagina(1) ?>">« Primera</a>
        <a class="btn btn-secondary btn-small" href="<?= $enlacePagina($pagina - 1) ?>">‹ Anterior</a>
      <?php endif; ?>

      <?php for ($n = max(1, $pagina - 2); $n <= min($totalPaginas, $pagina + 2); $n++): ?>
        <a class="btn <?= $n === $pagina ? 'btn-primary' : 'btn-secondary' ?> btn-small" href="<?= $enlacePagina($n) ?>"><?= $n ?></a>
      <?php endfor; ?>

      <?php if ($pagina < $totalPaginas): ?>
        <a class="btn btn-secondary btn-small" href="<?= $enlacePagina($pagina + 1) ?>">Siguiente ›</a>
        <a class="btn btn-secondary btn-small" href="<?= $enlacePagina($totalPaginas) ?>">Última »</a>
      <?php endif; ?>
      <span style="font-size:13px; color:#64748b; margin-left:8px;">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
    </nav>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <p><?= $filtrosActivos > 0 ? 'No hay registros con esos filtros.' : 'No hay registros todavía.' ?></p>
  </div>
<?php endif; ?>

<script>
(function () {
  var formulario = document.getElementById('formFiltroRegistros');
  var entradaQ = document.getElementById('registros_q');
  var CLAVE_REENFOQUE = 'tablas:registros:reenfocar';
  var temporizador = null;

  // La página se vuelve a cargar cada vez que se aplica un filtro, y al
  // recargar se pierde el cursor. Por eso, cuando el envío lo provocó
  // ESCRIBIR en el cuadro de búsqueda, se deja una marca para devolverle el
  // cursor (al final del texto) en cuanto cargue de nuevo.
  function enviar(porEscritura) {
    if (porEscritura) {
      try { sessionStorage.setItem(CLAVE_REENFOQUE, '1'); } catch (e) {}
    }
    formulario.submit();
  }

  // Al escribir se espera 600 ms sin teclear antes de buscar (no en cada letra).
  entradaQ.addEventListener('input', function () {
    clearTimeout(temporizador);
    temporizador = setTimeout(function () { enviar(true); }, 600);
  });

  // Los selectores y las fechas se aplican en cuanto cambian.
  Array.prototype.forEach.call(formulario.querySelectorAll('select, input[type="date"]'), function (campo) {
    campo.addEventListener('change', function () { enviar(false); });
  });

  try {
    if (sessionStorage.getItem(CLAVE_REENFOQUE) === '1') {
      sessionStorage.removeItem(CLAVE_REENFOQUE);
      entradaQ.focus();
      var fin = entradaQ.value.length;
      entradaQ.setSelectionRange(fin, fin);
    }
  } catch (e) {}
})();
</script>