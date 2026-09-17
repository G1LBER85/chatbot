<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales.php
// ─────────────────────────────────────────────────────────────
// Vista principal de "Credencialización": lista de alumnos con
// filtros y checkboxes para elegir a quién generarle su credencial.
// El trabajo pesado (armar el PDF) NO vive aquí — vive en
// panel/credenciales/proceso_de_credenciales/generar.php, que a su
// vez usa plantilla_credenciales.php (el diseño), estilos.css, y
// panel/credenciales/alumnos.php, qr.php y pdf.php. Este archivo
// solo arma la pantalla y apunta hacia allá. Sirve igual para uno
// que para muchos alumnos marcados, así que ya no hay un camino
// aparte para "individual".
//
// Índice de este archivo:
//   1. Consulta de alumnos
//   2. HTML — filtros (grado, grupo, nombre)
//   3. HTML — formulario/tabla de selección + modal de carga
//   4. JavaScript — filtrado y "marcar todos"
//   5. JavaScript — modal real de "Generando..." (con fetch)
// ═══════════════════════════════════════════════════════════════

require '../conexion.php';

$paginaActual = 'credenciales';

// ─────────────────────────────────────────────────────────────
// 1. CONSULTA DE ALUMNOS
// ─────────────────────────────────────────────────────────────
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

      <form id="formCredenciales" action="credenciales/proceso_de_credenciales/generar.php" method="POST">

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

      <div class="modal-overlay" id="modalCargando">
        <div class="modal-caja">
          <div class="spinner" id="modalSpinner"></div>
          <div class="modal-titulo" id="modalTitulo">Generando credenciales…</div>
          <div class="modal-mensaje" id="modalMensaje">
            Si son muchos alumnos, esto puede tardar varios minutos. No cierres ni recargues esta página.
          </div>
          <button type="button" id="modalBotonCerrar" class="btn btn-secondary" style="margin-top:18px; display:none;">Cerrar</button>
        </div>
      </div>

      <style>
        .modal-overlay {
          display: none;
          position: fixed;
          inset: 0;
          background: rgba(0, 0, 0, 0.55);
          z-index: 999;
          justify-content: center;
          align-items: center;
        }
        .modal-overlay.visible { display: flex; }
        .modal-caja {
          background: white;
          border-radius: 14px;
          padding: 36px 44px;
          max-width: 380px;
          width: 90%;
          text-align: center;
          box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }
        .modal-titulo {
          font-size: 18px;
          font-weight: bold;
          color: var(--navy, #243b53);
          margin-bottom: 10px;
        }
        .modal-titulo.error { color: #c0392b; }
        .modal-mensaje {
          font-size: 14px;
          color: var(--muted, #64748b);
          line-height: 1.5;
        }
        .spinner {
          width: 44px;
          height: 44px;
          margin: 0 auto 18px;
          border: 4px solid #e2e8f0;
          border-top-color: var(--teal, #048A81);
          border-radius: 50%;
          animation: girar 0.8s linear infinite;
        }
        @keyframes girar {
          to { transform: rotate(360deg); }
        }
        .tabla-bloqueada {
          pointer-events: none;
          opacity: 0.6;
        }
      </style>

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

        function limpiarSeleccion() {
          filas.forEach(function (fila) {
            const checkbox = fila.querySelector('.check-alumno');
            if (checkbox) checkbox.checked = false;
          });
          marcarTodos.checked = false;
        }

        marcarTodos.addEventListener('change', function () {
          filas.forEach(function (fila) {
            if (fila.style.display !== 'none') {
              const checkbox = fila.querySelector('.check-alumno');
              if (checkbox && !checkbox.disabled) checkbox.checked = marcarTodos.checked;
            }
          });
        });

        filtroGrado.addEventListener('change', function () { limpiarSeleccion(); aplicarFiltros(); });
        filtroGrupo.addEventListener('change', function () { limpiarSeleccion(); aplicarFiltros(); });
        filtroNombre.addEventListener('input', function () { limpiarSeleccion(); aplicarFiltros(); });
      })();
      </script>

      <script>
      (function () {
        const formulario = document.getElementById('formCredenciales');
        const modal = document.getElementById('modalCargando');
        const modalTitulo = document.getElementById('modalTitulo');
        const modalMensaje = document.getElementById('modalMensaje');
        const modalBotonCerrar = document.getElementById('modalBotonCerrar');
        const modalSpinner = document.getElementById('modalSpinner');
        const botonGenerar = formulario.querySelector('button[type="submit"]');
        const tabla = document.getElementById('tablaAlumnos');

        const tituloOriginal = modalTitulo.textContent;
        const mensajeOriginal = modalMensaje.textContent;

        function bloquearInterfaz() {
          modal.classList.add('visible');
          modalTitulo.textContent = tituloOriginal;
          modalTitulo.classList.remove('error');
          modalMensaje.textContent = mensajeOriginal;
          modalSpinner.style.display = '';
          modalBotonCerrar.style.display = 'none';
          botonGenerar.disabled = true;
          tabla.classList.add('tabla-bloqueada');
        }

        function desbloquearInterfaz() {
          modal.classList.remove('visible');
          botonGenerar.disabled = false;
          tabla.classList.remove('tabla-bloqueada');
        }

        function mostrarError(mensaje) {
          modalTitulo.textContent = 'No se pudo generar';
          modalTitulo.classList.add('error');
          modalMensaje.textContent = mensaje;
          modalSpinner.style.display = 'none';
          modalBotonCerrar.style.display = 'inline-block';
          botonGenerar.disabled = false;
          tabla.classList.remove('tabla-bloqueada');
        }

        modalBotonCerrar.addEventListener('click', function () {
          modal.classList.remove('visible');
        });

        formulario.addEventListener('submit', function (evento) {
          evento.preventDefault();

          const marcados = formulario.querySelectorAll('.check-alumno:checked');
          if (marcados.length === 0) {
            alert('Marca al menos un alumno antes de generar credenciales.');
            return;
          }

          const datosFormulario = new FormData(formulario);

          bloquearInterfaz();

          fetch(formulario.action, {
            method: 'POST',
            body: datosFormulario
          })
            .then(function (respuesta) {
              if (!respuesta.ok) {
                return respuesta.text().then(function (texto) {
                  throw new Error(texto || 'Ocurrió un error al generar las credenciales.');
                });
              }

              const disposicion = respuesta.headers.get('Content-Disposition') || '';
              const coincidencia = disposicion.match(/filename="?([^"]+)"?/);
              const nombreArchivo = coincidencia ? coincidencia[1] : 'credenciales.pdf';
              const tipo = respuesta.headers.get('Content-Type') || '';

              return respuesta.blob().then(function (blob) {
                return { blob: blob, nombreArchivo: nombreArchivo, tipo: tipo };
              });
            })
            .then(function (resultado) {
              const url = URL.createObjectURL(resultado.blob);

              if (resultado.tipo.includes('pdf')) {
                window.open(url, '_blank');
              } else {
                const enlaceTemporal = document.createElement('a');
                enlaceTemporal.href = url;
                enlaceTemporal.download = resultado.nombreArchivo;
                document.body.appendChild(enlaceTemporal);
                enlaceTemporal.click();
                enlaceTemporal.remove();
              }

              desbloquearInterfaz();
            })
            .catch(function (error) {
              mostrarError(error.message);
            });
        });
      })();
      </script>

    <?php else: ?>
      <div class="empty-state"><p>Aún no hay alumnos activos.</p></div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>