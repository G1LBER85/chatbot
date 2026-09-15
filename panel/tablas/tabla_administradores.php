<!-- [VISTA DE LA TABLA] -->
<div class="panel-header">
  <div>
    <h1>Administradores Registrados</h1>
    <p>Consulta y gestiona las cuentas del panel</p>
  </div>
  <div>
    <a href="administradores.php?accion=nuevo" class="btn btn-primary">➕ Agregar Administrador</a>
  </div>
</div>

<?php if ($administradores && $administradores->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Nombre Completo</th>
        <th>Correo</th>
        <th>Usuario</th>
        <th>Permisos</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($admin = $administradores->fetch_assoc()): ?>
      <tr>
        <td><?= $admin['id'] ?></td>
        <td><strong><?= htmlspecialchars($admin['nombre_completo'] ?? $admin['usuario']) ?></strong></td>
        <td><?= htmlspecialchars($admin['correo'] ?? 'Sin correo') ?></td>
        <td><code><?= htmlspecialchars($admin['usuario']) ?></code></td>
        <td>
          <span class="badge badge-success">
            <?php 
              if (empty($admin['permisos']) || $admin['permisos'] === 'todo' || stristr($admin['permisos'], 'todo')) {
                  echo 'Todo';
              } else {
                  echo htmlspecialchars($admin['permisos']);
              }
            ?>
          </span>
        </td>
        <td>
          <div style="display:flex; gap:8px;">
            <a href="administradores.php?accion=editar&id=<?= $admin['id'] ?>" class="btn btn-primary btn-small">✏️</a>
            <a href="administradores.php?eliminar=<?= $admin['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('¿Deseas eliminar este administrador?')">🗑️</a>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="empty-state"><p>No hay administradores registrados aún.</p></div>
<?php endif; ?>