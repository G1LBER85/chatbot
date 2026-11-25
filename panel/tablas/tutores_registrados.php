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
          <div style="display:flex; align-items:center; gap:8px;">
            <span><?= htmlspecialchars($fila['tutor_chat_id']) ?></span>
            <!-- Quitar el Chat ID con modal de SweetAlert2 -->
            <button type="button" class="btn btn-danger btn-small" title="Quitar Chat ID" onclick="Swal.fire({
              title: '¿Quitar el Chat ID de <?= htmlspecialchars(addslashes($fila['nombre'])) ?>?',
              text: 'El alumno pasará a Tutores pendientes.',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#d33',
              cancelButtonColor: '#3085d6',
              confirmButtonText: 'Aceptar',
              cancelButtonText: 'Cancelar'
            }).then((r) => { if (r.isConfirmed) window.location.href = 'tablas.php?vista=tutores_registrados&eliminar_chat_id=<?= $fila['id'] ?>'; })">🗑️</button>
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
<?php else: ?>
  <div class="empty-state"><p>Ningún tutor se ha registrado todavía.</p></div>
<?php endif; ?>