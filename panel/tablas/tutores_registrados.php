<?php
// [CONSULTA: TUTORES REGISTRADOS] ──────────────────────────────
// Alumnos cuyo tutor ya hizo /start en Telegram (tutor_chat_id
// tiene valor). Se usa SELECT * porque además de mostrar el nombre
// y el chat_id, necesitamos "id" y "grado" para: (1) armar los
// enlaces de Editar/Eliminar y (2) ordenar por grado descendente.
$tutoresRegistrados = $conn->query("
    SELECT * FROM alumnos
    WHERE tutor_chat_id IS NOT NULL
    ORDER BY grado ASC, nombre ASC
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
               El número SIEMPRE se ve y NO se puede editar.
               El candado permite mostrar la opción para quitarlo. -->
          <div class="chat-id-celda">
            <span class="chat-id-texto"><?= htmlspecialchars($fila['tutor_chat_id']) ?></span>

            <button type="button"
                    class="btn btn-secondary btn-small btn-candado"
                    title="Bloqueado. Clic para ver la opción de quitar el Chat ID"
                    aria-label="Desbloquear para quitar el Chat ID"
                    aria-expanded="false">🔒</button>

            <button type="button"
                    class="btn btn-danger btn-small chat-id-quitar"
                    title="Quitar Chat ID"
                    onclick="Swal.fire({
                      title: '¿Quitar el Chat ID de <?= htmlspecialchars(addslashes($fila['nombre'])) ?>?',
                      text: 'El alumno pasará a Tutores pendientes.',
                      icon: 'warning',
                      showCancelButton: true,
                      confirmButtonColor: '#d33',
                      cancelButtonColor: '#3085d6',
                      confirmButtonText: 'Aceptar',
                      cancelButtonText: 'Cancelar'
                    }).then((r) => {
                      if (r.isConfirmed) {
                        window.location.href = 'tablas.php?vista=tutores_registrados&eliminar_chat_id=<?= (int) $fila['id'] ?>';
                      }
                    })">🗑</button>
          </div>
        </td>
        <td>
          <div style="display:flex; gap:8px;">
            <!-- Editar reutiliza el mismo formulario de alumnos.php -->
            <a href="alumnos.php?accion=editar&id=<?= $fila['id'] ?>" class="btn btn-primary btn-small" title="Editar alumno">✏️</a>
            <!-- Eliminar con modal de SweetAlert2 -->
            <button type="button" class="btn btn-danger btn-small" title="Eliminar alumno" onclick="Swal.fire({
              title: '¿Eliminar a <?= htmlspecialchars(addslashes($fila['nombre'])) ?>?',
              text: 'Esta acción es irreversible y eliminará tanto los datos del alumno como su historial de asistencia.',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#d33',
              cancelButtonColor: '#3085d6',
              confirmButtonText: 'Aceptar',
              cancelButtonText: 'Cancelar'
            }).then((r) => { if (r.isConfirmed) window.location.href = 'tablas.php?vista=tutores_registrados&eliminar_alumno=<?= $fila['id'] ?>'; })">🗑️</button>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <style>
    /* Candado del Chat ID: el número siempre se ve. Cerrado = sin opciones;
       abierto = aparece solo el basurero para quitar al tutor. */
    .chat-id-celda { display: flex; align-items: center; gap: 8px; }
    .chat-id-quitar { display: none; }
    .chat-id-celda.abierta .chat-id-quitar { display: inline-block; }
  </style>

  <script>
  (function () {
    var tabla = document.getElementById('tablaDatos');
    if (!tabla) return;

    // Abre (🔓) o cierra (🔒) el candado de una celda
    function poner(celda, abierta) {
      celda.classList.toggle('abierta', abierta);
      var candado = celda.querySelector('.btn-candado');
      candado.textContent = abierta ? '🔓' : '🔒';
      candado.title = abierta
        ? 'Desbloqueado. Clic para volver a bloquear'
        : 'Bloqueado. Clic para ver la opción de quitar el Chat ID';
      candado.setAttribute('aria-expanded', abierta ? 'true' : 'false');
    }

    // Cierra todos los candados (menos "excepto", si se indica)
    function cerrarTodos(excepto) {
      Array.prototype.forEach.call(tabla.querySelectorAll('.chat-id-celda.abierta'), function (celda) {
        if (celda !== excepto) poner(celda, false);
      });
    }

    // Clic en un candado: se abre o se cierra, y solo uno queda abierto a la vez
    tabla.addEventListener('click', function (e) {
      var candado = e.target.closest('.btn-candado');
      if (!candado) return;
      var celda = candado.closest('.chat-id-celda');
      var abrir = !celda.classList.contains('abierta');
      cerrarTodos(celda);
      poner(celda, abrir);
    });

    // Clic en cualquier otro lado, o Esc: todo vuelve a quedar bloqueado
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.chat-id-celda')) cerrarTodos(null);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') cerrarTodos(null);
    });
  })();
  </script>
<?php else: ?>
  <div class="empty-state"><p>Ningún tutor se ha registrado todavía.</p></div>
<?php endif; ?>