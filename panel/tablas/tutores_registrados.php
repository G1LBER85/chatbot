<?php
// [CONSULTA: TUTORES REGISTRADOS]
// Alumnos cuyo tutor ya hizo /start en Telegram.
$tutoresRegistrados = $conn->query("
    SELECT * FROM alumnos
    WHERE tutor_chat_id IS NOT NULL
    ORDER BY nombre
");
?>

<?php if ($tutoresRegistrados->num_rows > 0): ?>
  <table>
    <thead><tr><th>Alumno</th><th>Chat ID</th></tr></thead>
    <tbody>
      <?php while ($fila = $tutoresRegistrados->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['tutor_chat_id']) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="empty-state"><p>Ningún tutor se ha registrado todavía.</p></div>
<?php endif; ?>
