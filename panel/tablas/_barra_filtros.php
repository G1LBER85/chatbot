<?php
// ═══════════════════════════════════════════════════════════════
// panel/tablas/_barra_filtros.php
// ─────────────────────────────────────────────────────────────
// Barra de búsqueda GENERAL de la sección Tablas. Se incluye una
// sola vez desde panel/tablas.php (para cada vista que filtra en el
// navegador) y también desde tabla_administradores.php. Filtra las
// filas de la tabla que esté en pantalla, al instante y sin recargar.
//
// Quien la incluya define ANTES del include (todas opcionales):
//   $filtroClave          nombre único de la tabla (ej. 'grados_grupos').
//                         Es la llave con la que se recuerda su filtro.
//   $filtroTablaId        id del <table> a filtrar (def. 'tablaDatos')
//   $filtroConGradoGrupo  true = muestra los selectores de grado y
//                         grupo; false = solo el cuadro de búsqueda
//   $filtroPlaceholder    texto de ayuda dentro del cuadro de búsqueda
//
// CÓMO SE EVITA QUE EL FILTRO SE REINICIE: el estado (texto, grado
// y grupo) se guarda en sessionStorage del navegador, una entrada por
// tabla. Se vuelve a leer cada vez que carga la página, así que
// sobrevive a: eliminar un alumno (recarga la página), ir a editar y
// regresar, cambiar de pestaña de tabla y volver, y F5. Solo se borra
// con el botón "Limpiar filtros" o al cerrar la pestaña del navegador.
//
// Las filas deben traer data-grado y data-grupo en su <tr>, y la
// ÚLTIMA celda de cada fila debe ser la de acciones (esa no se busca).
// ═══════════════════════════════════════════════════════════════

$filtroClave = $filtroClave ?? 'tabla';
$filtroTablaId = $filtroTablaId ?? 'tablaDatos';
$filtroConGradoGrupo = $filtroConGradoGrupo ?? true;
$filtroPlaceholder = $filtroPlaceholder ?? 'Buscar…';
$filtroId = 'filtro_' . preg_replace('/[^A-Za-z0-9_]/', '_', $filtroClave);
?>
<div class="form-box" id="<?= $filtroId ?>_caja" style="margin-bottom: 16px;">
  <div style="display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end;">

    <div class="form-group" style="margin-bottom:0; flex:1; min-width:230px;">
      <label for="<?= $filtroId ?>_q">🔎 Buscar</label>
      <input type="search" id="<?= $filtroId ?>_q" placeholder="<?= htmlspecialchars($filtroPlaceholder) ?>" autocomplete="off">
    </div>

    <?php if ($filtroConGradoGrupo): ?>
    <div class="form-group" style="margin-bottom:0;">
      <label for="<?= $filtroId ?>_grado">Grado</label>
      <select id="<?= $filtroId ?>_grado">
        <option value="">Todos</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
        <option value="6">6</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label for="<?= $filtroId ?>_grupo">Grupo</label>
      <select id="<?= $filtroId ?>_grupo">
        <option value="">Todos</option>
        <option value="A">A</option>
        <option value="B">B</option>
        <option value="C">C</option>
        <option value="D">D</option>
        <option value="E">E</option>
        <option value="F">F</option>
        <option value="G">G</option>
        <option value="H">H</option>
        <option value="I">I</option>
        <option value="J">J</option>
      </select>
    </div>
    <?php endif; ?>

    <div class="form-group" style="margin-bottom:0;">
      <button type="button" class="btn btn-secondary" id="<?= $filtroId ?>_limpiar">Limpiar filtros</button>
    </div>
  </div>
  <div id="<?= $filtroId ?>_contador" style="margin-top:10px; font-size:13px; color:#64748b;" aria-live="polite"></div>
</div>

