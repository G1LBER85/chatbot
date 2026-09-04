<?php
// [CONSULTA: GRADOS Y GRUPOS] ──────────────────────────────────
// Lista de alumnos activos. Se agrega "id" (antes no venía en el
// SELECT) porque ahora lo necesitamos para armar los enlaces de
// Editar/Eliminar de cada fila.
// ORDER BY grado DESC: se pidió que las tablas se ordenen de mayor
// a menor según el grado; grupo y nombre quedan como orden secundario
// para que dentro de un mismo grado se vea ordenado y legible.
// $conn viene de tablas.php, que ya hizo require '../conexion.php'.
$gradosGrupos = $conn->query("
    SELECT id, nombre, grado, grupo
    FROM alumnos
    WHERE activo = 1
    ORDER BY grado DESC, grupo ASC, nombre ASC
");
?>

<?php if ($gradosGrupos->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Grado</th>
        <th>Grupo</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($fila = $gradosGrupos->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['grado']) ?></td>
        <td><?= htmlspecialchars($fila['grupo'] ?: '—') ?></td>
        <td>
          <div style="display:flex; gap:8px;">
            <!-- Editar reutiliza el mismo formulario de alumnos.php -->
            <a href="alumnos.php?accion=editar&id=<?= $fila['id'] ?>" class="btn btn-primary btn-small" title="Editar alumno">✏️</a>
            <!-- Eliminar se procesa arriba en tablas.php (eliminar_alumno) -->
            <a href="tablas.php?vista=grados_grupos&eliminar_alumno=<?= $fila['id'] ?>"
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
  <div class="empty-state"><p>Aún no hay alumnos activos.</p></div>
<?php endif; ?>
