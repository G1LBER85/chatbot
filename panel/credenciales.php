<?php
require '../conexion.php';

$paginaActual = 'credenciales';

// [CONSULTA: ALUMNOS ACTIVOS] ──────────────────────────────────
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

      <!-- [FILTROS: GRADO, GRUPO Y NOMBRE] ─────────────────────
           Mismo patrón de filtrado en el navegador usado en
           "Ver alumnos", para localizar rápido a quién credencializar. -->
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

      <!-- [FORMULARIO: SELECCIÓN Y GENERACIÓN POR LOTE] ─────────
           Los checkboxes marcados se mandan como ids[] a
           generar_credencial.php, que arma un PDF tipo hoja carta
           con todas las tarjetas seleccionadas. -->
      <!-- method="POST" en vez de GET: con muchos alumnos marcados,
           la URL con todos los ids[] se vuelve tan larga que Apache
           la rechaza ("Request-URI Too Long"). POST no tiene ese
           límite porque los datos no viajan en la URL. -->
      <form action="generar_credencial.php" method="POST" target="_blank">
        <input type="hidden" name="modo" value="lote">

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
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($alumno = $alumnos->fetch_assoc()): ?>
            <tr
              data-grado="<?= htmlspecialchars($alumno['grado']) ?>"
              data-grupo="<?= htmlspecialchars($alumno['grupo']) ?>"
              data-nombre="<?= htmlspecialchars(mb_strtolower($alumno['nombre'], 'UTF-8')) ?>">
              <td>
                <input type="checkbox" name="ids[]" value="<?= $alumno['id'] ?>" class="check-alumno" style="width:18px; height:18px;">
              </td>
              <td><?= htmlspecialchars($alumno['nombre']) ?></td>
              <td><?= htmlspecialchars($alumno['grado']) ?> <?= htmlspecialchars($alumno['grupo'] ?? '') ?></td>
              <td>
                <?= $alumno['CURP']
                    ? '<span style="font-family:monospace;">' . htmlspecialchars($alumno['CURP']) . '</span>'
                    : '<span style="color:#dc4c4c;">Sin CURP — no se puede credencializar</span>' ?>
              </td>
              <td>
                <?php if ($alumno['CURP']): ?>
                  <a href="generar_credencial.php?modo=individual&id=<?= $alumno['id'] ?>" target="_blank" class="btn btn-primary btn-small" title="Generar credencial de este alumno">🪪 Generar</a>
                <?php else: ?>
                  <span class="btn btn-secondary btn-small" style="opacity:.5; cursor:not-allowed;" title="Este alumno no tiene CURP capturada">🪪 Generar</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
            <tr id="filaSinResultados" style="display:none;">
              <td colspan="5" class="empty-state">No se encontraron alumnos con esos filtros.</td>
            </tr>
          </tbody>
        </table>
      </form>

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