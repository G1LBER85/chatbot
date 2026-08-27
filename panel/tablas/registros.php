<?php
// [CONSULTA: REGISTRO A EDITAR] ────────────────────────────────
// Si venimos de darle clic a "Editar" en una fila, traemos ese
// registro puntual (con el nombre del alumno, solo para mostrarlo
// en el título del formulario) para precargar el formulario de abajo.
$idEditando = isset($_GET['editar_registro']) ? intval($_GET['editar_registro']) : null;
$registroEditar = null;

if ($idEditando) {
    $stmtEditar = $conn->prepare("
        SELECT r.id, r.tipo, r.fecha_hora, a.nombre
        FROM registros r
        INNER JOIN alumnos a ON a.id = r.alumno_id
        WHERE r.id = ?
    ");
    $stmtEditar->bind_param("i", $idEditando);
    $stmtEditar->execute();
    $registroEditar = $stmtEditar->get_result()->fetch_assoc();
    $stmtEditar->close();
}

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

<?php if ($registroEditar): ?>
  <!-- [FORMULARIO: EDITAR REGISTRO PUNTUAL] ────────────────────
       Permite corregir el tipo (entrada/salida) o la hora de un
       registro capturado por error. Se envía por POST a tablas.php,
       que es quien procesa la actualización (ver arriba en ese archivo). -->
  <div class="form-box">
    <h3 style="margin-bottom: 15px;">✏️ Editar registro de <?= htmlspecialchars($registroEditar['nombre']) ?></h3>
    <form method="POST" action="tablas.php?vista=registros">
      <input type="hidden" name="id" value="<?= $registroEditar['id'] ?>">
      <div class="form-row">
        <div class="form-group">
          <label>Tipo</label>
          <select name="tipo">
            <option value="entrada" <?= $registroEditar['tipo'] === 'entrada' ? 'selected' : '' ?>>Entrada</option>
            <option value="salida" <?= $registroEditar['tipo'] === 'salida' ? 'selected' : '' ?>>Salida</option>
          </select>
        </div>
        <div class="form-group">
          <label>Fecha y hora</label>
          <input type="datetime-local" name="fecha_hora" value="<?= date('Y-m-d\TH:i', strtotime($registroEditar['fecha_hora'])) ?>" required>
        </div>
      </div>
      <div style="display:flex; gap:10px;">
        <button type="submit" name="actualizar_registro" value="1" class="btn btn-primary">💾 Guardar Cambios</button>
        <a href="tablas.php?vista=registros" class="btn btn-secondary">← Cancelar</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if ($registrosRecientes->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Alumno</th>
        <th>Grado/Grupo</th>
        <th>Tipo</th>
        <th>Fecha y hora</th>
        <th>Acciones</th>
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
        <td>
          <div style="display:flex; gap:8px;">
            <!-- Editar: carga el formulario de arriba con este registro -->
            <a href="tablas.php?vista=registros&editar_registro=<?= $fila['id'] ?>" class="btn btn-primary btn-small" title="Editar registro">✏️</a>
            <!-- Eliminar: borra solo esta fila de asistencia, procesado arriba en tablas.php -->
            <a href="tablas.php?vista=registros&eliminar_registro=<?= $fila['id'] ?>"
               class="btn btn-danger btn-small"
               title="Eliminar registro"
               onclick="return confirm('¿Eliminar este registro de asistencia?')">🗑️</a>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="empty-state"><p>No hay registros todavía.</p></div>
<?php endif; ?>
