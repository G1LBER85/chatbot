<?php
// [CONSULTA: REGISTROS] ────────────────────────────────────────
// Últimos 100 registros de entrada/salida.
// ORDER BY a.grado DESC: se pidió ordenar de mayor a menor según
// el grado del alumno; dentro del mismo grado se mantiene el más
// reciente primero (r.id DESC) para no perder el orden cronológico.
$registrosRecientes = $conn->query("
    SELECT r.id, r.tipo, r.fecha_hora, a.nombre, a.grado, a.grupo
    FROM registros r
    INNER JOIN alumnos a ON a.id = r.alumno_id
    ORDER BY a.grado DESC, r.id DESC
    LIMIT 100
");
?>

<?php if ($registrosRecientes->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Alumno</th>
        <th>Grado/Grupo</th>
        <th>Tipo</th>
        <th>Fecha y hora</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($fila = $registrosRecientes->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['grado']) ?> <?= htmlspecialchars($fila['grupo'] ?? '') ?></td>
        <td>
          <span class="badge badge-<?= $fila['tipo'] ?>">
            <?= $fila['tipo'] === 'entrada' ? '✅ Entrada' : '🚪 Salida' ?>
          </span>
        </td>
        <td><?= date('d/m/Y h:i A', strtotime($fila['fecha_hora'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="empty-state"><p>No hay registros todavía.</p></div>
<?php endif; ?>