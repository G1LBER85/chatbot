<?php
require_once __DIR__ . '/login/php/inicio/auth.php';
require '../conexion.php';

$paginaActual = 'administradores';
$accion = $_GET['accion'] ?? '';
$mensaje = '';
$tipo_mensaje = '';

// [CARGAR DATOS PARA EDITAR]
$adminEdit = null;
if ($accion === 'editar' && isset($_GET['id'])) {
    $idEdit = (int)$_GET['id'];
    $stmtEdit = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmtEdit->bind_param("i", $idEdit);
    $stmtEdit->execute();
    $adminEdit = $stmtEdit->get_result()->fetch_assoc();
    $stmtEdit->close();
}

// [PROCESAR FORMULARIO: AGREGAR O EDITAR]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $usuario = trim($_POST['usuario']);
    $contrasena = trim($_POST['contrasena']);
    
    // Asignación de permisos
    if (isset($_POST['permiso_todo']) && $_POST['permiso_todo'] === 'todo') {
        $permisos = 'todo';
    } else {
        $permisos = isset($_POST['permisos']) ? implode(',', $_POST['permisos']) : '';
    }

    if (isset($_POST['guardar_edicion']) && isset($_POST['id_admin'])) {
        // ACTUALIZAR REGISTRO
        $idAdmin = (int)$_POST['id_admin'];

        if (!empty($contrasena)) {
            $passHash = password_hash($contrasena, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre_completo = ?, correo = ?, usuario = ?, contrasena = ?, permisos = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $nombre, $correo, $usuario, $passHash, $permisos, $idAdmin);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre_completo = ?, correo = ?, usuario = ?, permisos = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $nombre, $correo, $usuario, $permisos, $idAdmin);
        }

        if ($stmt->execute()) {
            header("Location: administradores.php?editado=1");
            exit;
        } else {
            $mensaje = "❌ Error al actualizar el administrador";
            $tipo_mensaje = "error";
            $accion = 'editar';
        }

    } elseif (isset($_POST['agregar'])) {
        // INSERTAR NUEVO REGISTRO
        if ($nombre && $correo && $usuario && $contrasena) {
            $stmtDup = $conn->prepare("SELECT id FROM usuarios WHERE (usuario = ? OR correo = ?) LIMIT 1");
            $stmtDup->bind_param("ss", $usuario, $correo);
            $stmtDup->execute();
            $existente = $stmtDup->get_result()->fetch_assoc();
            $stmtDup->close();

            if ($existente) {
                $mensaje = "⚠️ El usuario o correo electrónico ya se encuentra registrado";
                $tipo_mensaje = "warning";
                $accion = 'nuevo';
            } else {
                $passHash = password_hash($contrasena, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre_completo, correo, usuario, contrasena, permisos) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $nombre, $correo, $usuario, $passHash, $permisos);

                if ($stmt->execute()) {
                    header("Location: administradores.php?agregado=1");
                    exit;
                } else {
                    $mensaje = "❌ Error al agregar administrador";
                    $tipo_mensaje = "error";
                    $accion = 'nuevo';
                }
            }
        } else {
            $mensaje = "⚠️ Completa todos los campos obligatorios";
            $tipo_mensaje = "warning";
            $accion = 'nuevo';
        }
    }
}

// [PROCESO: ELIMINAR ADMINISTRADOR]
if (isset($_GET['eliminar'])) {
    $idEliminar = (int)$_GET['eliminar'];
    $stmtDel = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmtDel->bind_param("i", $idEliminar);
    if ($stmtDel->execute()) {
        header("Location: administradores.php?eliminado=1");
        exit;
    }
}

// Mensajes dinámicos
if (isset($_GET['agregado'])) { $mensaje = "✅ Administrador agregado correctamente"; $tipo_mensaje = "success"; }
if (isset($_GET['editado'])) { $mensaje = "✅ Administrador actualizado correctamente"; $tipo_mensaje = "success"; }
if (isset($_GET['eliminado'])) { $mensaje = "✅ Administrador eliminado correctamente"; $tipo_mensaje = "success"; }

// Obtener administradores
$administradores = null;
if ($accion !== 'nuevo' && $accion !== 'editar') {
    $administradores = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");
}

$mostrandoFormulario = ($accion === 'nuevo' || $accion === 'editar');

$permisosActuales = [];
if ($adminEdit && !empty($adminEdit['permisos'])) {
    $permisosActuales = explode(',', $adminEdit['permisos']);
}
$esAccesoTotal = ($adminEdit && ($adminEdit['permisos'] === 'todo' || in_array('todo', $permisosActuales, true)));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administradores — Panel ChecaBot</title>
  <link rel="stylesheet" href="css/panel.css">
</head>
<body>

