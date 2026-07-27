<?php
require 'conexion.php';

$accion = $_GET['accion'] ?? '';
$mensaje = '';
$tipo_mensaje = '';

// AGREGAR ALUMNO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre']);
    $grado = trim($_POST['grado']);
    $tutor_telefono = trim($_POST['tutor_telefono']);
    
    if ($nombre && $grado && $tutor_telefono) {
        $stmt = $conn->prepare("INSERT INTO alumnos (nombre, grado, tutor_telefono) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $grado, $tutor_telefono);
        
        if ($stmt->execute()) {
            $alumno_id = $conn->insert_id;
            $codigo = "ALU" . str_pad($alumno_id, 3, "0", STR_PAD_LEFT);
            $stmt2 = $conn->prepare("UPDATE alumnos SET codigo = ? WHERE id = ?");
            $stmt2->bind_param("si", $codigo, $alumno_id);
            $stmt2->execute();
            
            $mensaje = "✅ Alumno '$nombre' agregado con código $codigo";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "❌ Error al agregar alumno";
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "⚠️ Completa todos los campos";
        $tipo_mensaje = "warning";
    }
}

// EDITAR ALUMNO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $grado = trim($_POST['grado']);
    $tutor_telefono = trim($_POST['tutor_telefono']);
    
    if ($nombre && $grado && $tutor_telefono) {
        $stmt = $conn->prepare("UPDATE alumnos SET nombre = ?, grado = ?, tutor_telefono = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nombre, $grado, $tutor_telefono, $id);
        
        if ($stmt->execute()) {
            $mensaje = "✅ Alumno actualizado correctamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "❌ Error al actualizar";
            $tipo_mensaje = "error";
        }
    }
}

// ELIMINAR ALUMNO
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    $stmt = $conn->prepare("DELETE FROM registros WHERE alumno_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $stmt2 = $conn->prepare("DELETE FROM alumnos WHERE id = ?");
    $stmt2->bind_param("i", $id);
    
    if ($stmt2->execute()) {
        $mensaje = "✅ Alumno eliminado correctamente";
        $tipo_mensaje = "success";
        header("Location: admin.php");
        exit;
    }
}

// OBTENER ALUMNOS
$alumnos = $conn->query("SELECT * FROM alumnos ORDER BY id DESC");

// OBTENER UN ALUMNO PARA EDITAR
$alumno_edit = null;
if ($accion === 'editar' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM alumnos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $alumno_edit = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administrador — ChecaBot</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; }
    
    header { 
      background: #2c3e50; 
      color: white; 
      padding: 20px; 
      border-radius: 8px 8px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    header h1 { font-size: 28px; }
    header a { 
      background: #048A81; 
      color: white; 
      padding: 10px 20px; 
      text-decoration: none; 
      border-radius: 5px;
      font-weight: bold;
    }
    header a:hover { background: #037773; }
    
    .mensaje {
      margin: 20px 0;
      padding: 15px;
      border-radius: 8px;
      font-weight: bold;
    }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    
    .tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      border-bottom: 2px solid #ddd;
    }
    .tab {
      padding: 12px 20px;
      cursor: pointer;
      background: white;
      border: none;
      font-size: 16px;
      font-weight: bold;
      color: #666;
      border-bottom: 3px solid transparent;
    }
    .tab.active {
      color: #048A81;
      border-bottom-color: #048A81;
    }
    .tab:hover { color: #048A81; }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    .form-box {
      background: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #333;
    }
    .form-group input, .form-group select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
    }
    .form-group input:focus, .form-group select:focus {
      outline: none;
      border-color: #048A81;
      box-shadow: 0 0 5px rgba(4, 138, 129, 0.3);
    }
    
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      font-size: 14px;
      transition: all 0.3s;
    }
    .btn-primary { background: #048A81; color: white; }
    .btn-primary:hover { background: #037773; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-danger:hover { background: #c0392b; }
    .btn-secondary { background: #95a5a6; color: white; }
    .btn-secondary:hover { background: #7f8c8d; }
    
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border-radius: 8px;
      overflow: hidden;
    }
    th {
      background: #2c3e50;
      color: white;
      padding: 15px;
      text-align: left;
      font-weight: bold;
    }
    td {
      padding: 12px 15px;
      border-bottom: 1px solid #ddd;
    }
    tr:hover { background: #f5f5f5; }
    tr:last-child td { border-bottom: none; }
    
    .badge {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
    }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    .badge-warning { background: #fff3cd; color: #856404; }
    
    .acciones {
      display: flex;
      gap: 8px;
    }
    .btn-small {
      padding: 6px 12px;
      font-size: 12px;
    }
    
    .empty-state {
      text-align: center;
      padding: 40px;
      color: #999;
    }
  </style>
</head>
<body>

<div class="container">
  <header>
    <h1>👨‍💼 Panel de Administrador</h1>
    <a href="index.php">← Volver al sistema</a>
  </header>
  
  <?php if ($mensaje): ?>
    <div class="mensaje <?= $tipo_mensaje ?>">
      <?= $mensaje ?>
    </div>
  <?php endif; ?>
  
  <div class="tabs">
    <button class="tab active" onclick="mostrarTab('alumnos')">📋 Gestionar Alumnos</button>
    <button class="tab" onclick="mostrarTab('agregar')">➕ Agregar Alumno</button>
  </div>
  
  <!-- TAB 1: LISTAR ALUMNOS -->
  <div id="alumnos" class="tab-content active">
    <div class="form-box">
      <h2 style="margin-bottom: 20px;">Alumnos Registrados</h2>
      
      <?php if ($alumnos->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Grado</th>
              <th>Código</th>
              <th>Tutor Chat ID</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($alumno = $alumnos->fetch_assoc()): ?>
            <tr>
              <td>#<?= $alumno['id'] ?></td>
              <td><strong><?= $alumno['nombre'] ?></strong></td>
              <td><?= $alumno['grado'] ?></td>
              <td>
                <span style="font-family: monospace; background: #f0f0f0; padding: 4px 8px; border-radius: 4px;">
                  <?= $alumno['codigo'] ?>
                </span>
              </td>
              <td>
                <?php if ($alumno['tutor_chat_id']): ?>
                  <span class="badge badge-success">✓ Registrado</span><br>
                  <small style="color: #666;"><?= $alumno['tutor_chat_id'] ?></small>
                <?php else: ?>
                  <span class="badge badge-warning">⏳ Pendiente</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($alumno['activo']): ?>
                  <span class="badge badge-success">Activo</span>
                <?php else: ?>
                  <span class="badge badge-danger">Inactivo</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="acciones">
                  <a href="admin.php?accion=editar&id=<?= $alumno['id'] ?>" class="btn btn-primary btn-small">✏️ Editar</a>
                  <a href="admin.php?eliminar=<?= $alumno['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('¿Eliminar a <?= addslashes($alumno['nombre']) ?>?')">🗑️ Eliminar</a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <p>No hay alumnos registrados aún.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- TAB 2: AGREGAR O EDITAR ALUMNO -->
  <div id="agregar" class="tab-content <?= $accion === 'editar' ? 'active' : '' ?>">
    <div class="form-box">
      <h2 style="margin-bottom: 20px;">
        <?= $alumno_edit ? "✏️ Editar Alumno" : "➕ Agregar Nuevo Alumno" ?>
      </h2>
      
      <form method="POST">
        <div class="form-group">
          <label>Nombre Completo *</label>
          <input type="text" name="nombre" required value="<?= $alumno_edit['nombre'] ?? '' ?>">
        </div>
        
        <div class="form-group">
          <label>Grado *</label>
          <input type="text" name="grado" placeholder="Ejemplo: 3A, 2B, 1C" required value="<?= $alumno_edit['grado'] ?? '' ?>">
        </div>
        
        <div class="form-group">
          <label>Teléfono del Tutor (Whatsapp) *</label>
          <input type="text" name="tutor_telefono" placeholder="Ejemplo: 5219622339022" required value="<?= $alumno_edit['tutor_telefono'] ?? '' ?>">
        </div>
        
        <div style="display: flex; gap: 10px;">
          <button type="submit" name="<?= $alumno_edit ? 'editar' : 'agregar' ?>" value="1" class="btn btn-primary">
            <?= $alumno_edit ? "💾 Guardar Cambios" : "➕ Agregar Alumno" ?>
          </button>
          <?php if ($alumno_edit): ?>
            <a href="admin.php" class="btn btn-secondary">← Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
      
      <?php if ($alumno_edit): ?>
        <hr style="margin: 30px 0;">
        <h3>ℹ️ Información del Alumno</h3>
        <p><strong>ID:</strong> #<?= $alumno_edit['id'] ?></p>
        <p><strong>Código:</strong> <span style="font-family: monospace; background: #f0f0f0; padding: 4px 8px; border-radius: 4px;"><?= $alumno_edit['codigo'] ?></span></p>
        <p><strong>Chat ID Tutor:</strong> <?= $alumno_edit['tutor_chat_id'] ?? 'No registrado aún' ?></p>
        <p><strong>Estado:</strong> <?= $alumno_edit['activo'] ? 'Activo' : 'Inactivo' ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function mostrarTab(tab) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
  document.getElementById(tab).classList.add('active');
  event.target.classList.add('active');
}
</script>

</body>
</html>