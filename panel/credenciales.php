<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales.php
// ─────────────────────────────────────────────────────────────
// Vista principal de "Credencialización": lista de alumnos con
// filtros y checkboxes para elegir a quién generarle su credencial.
// El trabajo pesado (armar el PDF) NO vive aquí — vive en
// panel/generar.php, que a su vez usa panel/plantilla_credenciales.php
// (el diseño), panel/estilos.css, y panel/credenciales/alumnos.php,
// qr.php y pdf.php. Este archivo solo arma la pantalla y apunta
// hacia allá. Sirve igual para uno que para muchos alumnos
// marcados, así que ya no hay un camino aparte para "individual".
//
// Índice de este archivo:
//   1. Consulta de alumnos
//   2. HTML — filtros (grado, grupo, nombre)
//   3. HTML — formulario/tabla de selección
//   4. JavaScript — filtrado y "marcar todos"
// ═══════════════════════════════════════════════════════════════

require '../conexion.php';

$paginaActual = 'credenciales';

// ─────────────────────────────────────────────────────────────
// 1. CONSULTA DE ALUMNOS
// ─────────────────────────────────────────────────────────────
// Traemos todos los alumnos activos para armar la lista con
// filtros y checkboxes, igual que en "Ver alumnos".
$alumnos = $conn->query("
    SELECT id, nombre, grado, grupo, CURP
    FROM alumnos
    WHERE activo = 1
    ORDER BY grado DESC, grupo ASC, nombre ASC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Credencialización — Panel de Administrador ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
</head>
<body>
<div class="layout">

  <?php include '../sidebar/sidebar.php'; ?>

  <main class="contenido">
    <div class="panel-header">
      <div>
        <h1>Credencialización</h1>
        <p>Genera credenciales en PDF, individuales o por lote</p>
      </div>
    </div>

    <?php if ($alumnos->num_rows > 0): ?>

      <!-- ═══════════════════════════════════════════════════════
           2. FILTROS: GRADO, GRUPO Y NOMBRE
           Mismo patrón de filtrado en el navegador usado en
           "Ver alumnos", para localizar rápido a quién credencializar.
           No toca el servidor — todo pasa en JavaScript (sección 4).
           ═══════════════════════════════════════════════════════ -->
      <div class="form-box" style="margin-bottom: 20px;">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:20px;">
          <div style="display:flex; gap:14px; flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0;">
              <label>Grado</label>
              <select id="filtroGrado">
                <option value="">Todos</option>
                <?php for ($g = 1; $g <= 6; $g++): ?>
                  <option value="<?= $g ?>"><?= $g ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label>Grupo</label>
              <select id="filtroGrupo">
                <option value="">Todos</option>
                <?php foreach (range('A', 'J') as $letra): ?>
                  <option value="<?= $letra ?>"><?= $letra ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0; flex:1; min-width:220px;">
            <label>Buscar por nombre</label>
            <input type="text" id="filtroNombre" placeholder="Ejemplo: Juan Pérez" autocomplete="off">
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════
           3. FORMULARIO Y TABLA DE SELECCIÓN
           Un solo camino para generar credenciales: se marcan
           checkboxes (uno o varios — funciona igual para 1 que para
           muchos) y se manda el formulario por POST a
           generar.php. Es POST y no GET porque
           con muchos alumnos marcados la URL sería demasiado larga
           para el servidor ("Request-URI Too Long").
           ═══════════════════════════════════════════════════════ -->
      <form action="generar.php" method="POST" target="_blank">

        <div class="form-box" style="margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
          <label style="display:flex; align-items:center; gap:8px; font-weight:bold; color:#334e68;">
            <input type="checkbox" id="marcarTodos" style="width:18px; height:18px;">
            Marcar todos los visibles
          </label>
          <button type="submit" class="btn btn-primary">🪪 Generar credenciales de los seleccionados (PDF)</button>
        </div>

        <table id="tablaAlumnos">
          <thead>
            <tr>
              <th style="width:40px;"></th>
              <th>Nombre</th>
              <th>Grado/Grupo</th>
              <th>CURP</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($alumno = $alumnos->fetch_assoc()): ?>
            <tr
              data-grado="<?= htmlspecialchars($alumno['grado']) ?>"
              data-grupo="<?= htmlspecialchars($alumno['grupo']) ?>"
              data-nombre="<?= htmlspecialchars(mb_strtolower($alumno['nombre'], 'UTF-8')) ?>">
              <td>
                <input
                  type="checkbox"
                  name="ids[]"
                  value="<?= $alumno['id'] ?>"
                  class="check-alumno"
                  style="width:18px; height:18px;"
                  <?= $alumno['CURP'] ? '' : 'disabled title="Este alumno no tiene CURP capturada"' ?>>
              </td>
              <td><?= htmlspecialchars($alumno['nombre']) ?></td>
              <td><?= htmlspecialchars($alumno['grado']) ?> <?= htmlspecialchars($alumno['grupo'] ?? '') ?></td>
              <td>
                <?= $alumno['CURP']
                    ? '<span style="font-family:monospace;">' . htmlspecialchars($alumno['CURP']) . '</span>'
                    : '<span style="color:#dc4c4c;">Sin CURP — no se puede credencializar</span>' ?>
              </td>
            </tr>
            <?php endwhile; ?>
            <tr id="filaSinResultados" style="display:none;">
              <td colspan="4" class="empty-state">No se encontraron alumnos con esos filtros.</td>
            </tr>
          </tbody>
        </table>
      </form>

      <!-- ═══════════════════════════════════════════════════════
           4. JAVASCRIPT — FILTRADO Y "MARCAR TODOS"
           Todo el filtrado ocurre aquí, en el navegador, sin
           recargar la página ni tocar el servidor.
           ═══════════════════════════════════════════════════════ -->
      <script>
      (function () {
        const filtroGrado = document.getElementById('filtroGrado');
        const filtroGrupo = document.getElementById('filtroGrupo');
        const filtroNombre = document.getElementById('filtroNombre');
        const marcarTodos = document.getElementById('marcarTodos');
        const filas = document.querySelectorAll('#tablaAlumnos tbody tr[data-nombre]');
        const filaSinResultados = document.getElementById('filaSinResultados');

        function aplicarFiltros() {
          const grado = filtroGrado.value;
          const grupo = filtroGrupo.value;
          const nombre = filtroNombre.value.trim().toLowerCase();
          let visibles = 0;

          filas.forEach(function (fila) {
            const coincideGrado = grado === '' || fila.dataset.grado === grado;
            const coincideGrupo = grupo === '' || fila.dataset.grupo === grupo;
            const coincideNombre = nombre === '' || fila.dataset.nombre.includes(nombre);
            const visible = coincideGrado && coincideGrupo && coincideNombre;
            fila.style.display = visible ? '' : 'none';
            if (visible) visibles++;
          });

          filaSinResultados.style.display = visibles === 0 ? '' : 'none';
        }

        // "Marcar todos los visibles": solo afecta las filas que
        // el filtro está mostrando actualmente, no las ocultas.
        marcarTodos.addEventListener('change', function () {
          filas.forEach(function (fila) {
            if (fila.style.display !== 'none') {
              const checkbox = fila.querySelector('.check-alumno');
              if (checkbox) checkbox.checked = marcarTodos.checked;
            }
          });
        });

        filtroGrado.addEventListener('change', aplicarFiltros);
        filtroGrupo.addEventListener('change', aplicarFiltros);
        filtroNombre.addEventListener('input', aplicarFiltros);
      })();
      </script>

    <?php else: ?>
      <div class="empty-state"><p>Aún no hay alumnos activos.</p></div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>