<div class="layout">

  <?php include '../sidebar/sidebar.php'; ?>

  <main class="contenido">

    <?php if ($mensaje): ?>
      <div class="mensaje <?= $tipo_mensaje ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if (!$mostrandoFormulario): ?>

      <!-- INCLUSIÓN DE LA TABLA DESDE LA CARPETA TABLAS -->
      <?php include 'tablas/tabla_administradores.php'; ?>

    <?php else: ?>

      <!-- [FORMULARIO: NUEVO / EDITAR] -->
      <div class="panel-header">
        <div>
          <h1><?= $accion === 'editar' ? '✏️ Editar Administrador' : '➕ Agregar Nuevo Administrador' ?></h1>
          <p><?= $accion === 'editar' ? 'Modifica los datos o permisos de la cuenta' : 'Todos los campos son obligatorios' ?></p>
        </div>
      </div>

      <div class="form-box">
        <form method="POST">
          <?php if ($accion === 'editar'): ?>
            <input type="hidden" name="id_admin" value="<?= $adminEdit['id'] ?>">
          <?php endif; ?>

          <div class="form-row">
            <div class="form-group">
              <label>Nombre Completo *</label>
              <input type="text" name="nombre" value="<?= htmlspecialchars($adminEdit['nombre_completo'] ?? '') ?>" placeholder="Ejemplo: Juan Pérez" required>
            </div>
            <div class="form-group">
              <label>Correo Electrónico *</label>
              <input type="email" name="correo" value="<?= htmlspecialchars($adminEdit['correo'] ?? '') ?>" placeholder="correo@ejemplo.com" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Usuario *</label>
              <input type="text" name="usuario" value="<?= htmlspecialchars($adminEdit['usuario'] ?? '') ?>" placeholder="Ejemplo: admin_juan" required>
            </div>
            
            <!-- CAMPO CONTRASEÑA CON OPCIÓN MOSTRAR/OCULTAR -->
            <div class="form-group">
              <label>Contraseña <?= $accion === 'editar' ? '(Opcional)' : '*' ?></label>
              <div style="position: relative; display: flex; align-items: center;">
                <input 
                  type="password" 
                  name="contrasena" 
                  id="inputContrasena"
                  placeholder="<?= $accion === 'editar' ? 'Escribe una nueva para cambiarla' : '••••••••' ?>" 
                  <?= $accion === 'nuevo' ? 'required' : '' ?>
                  style="width: 100%; padding-right: 40px;"
                >
                <button 
                  type="button" 
                  onclick="togglePassword()" 
                  style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; font-size: 16px;"
                  title="Mostrar / Ocultar contraseña"
                >
                  👁️
                </button>
              </div>
            </div>
          </div>

          <!-- PERMISOS -->
          <div class="form-group" style="margin-top: 15px;">
            <label>Permisos de acceso en el Panel *</label>
            <small style="display:block; margin-bottom:10px; color:#666;">Selecciona las secciones permitidas para esta cuenta:</small>
            
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 10px;">
              <label style="font-weight: bold; color: #0d9488; cursor: pointer;">
                <input type="checkbox" name="permiso_todo" value="todo" id="perm_todo" onchange="marcarTodos(this)" <?= $esAccesoTotal ? 'checked' : '' ?>> 🌐 Acceso Total (Todo)
              </label>
              <hr style="border: 0; border-top: 1px solid #cbd5e1; margin: 5px 0;">

              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                <?php 
                  $modulos = [
                    'dashboard' => '🏠 Dashboard',
                    'alumnos' => '🎓 Alumnos',
                    'tablas' => '📊 Tablas',
                    'administradores' => '👤 Administradores',
                    'telegram' => '🤖 Telegram',
                    'importar' => '📋 Importar Alumnos',
                    'imagenes' => '🖼️ Cargar Imágenes'
                  ];
                  foreach ($modulos as $key => $label):
                    $checked = ($esAccesoTotal || in_array($key, $permisosActuales, true)) ? 'checked' : '';
                ?>
                  <label><input type="checkbox" name="permisos[]" value="<?= $key ?>" class="perm-item" <?= $checked ?>> <?= $label ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" name="<?= $accion === 'editar' ? 'guardar_edicion' : 'agregar' ?>" value="1" class="btn btn-primary">
              <?= $accion === 'editar' ? '💾 Guardar Cambios' : '➕ Agregar Administrador' ?>
            </button>
            <a href="administradores.php" class="btn btn-secondary">← Cancelar</a>
          </div>
        </form>
      </div>

      <script>
        function marcarTodos(source) {
          const checkboxes = document.querySelectorAll('.perm-item');
          checkboxes.forEach(cb => cb.checked = source.checked);
        }

        function togglePassword() {
          const input = document.getElementById('inputContrasena');
          input.type = (input.type === 'password') ? 'text' : 'password';
        }
      </script>
    <?php endif; ?>

  </main>
</div>

</body>
</html>