<script>
(function () {
  var ID = <?= json_encode($filtroId) ?>;
  var CLAVE = <?= json_encode('tablas:filtro:' . $filtroClave) ?>;
  var TABLA_ID = <?= json_encode($filtroTablaId) ?>;
  var CON_GRADO_GRUPO = <?= json_encode($filtroConGradoGrupo) ?>;

  /* PURO:INICIO — lógica sin DOM (se prueba aparte) */

  // Quita acentos y mayúsculas, para que "perez" encuentre "Pérez".
  function normalizar(texto) {
    return String(texto).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
  }

  // El texto se parte en palabras: TODAS deben aparecer en la fila.
  function crearFiltro(q, grado, grupo) {
    return {
      terminos: normalizar(q).split(/\s+/).filter(Boolean),
      grado: grado || '',
      grupo: (grupo || '').toUpperCase()
    };
  }

  // fila = { texto (ya normalizado), grado, grupo }
  function coincide(fila, filtro) {
    if (filtro.grado !== '' && fila.grado !== filtro.grado) return false;
    if (filtro.grupo !== '' && fila.grupo !== filtro.grupo) return false;
    for (var i = 0; i < filtro.terminos.length; i++) {
      if (fila.texto.indexOf(filtro.terminos[i]) === -1) return false;
    }
    return true;
  }

  function estadoVacio() { return { q: '', grado: '', grupo: '' }; }

  // "almacen" es sessionStorage; puede ser null o fallar (modo privado,
  // cuota llena...): en cualquier caso se sigue funcionando sin recordar.
  function leerEstado(almacen, clave) {
    try {
      var crudo = almacen && almacen.getItem(clave);
      if (!crudo) return estadoVacio();
      var o = JSON.parse(crudo);
      return { q: String(o.q || ''), grado: String(o.grado || ''), grupo: String(o.grupo || '') };
    } catch (e) {
      return estadoVacio();
    }
  }

  function guardarEstado(almacen, clave, estado) {
    try {
      if (!almacen) return;
      if (!estado.q && !estado.grado && !estado.grupo) almacen.removeItem(clave);
      else almacen.setItem(clave, JSON.stringify(estado));
    } catch (e) { /* no pasa nada: simplemente no se recuerda */ }
  }
  /* PURO:FIN */

  function iniciar() {
    var caja = document.getElementById(ID + '_caja');
    var tabla = document.getElementById(TABLA_ID);
    if (!tabla) { caja.style.display = 'none'; return; }   // no hay tabla (vista vacía)

    var almacen = null;
    try { almacen = window.sessionStorage; } catch (e) { almacen = null; }

    var entradaQ = document.getElementById(ID + '_q');
    var selGrado = CON_GRADO_GRUPO ? document.getElementById(ID + '_grado') : null;
    var selGrupo = CON_GRADO_GRUPO ? document.getElementById(ID + '_grupo') : null;
    var botonLimpiar = document.getElementById(ID + '_limpiar');
    var contador = document.getElementById(ID + '_contador');

    // Se calcula UNA vez el texto de cada fila (sin la última celda, que
    // es la de acciones) para que filtrar miles de filas siga siendo rápido.
    var filas = Array.prototype.slice.call(tabla.querySelectorAll('tbody tr')).map(function (tr) {
      var celdas = Array.prototype.slice.call(tr.cells, 0, Math.max(1, tr.cells.length - 1));
      return {
        tr: tr,
        texto: normalizar(celdas.map(function (c) { return c.textContent; }).join(' ')),
        grado: tr.getAttribute('data-grado') || '',
        grupo: (tr.getAttribute('data-grupo') || '').toUpperCase()
      };
    });

    var sinResultados = document.createElement('div');
    sinResultados.className = 'empty-state';
    sinResultados.style.display = 'none';
    sinResultados.innerHTML = '<p>No se encontraron resultados con esos filtros.</p>';
    tabla.parentNode.insertBefore(sinResultados, tabla.nextSibling);

    function leerFormulario() {
      return {
        q: entradaQ.value,
        grado: selGrado ? selGrado.value : '',
        grupo: selGrupo ? selGrupo.value : ''
      };
    }

    function aplicar() {
      var estado = leerFormulario();
      var filtro = crearFiltro(estado.q, estado.grado, estado.grupo);
      var visibles = 0;
      filas.forEach(function (f) {
        var ok = coincide(f, filtro);
        f.tr.style.display = ok ? '' : 'none';
        if (ok) visibles++;
      });
      sinResultados.style.display = (visibles === 0 && filas.length > 0) ? '' : 'none';
      var activo = estado.q.trim() !== '' || estado.grado !== '' || estado.grupo !== '';
      contador.textContent = (activo ? 'Filtro activo · ' : '') + 'Mostrando ' + visibles + ' de ' + filas.length;
      botonLimpiar.disabled = !activo;
      guardarEstado(almacen, CLAVE, estado);
    }

    // 1) Se recupera el filtro que había antes de que se recargara la página
    var guardado = leerEstado(almacen, CLAVE);
    entradaQ.value = guardado.q;
    if (selGrado) selGrado.value = guardado.grado;
    if (selGrupo) selGrupo.value = guardado.grupo;

    // 2) Se aplica y se dejan los eventos
    aplicar();
    entradaQ.addEventListener('input', aplicar);
    if (selGrado) selGrado.addEventListener('change', aplicar);
    if (selGrupo) selGrupo.addEventListener('change', aplicar);
    botonLimpiar.addEventListener('click', function () {
      entradaQ.value = '';
      if (selGrado) selGrado.value = '';
      if (selGrupo) selGrupo.value = '';
      aplicar();
      entradaQ.focus();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();
})();
</script>