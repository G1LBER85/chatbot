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
  <table>
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
      <tr>
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['grado']) ?></td>
        <td>
          <div style="display:flex; align-items:center; gap:8px;">
            <span><?= htmlspecialchars($fila['tutor_chat_id']) ?></span>
            <!-- Quitar el Chat ID solo desvincula al tutor (tutor_chat_id = NULL);
                 el alumno NO se borra, solo pasa a la vista de Tutores pendientes.
                 Telegram vuelve a asignarlo solo si el tutor hace /start de nuevo. -->
            <a href="tablas.php?vista=tutores_registrados&eliminar_chat_id=<?= $fila['id'] ?>"
               class="btn btn-danger btn-small"
               title="Quitar Chat ID"
               onclick="return confirm('¿Quitar el Chat ID de <?= htmlspecialchars(addslashes($fila['nombre'])) ?>? El alumno pasará a Tutores pendientes.')">🗑️</a>
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
<?php else: ?>
  <div class="empty-state"><p>Ningún tutor se ha registrado todavía.</p></div>
<?php endif; ?>