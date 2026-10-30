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
// que para muchos alumnos marcados.
//
// Índice de este archivo:
//   1. Consulta de alumnos
//   2. HTML — filtros (grado, grupo, nombre)
//   3. HTML — formulario/tabla de selección + modal de carga
//      (con Skeleton Loader y barra de progreso)
//   4. JavaScript — filtrado y "marcar todos"
//   5. JavaScript — modal de "Generando..." (fetch, contador de
//      progreso y bloqueo de la pantalla)
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

      <!-- ═══════════════════════════════════════════════════════
           2. FILTROS: GRADO, GRUPO Y NOMBRE
           ═══════════════════════════════════════════════════════ -->
      <div class="form-box" id="cajaFiltros" style="margin-bottom: 20px;">
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
           El envío lo intercepta por completo el JavaScript de la
           sección 5 con fetch() — por eso NO lleva target="_blank":
           la pestaña/descarga la abre el propio JavaScript una vez
           que la respuesta está lista.
           ═══════════════════════════════════════════════════════ -->
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

      <!-- ═══════════════════════════════════════════════════════
           MODAL: "Generando credenciales..."
           Muestra un SKELETON LOADER: una maqueta gris con efecto de
           brillo que imita cómo va a lucir la credencial (frente y
           reverso), más una barra de progreso real. Es mejor que un
           spinner suelto porque le da forma a lo que se está
           esperando.
           ═══════════════════════════════════════════════════════ -->
      <div class="modal-overlay" id="modalCargando">
        <div class="modal-caja" id="modalCaja" role="dialog" aria-modal="true" aria-labelledby="modalTitulo" tabindex="-1">

          <div class="modal-titulo" id="modalTitulo">Generando credenciales…</div>

          <div class="sk-par" id="modalSkeleton" aria-hidden="true">
            <!-- Maqueta del FRENTE -->
            <div class="sk-tarjeta">
              <div class="sk sk-encabezado"></div>
              <div class="sk-cuerpo">
                <div class="sk sk-foto"></div>
                <div class="sk-lineas">
                  <div class="sk sk-linea" style="width:90%"></div>
                  <div class="sk sk-linea" style="width:45%"></div>
                  <div class="sk sk-linea" style="width:75%"></div>
                </div>
              </div>
              <div class="sk sk-pie"></div>
            </div>
            <!-- Maqueta del REVERSO -->
            <div class="sk-tarjeta">
              <div class="sk sk-encabezado"></div>
              <div class="sk-cuerpo sk-centro">
                <div class="sk sk-qr"></div>
              </div>
              <div class="sk sk-pie"></div>
            </div>
          </div>

          <div class="progreso-barra" id="modalBarra" aria-hidden="true">
            <div class="progreso-relleno" id="modalRelleno"></div>
          </div>

          <div class="modal-mensaje" id="modalMensaje" aria-live="polite">
            Preparando… Si son muchos alumnos, esto puede tardar varios minutos. No cierres ni recargues esta página.
          </div>

          <button type="button" id="modalBotonCerrar" class="btn btn-secondary" style="margin-top:18px; display:none;">Cerrar</button>
        </div>
      </div>

      <style>
        /* ── Modal ─────────────────────────────────────────── */
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
          padding: 30px 36px;
          max-width: 420px;
          width: 92%;
          text-align: center;
          box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
          outline: none;
        }
        .modal-titulo {
          font-size: 18px;
          font-weight: bold;
          color: var(--navy, #243b53);
          margin-bottom: 16px;
        }
        .modal-titulo.error { color: #c0392b; }
        .modal-mensaje {
          font-size: 14px;
          color: var(--muted, #64748b);
          line-height: 1.5;
        }

        /* ── Skeleton Loader ───────────────────────────────── */
        .sk {
          position: relative;
          overflow: hidden;
          background: #e5eaf0;
          border-radius: 4px;
        }
        /* El "brillo" que cruza cada bloque de izquierda a derecha */
        .sk::after {
          content: "";
          position: absolute;
          inset: 0;
          transform: translateX(-100%);
          background: linear-gradient(90deg, transparent, rgba(255,255,255,.7), transparent);
          animation: brillo 1.4s ease-in-out infinite;
        }
        @keyframes brillo {
          100% { transform: translateX(100%); }
        }
        .sk-par {
          display: flex;
          gap: 12px;
          justify-content: center;
          margin-bottom: 18px;
        }
        .sk-tarjeta {
          width: 150px;
          height: 94px;           /* misma proporción que la credencial real (85.2 x 53.6) */
          border: 1px solid #d9e0e8;
          border-radius: 8px;
          padding: 5px;
          display: flex;
          flex-direction: column;
          gap: 5px;
          background: #fff;
        }
        .sk-encabezado { height: 14px; }
        .sk-cuerpo {
          flex: 1;
          display: flex;
          gap: 7px;
          align-items: center;
        }
        .sk-cuerpo.sk-centro { justify-content: center; }
        .sk-foto { width: 32px; height: 40px; flex-shrink: 0; }
        .sk-lineas { flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .sk-linea { height: 7px; }
        .sk-qr { width: 40px; height: 40px; }
        .sk-pie { height: 8px; }

        /* ── Barra de progreso ─────────────────────────────── */
        .progreso-barra {
          height: 8px;
          background: #e5eaf0;
          border-radius: 99px;
          overflow: hidden;
          margin-bottom: 12px;
        }
        .progreso-relleno {
          height: 100%;
          width: 0%;
          background: var(--teal, #048A81);
          border-radius: 99px;
          transition: width .35s ease;
        }

        /* ── Bloqueo visual mientras se genera ─────────────────
           Se usa junto con el atributo "inert" (que también impide
           el foco con teclado y los clics). A propósito NO se pone
           "disabled" en los checkboxes de alumnos: un campo
           disabled se EXCLUYE del envío y de FormData(), y se
           perderían los alumnos marcados. */
        .bloqueado {
          pointer-events: none;
          opacity: 0.55;
          user-select: none;
        }

        /* Si la persona tiene activada la opción de "reducir
           movimiento" en su sistema, se apagan las animaciones. */
        @media (prefers-reduced-motion: reduce) {
          .sk::after { animation: none; }
          .progreso-relleno { transition: none; }
        }
      </style>

      <!-- ═══════════════════════════════════════════════════════
           4. JAVASCRIPT — FILTRADO Y "MARCAR TODOS"
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

        // Al cambiar CUALQUIER filtro se desmarcan todos los
        // checkboxes (visibles u ocultos), para evitar generar sin
        // querer a alguien de otro grado/grupo que se había quedado
        // marcado desde antes, oculto por el filtro nuevo.
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

      <!-- ═══════════════════════════════════════════════════════
           5. JAVASCRIPT — MODAL DE "GENERANDO..." (con fetch)
           - Muestra el Skeleton Loader y una barra con el avance
             real (lo consulta a progreso.php cada 800 ms).
           - BLOQUEA la pantalla mientras genera: el menú lateral,
             los filtros, la tabla y el botón quedan inertes (no se
             pueden clicar ni alcanzar con el teclado), y el
             navegador avisa si intentan cerrar o recargar la
             pestaña a medio proceso.
           OJO con el orden: los datos del formulario (FormData) se
           capturan ANTES de bloquear nada.
           ═══════════════════════════════════════════════════════ -->
      <script>
      (function () {
        const formulario = document.getElementById('formCredenciales');
        const modal = document.getElementById('modalCargando');
        const modalCaja = document.getElementById('modalCaja');
        const modalTitulo = document.getElementById('modalTitulo');
        const modalMensaje = document.getElementById('modalMensaje');
        const modalBotonCerrar = document.getElementById('modalBotonCerrar');
        const modalSkeleton = document.getElementById('modalSkeleton');
        const modalBarra = document.getElementById('modalBarra');
        const modalRelleno = document.getElementById('modalRelleno');
        const botonGenerar = formulario.querySelector('button[type="submit"]');

        // Zonas que se bloquean mientras se genera. El modal NO está
        // en esta lista: tiene que seguir siendo usable (botón Cerrar).
        const zonasABloquear = [
          document.querySelector('.sidebar'),
          document.getElementById('cajaFiltros'),
          formulario
        ].filter(Boolean);

        const tituloOriginal = modalTitulo.textContent;
        const mensajeOriginal = modalMensaje.textContent;

        function avisarAlSalir(evento) {
          evento.preventDefault();
          evento.returnValue = '';
        }

        function bloquearPantalla() {
          zonasABloquear.forEach(function (zona) {
            zona.setAttribute('inert', '');
            zona.classList.add('bloqueado');
          });
          botonGenerar.disabled = true;
          window.addEventListener('beforeunload', avisarAlSalir);
        }

        function desbloquearPantalla() {
          zonasABloquear.forEach(function (zona) {
            zona.removeAttribute('inert');
            zona.classList.remove('bloqueado');
          });
          botonGenerar.disabled = false;
          window.removeEventListener('beforeunload', avisarAlSalir);
        }

        function abrirModal() {
          modalTitulo.textContent = tituloOriginal;
          modalTitulo.classList.remove('error');
          modalMensaje.textContent = mensajeOriginal;
          modalSkeleton.style.display = '';
          modalBarra.style.display = '';
          modalRelleno.style.width = '0%';
          modalBotonCerrar.style.display = 'none';
          modal.classList.add('visible');
          modalCaja.focus();
        }

        function cerrarModal() {
          modal.classList.remove('visible');
        }

        function mostrarProgreso(hechos, total) {
          const porcentaje = Math.min(100, Math.round((hechos / total) * 100));
          modalRelleno.style.width = porcentaje + '%';
          modalMensaje.textContent = 'Generando credencial ' + hechos + ' de ' + total + ' (' + porcentaje + '%)…';
        }

        function mostrarError(mensaje) {
          modalTitulo.textContent = 'No se pudo generar';
          modalTitulo.classList.add('error');
          modalMensaje.textContent = mensaje;
          modalSkeleton.style.display = 'none';
          modalBarra.style.display = 'none';
          modalBotonCerrar.style.display = 'inline-block';
          // La pantalla se desbloquea de una vez (el modal sigue
          // tapando todo hasta que den clic en "Cerrar"), así pueden
          // volver a intentarlo sin recargar la página.
          desbloquearPantalla();
        }

        modalBotonCerrar.addEventListener('click', function () {
          cerrarModal();
          botonGenerar.focus();
        });

        formulario.addEventListener('submit', function (evento) {
          evento.preventDefault();

          const marcados = formulario.querySelectorAll('.check-alumno:checked');
          if (marcados.length === 0) {
            alert('Marca al menos un alumno antes de generar credenciales.');
            return;
          }

          // Se capturan los datos PRIMERO, antes de bloquear nada.
          const datosFormulario = new FormData(formulario);

          // Token al azar para identificar ESTA generación: generar.php
          // va escribiendo su avance con este token, y progreso.php es
          // lo que se consulta aquí abajo para leerlo.
          const token = 'tok' + Date.now() + Math.random().toString(36).slice(2);
          datosFormulario.append('token', token);

          abrirModal();
          bloquearPantalla();

          // Sondeo del progreso. Un fallo al consultar NO debe
          // interrumpir la generación real, por eso se ignora y se
          // reintenta en el siguiente ciclo. "consultando" evita
          // encimar peticiones si el servidor tarda en responder.
          let consultando = false;
          const intervaloProgreso = setInterval(function () {
            if (consultando) return;
            consultando = true;
            fetch('credenciales/proceso_de_credenciales/progreso.php?token=' + encodeURIComponent(token))
              .then(function (r) { return r.json(); })
              .then(function (datos) {
                if (datos.total > 0) mostrarProgreso(datos.hechos, datos.total);
              })
              .catch(function () {})
              .finally(function () { consultando = false; });
          }, 800);

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
              clearInterval(intervaloProgreso);
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

              // Se deja ver la barra llena un instante antes de cerrar.
              modalRelleno.style.width = '100%';
              modalTitulo.textContent = 'Listo';
              modalMensaje.textContent = 'Credenciales generadas.';
              desbloquearPantalla();
              setTimeout(function () {
                cerrarModal();
                botonGenerar.focus();
              }, 450);
            })
            .catch(function (error) {
              clearInterval(intervaloProgreso);
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