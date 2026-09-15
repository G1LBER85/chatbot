<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);
    $p1 = trim($_POST['p1']);
    $p2 = trim($_POST['p2']);

    if ($p1 === $p2) {
        $passHash = password_hash($p1, PASSWORD_DEFAULT);

        // Actualizar contraseña en la tabla usuarios usando la columna 'correo'
        $stmt = $conn->prepare("UPDATE usuarios SET contrasena = ? WHERE correo = ?");
        $stmt->bind_param("ss", $passHash, $correo);

        if ($stmt->execute()) {
            // Limpiar solicitudes antiguas en la tabla passwords
            $stmtDel = $conn->prepare("DELETE FROM contrasena WHERE correo = ?");
            $stmtDel->bind_param("s", $correo);
            $stmtDel->execute();
            $stmtDel->close();

            echo "<script>
                    alert('✅ Contraseña actualizada correctamente.');
                    window.location.href = '/../../index.php';
                  </script>";
        } else {
            echo "❌ Error al actualizar la contraseña.";
        }
        $stmt->close();

    } else {
        echo "<script>
                alert('⚠️ Las contraseñas no coinciden.');
                window.history.back();
              </script>";
    }
}
?>