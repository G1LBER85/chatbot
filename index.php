<?php
require 'includes/conexion.php';

$alumnos = $conn->query("SELECT * FROM alumnos WHERE activo = 1");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>ChecaBot - Control de Asistencia</title>
  <style>
    body { font-family: Arial; background: #f0f0f0; padding: 20px; }
    h1 { color: #2c3e50; }
    table { width: 100%; border-collapse: collapse; background: white; }
    th { background: #2c3e50; color: white; padding: 10px; }
    td { padding: 10px; border-bottom: 1px solid #ddd; text-align: center; }
    .btn-entrada { background: #27ae60; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; }
    .btn-salida  { background: #e74c3c; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; }
  </style>
</head>
<body>

<h1>📋 ChecaBot — Control de Asistencia</h1>

<?php if(isset($_GET['ok'])): ?>
<div style="background:#27ae60;color:white;padding:15px;border-radius:8px;margin-bottom:20px">
  ✅ <?= urldecode($_GET['nombre']) ?> registró su <?= $_GET['tipo'] ?> correctamente. 
  📱 WhatsApp enviado al tutor.
</div>
<?php endif; ?>

<table>
  <tr>
    <th>Nombre</th>
    <th>Grado</th>
    <th>Acciones</th>
  </tr>
  <?php while($alumno = $alumnos->fetch_assoc()): ?>
  <tr>
    <td><?= $alumno['nombre'] ?></td>
    <td><?= $alumno['grado'] ?></td>
    <td>
      <form method="POST" action="registrar.php" style="display:inline">
        <input type="hidden" name="alumno_id" value="<?= $alumno['id'] ?>">
        <input type="hidden" name="tipo" value="entrada">
        <button class="btn-entrada" type="submit">✅ Entrada</button>
      </form>
      <form method="POST" action="registrar.php" style="display:inline">
        <input type="hidden" name="alumno_id" value="<?= $alumno['id'] ?>">
        <input type="hidden" name="tipo" value="salida">
        <button class="btn-salida" type="submit">🚪 Salida</button>
      </form>
    </td>
  </tr>
  <?php endwhile; ?>
</table>

</body>
</html>
