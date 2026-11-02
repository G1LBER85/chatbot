<?php
// [CONSULTA: TUTORES REGISTRADOS] ──────────────────────────────
// Alumnos cuyo tutor ya hizo /start en Telegram (tutor_chat_id
// tiene valor). Se usa SELECT * porque además de mostrar el nombre
// y el chat_id, necesitamos "id" y "grado" para: (1) armar los
// enlaces de Editar/Eliminar y (2) ordenar por grado descendente.
$tutoresRegistrados = $conn->query("
    SELECT * FROM alumnos
    WHERE tutor_chat_id IS NOT NULL
    ORDER BY grado DESC, nombre ASC
");
?>

<?php if ($tutoresRegistrados->num_rows > 0): ?>
  <table id="tablaDatos">
    <thead>
      <tr>
        <th>Alumno</th>
        <th>Grado</th>
        <th>Chat ID</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($fila = $tutoresRegistrados->fetch_assoc()): ?>
      <tr data-grado="<?= htmlspecialchars($fila['grado'] ?? '') ?>" data-grupo="<?= htmlspecialchars($fila['grupo'] ?? '') ?>">
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['grado']) ?></td>
        <td>
          <!-- 🔒 CANDADO DEL CHAT ID
               Cerrado por defecto: solo muestra el número. Con clic en el candado se
               abre 🔓 y aparece el campo para editarlo A MANO (el bot ya no puede
               sobrescribirlo). Quitar el Chat ID también quedó detrás del candado. -->
          <div class="chat-id-celda">
            <div class="chat-id-bloqueado">
              <span class="chat-id-texto"><?= htmlspecialchars($fila['tutor_chat_id']) ?></span>
              <button type="button" class="btn btn-secondary btn-small btn-candado"
                      title="Bloqueado. Clic para desbloquear y editar el Chat ID"
                      aria-label="Desbloquear el Chat ID">🔒</button>
            </div>

            <form method="post" action="tablas.php?vista=tutores_registrados" class="chat-id-edicion"
                  autocomplete="off" data-nombre="<?= htmlspecialchars($fila['nombre']) ?>">
              <input type="hidden" name="editar_chat_id" value="1">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="alumno_id" value="<?= (int) $fila['id'] ?>">
              <span title="Desbloqueado">🔓</span>
              <input type="text" name="nuevo_chat_id" class="chat-id-campo"
                     value="<?= htmlspecialchars($fila['tutor_chat_id']) ?>"
                     inputmode="numeric" pattern="[1-9][0-9]{4,17}" maxlength="18" required
                     title="Solo números, de 5 a 18 dígitos">
              <button type="submit" class="btn btn-primary btn-small" title="Guardar el nuevo Chat ID">💾</button>
              <!-- Quitar el Chat ID solo desvincula al tutor (tutor_chat_id = NULL);
                   el alumno NO se borra, solo pasa a la vista de Tutores pendientes. -->
              <a href="tablas.php?vista=tutores_registrados&eliminar_chat_id=<?= (int) $fila['id'] ?>"
                 class="btn btn-danger btn-small"
                 title="Quitar el Chat ID (el alumno pasa a Tutores pendientes)"
                 onclick="return confirm('¿Quitar el Chat ID de <?= htmlspecialchars(addslashes($fila['nombre'])) ?>? El alumno pasará a Tutores pendientes.')">🗑️</a>
              <button type="button" class="btn btn-secondary btn-small btn-cancelar-candado"
                      title="Cancelar y volver a bloquear">✖</button>
            </form>
          </div>
        </td>
        <td>
          <div style="display:flex; gap:8px;">
            <!-- Editar reutiliza el mismo formulario de alumnos.php -->
            <a href="alumnos.php?accion=editar&id=<?= $fila['id'] ?>" class="btn btn-primary btn-small" title="Editar alumno">✏️</a>
            <!-- Eliminar se procesa arriba en tablas.php (eliminar_alumno) -->
            <a href="tablas.php?vista=tutores_registrados&eliminar_alumno=<?= $fila['id'] ?>"
               class="btn btn-danger btn-small"
               title="Eliminar alumno"
               onclick="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($fila['nombre'])) ?>? Esto también borrará su historial de asistencia.')">🗑️</a>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <style>
    /* Candado del Chat ID: cerrado = solo texto; abierto = formulario de edición */
    .chat-id-bloqueado { display: flex; align-items: center; gap: 8px; }
    .chat-id-edicion   { display: none; align-items: center; gap: 6px; flex-wrap: wrap; margin: 0; }
    .chat-id-celda.abierta .chat-id-bloqueado { display: none; }
    .chat-id-celda.abierta .chat-id-edicion   { display: flex; }
    .chat-id-campo { width: 170px; padding: 5px 8px; font-family: monospace; }
  </style>

  <script>
  (function () {
    var tabla = document.getElementById('tablaDatos');
    if (!tabla) return;

    function cerrar(celda) {
      // Vuelve a bloquear y descarta lo que se haya escrito sin guardar
      celda.classList.remove('abierta');
      var campo = celda.querySelector('.chat-id-campo');
      if (campo) campo.value = campo.defaultValue;
    }

    function abrir(celda) {
      // Solo un candado abierto a la vez: se cierran los demás
      Array.prototype.forEach.call(tabla.querySelectorAll('.chat-id-celda.abierta'), function (otra) {
        if (otra !== celda) cerrar(otra);
      });
      celda.classList.add('abierta');
      var campo = celda.querySelector('.chat-id-campo');
      campo.focus();
      campo.select();
    }

    tabla.addEventListener('click', function (e) {
      var candado = e.target.closest('.btn-candado');
      if (candado) { abrir(candado.closest('.chat-id-celda')); return; }
      var cancelar = e.target.closest('.btn-cancelar-candado');
      if (cancelar) cerrar(cancelar.closest('.chat-id-celda'));
    });

    // Esc vuelve a cerrar el candado
    tabla.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      var abierta = tabla.querySelector('.chat-id-celda.abierta');
      if (abierta) cerrar(abierta);
    });

    // Al guardar: si no cambió nada, solo se cierra; si cambió, se pide confirmación
    tabla.addEventListener('submit', function (e) {
      var campo = e.target.querySelector('.chat-id-campo');
      if (!campo) return;
      var nuevo = campo.value.trim();
      if (nuevo === campo.defaultValue) {
        e.preventDefault();
        cerrar(e.target.closest('.chat-id-celda'));
        return;
      }
      var nombre = e.target.getAttribute('data-nombre') || 'este alumno';
      if (!confirm('¿Cambiar el Chat ID del tutor de ' + nombre + ' a ' + nuevo + '?')) {
        e.preventDefault();
      }
    });
  })();
  </script>
<?php else: ?>
  <div class="empty-state"><p>Ningún tutor se ha registrado todavía.</p></div>
<?php endif; ?>