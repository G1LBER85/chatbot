<?php
// [CONSULTA: TUTORES PENDIENTES] ───────────────────────────────
// Alumnos cuyo tutor AÚN NO ha hecho /start en Telegram
// (tutor_chat_id es NULL). SELECT * porque necesitamos "id" para
// los enlaces de Editar/Eliminar además de los datos ya mostrados.
// ORDER BY grado DESC: se ordena de mayor a menor según el grado.
$tutoresPendientes = $conn->query("
    SELECT * FROM alumnos
    WHERE tutor_chat_id IS NULL
    ORDER BY grado DESC, nombre ASC
");
?>

<?php if ($tutoresPendientes->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Alumno</th>
        <th>Grado/Grupo</th>
        <th>CURP</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($fila = $tutoresPendientes->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['grado']) ?> <?= htmlspecialchars($fila['grupo'] ?? '') ?></td>
        <td><span style="font-family: monospace;"><?= $fila['CURP'] ? htmlspecialchars($fila['CURP']) : '<span style="color:#bbb;">Sin capturar</span>' ?></span></td>
        <td>
          <div style="display:flex; gap:8px;">
            <!-- Editar reutiliza el mismo formulario de alumnos.php -->
            <a href="alumnos.php?accion=editar&id=<?= $fila['id'] ?>" class="btn btn-primary btn-small" title="Editar alumno">✏️</a>
            <!-- Eliminar se procesa arriba en tablas.php (eliminar_alumno) -->
            <a href="tablas.php?vista=tutores_pendientes&eliminar_alumno=<?= $fila['id'] ?>"
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
  <div class="empty-state"><p>Todos los tutores ya se registraron. 🎉</p></div>
<?php endif; ?>
