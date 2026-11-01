<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

$pdo   = db();
$admin = tiene_rol('administrador');
$yo    = usuario_actual()['id'];

$q   = trim($_GET['q'] ?? '');
$rol = $_GET['rol'] ?? '';
$roles = ['administrador' => 'Administrador', 'encargado' => 'Encargado', 'usuario' => 'Usuario'];
if (!isset($roles[$rol])) {
    $rol = '';
}

$where  = ["(u.nombre LIKE :q1 OR u.apellidos LIKE :q2 OR u.cedula LIKE :q3 OR u.correo LIKE :q4)"];
$params = [':q1' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%", ':q4' => "%$q%"];
if ($rol !== '') {
    $where[] = "r.nombre = :rol";
    $params[':rol'] = $rol;
}

$stmt = $pdo->prepare(
    "SELECT u.id_usuario, u.nombre, u.apellidos, u.cedula, u.correo, u.telefono, u.estado, r.nombre AS rol,
            (SELECT COUNT(*) FROM multas m WHERE m.id_usuario = u.id_usuario AND m.estado = 'pendiente') AS multas_pendientes
     FROM usuarios u
     JOIN roles r ON r.id_rol = u.id_rol
     WHERE " . implode(' AND ', $where) . "
     ORDER BY u.nombre, u.apellidos"
);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$titulo = 'Usuarios';
$activo = 'usuarios';
require __DIR__ . '/../../includes/header.php';
page_head('Usuarios', '<a class="btn btn-primary" href="form.php">Registrar usuario</a>');
?>
<form class="filtros" method="get">
  <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar por nombre, cédula o correo" aria-label="Buscar usuarios">
  <select name="rol" class="form-select" aria-label="Filtrar por rol">
    <option value="">Todos los roles</option>
    <?php foreach ($roles as $clave => $etiqueta): ?>
      <option value="<?= e($clave) ?>"<?= selected($rol, $clave) ?>><?= e($etiqueta) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-primary">Filtrar</button>
  <?php if ($q !== '' || $rol !== ''): ?><a class="btn btn-link" href="index.php">Quitar filtros</a><?php endif; ?>
</form>

<?php if (!$usuarios): ?>
  <p class="vacio">No hay usuarios que coincidan con estos filtros.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table tabla align-middle">
      <thead><tr><th>Nombre</th><th>Cédula</th><th>Contacto</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($usuarios as $f): ?>
        <?php $puedeEditar = $admin || $f['rol'] === 'usuario'; ?>
        <tr>
          <td>
            <strong><?= e($f['nombre'] . ' ' . $f['apellidos']) ?></strong>
            <?php if ((int) $f['multas_pendientes'] > 0): ?><br><small class="text-danger">Multas pendientes: <?= (int) $f['multas_pendientes'] ?></small><?php endif; ?>
          </td>
          <td><?= e($f['cedula']) ?></td>
          <td><?= e($f['correo']) ?><br><small class="text-muted"><?= e($f['telefono'] ?: 'Sin teléfono') ?></small></td>
          <td><?= e($roles[$f['rol']] ?? $f['rol']) ?></td>
          <td><?= estado_badge($f['estado']) ?></td>
          <td class="text-end">
            <?php if ($puedeEditar): ?>
              <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int) $f['id_usuario'] ?>">Editar</a>
            <?php endif; ?>
            <?php if ($admin && (int) $f['id_usuario'] !== $yo): ?>
              <?= form_eliminar('modules/usuarios/eliminar.php', (int) $f['id_usuario'], '¿Eliminar a ' . $f['nombre'] . ' ' . $f['apellidos'] . '?') ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
