<?php
// [CONSULTA: TUTORES PENDIENTES]
// Alumnos cuyo tutor AÚN NO ha hecho /start en Telegram.
$tutoresPendientes = $conn->query("
    SELECT * FROM alumnos
    WHERE tutor_chat_id IS NULL
    ORDER BY nombre
");
?>

<?php if ($tutoresPendientes->num_rows > 0): ?>
  <table>
    <thead><tr><th>Alumno</th><th>Grado/Grupo</th><th>CURP</th></tr></thead>
    <tbody>
      <?php while ($fila = $tutoresPendientes->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($fila['nombre']) ?></td>
        <td><?= htmlspecialchars($fila['grado']) ?> <?= htmlspecialchars($fila['grupo'] ?? '') ?></td>
        <td><span style="font-family: monospace;"><?= $fila['CURP'] ? htmlspecialchars($fila['CURP']) : '<span style="color:#bbb;">Sin capturar</span>' ?></span></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="empty-state"><p>Todos los tutores ya se registraron. 🎉</p></div>
<?php endif; ?>
