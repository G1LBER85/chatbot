<?php
// [CONSULTA: GRADOS Y GRUPOS]
// Lista de alumnos activos ordenada por grado y grupo.
// $conn viene de tablas.php, que ya hizo require 'conexion.php'.
$gradosGrupos = $conn->query("
    SELECT nombre, grado, grupo
    FROM alumnos
    WHERE activo = 1
    ORDER BY grado, grupo, nombre
");
?>

<?php if ($gradosGrupos->num_rows > 0): ?>
  <table>
    <thead><tr><th>Nombre</th><th>Grado</th><th>Grupo</th></tr></thead>
    <tbody>
      <?php while ($fila = $gradosGrupos->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['grado']) ?></td>
        <td><?= htmlspecialchars($fila['grupo'] ?: '—') ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="empty-state"><p>Aún no hay alumnos activos.</p></div>
<?php endif; ?>
