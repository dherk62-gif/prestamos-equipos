<?php
require __DIR__ . '/../../includes/init.php';
require_login();

$pdo   = db();
$staff = es_staff();
$admin = tiene_rol('administrador');

$q   = trim($_GET['q'] ?? '');
$cat = (int) ($_GET['categoria'] ?? 0);
$est = $_GET['estado'] ?? '';
$estados = ['disponible' => 'Disponible', 'prestado' => 'Prestado', 'mantenimiento' => 'En mantenimiento', 'baja' => 'De baja'];
if (!isset($estados[$est])) {
    $est = '';
}

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = "(e.codigo LIKE :q1 OR e.nombre LIKE :q2 OR e.marca LIKE :q3 OR e.modelo LIKE :q4)";
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = "%$q%";
}
if ($cat) {
    $where[] = "e.id_categoria = :cat";
    $params[':cat'] = $cat;
}
if ($est !== '') {
    $where[] = "e.estado = :est";
    $params[':est'] = $est;
}

$sql = "SELECT e.id_equipo, e.codigo, e.nombre, e.marca, e.modelo, e.estado, c.nombre AS categoria
        FROM equipos e
        JOIN categorias c ON c.id_categoria = e.id_categoria"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY c.nombre, e.codigo";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll();

$categorias = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre")->fetchAll();

$titulo = 'Equipos';
$activo = 'equipos';
require __DIR__ . '/../../includes/header.php';
page_head('Equipos', $staff ? '<a class="btn btn-primary" href="form.php">Registrar equipo</a>' : '');
?>
<form class="filtros" method="get">
  <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar por código, nombre, marca o modelo" aria-label="Buscar equipos">
  <select name="categoria" class="form-select" aria-label="Filtrar por categoría">
    <option value="0">Todas las categorías</option>
    <?php foreach ($categorias as $c): ?>
      <option value="<?= (int) $c['id_categoria'] ?>"<?= selected($cat, $c['id_categoria']) ?>><?= e($c['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="">Todos los estados</option>
    <?php foreach ($estados as $clave => $etiqueta): ?>
      <option value="<?= e($clave) ?>"<?= selected($est, $clave) ?>><?= e($etiqueta) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-primary">Filtrar</button>
  <?php if ($q !== '' || $cat || $est !== ''): ?><a class="btn btn-link" href="index.php">Quitar filtros</a><?php endif; ?>
</form>

<?php if (!$equipos): ?>
  <p class="vacio">No hay equipos que coincidan con estos filtros.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table tabla align-middle">
      <thead>
        <tr>
          <th>Código</th><th>Equipo</th><th>Marca y modelo</th><th>Categoría</th><th>Estado</th>
          <?php if ($staff): ?><th class="text-end">Acciones</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($equipos as $e): ?>
        <tr>
          <td><span class="codigo"><?= e($e['codigo']) ?></span></td>
          <td><?= e($e['nombre']) ?></td>
          <td><?= e(trim($e['marca'] . ' ' . $e['modelo']) ?: '—') ?></td>
          <td><?= e($e['categoria']) ?></td>
          <td><?= estado_badge($e['estado']) ?></td>
          <?php if ($staff): ?>
            <td class="text-end">
              <?php if ($e['estado'] === 'disponible'): ?>
                <a class="btn btn-sm btn-primary" href="<?= e(url('modules/prestamos/nuevo.php?equipo=' . (int) $e['id_equipo'])) ?>">Prestar</a>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int) $e['id_equipo'] ?>">Editar</a>
              <?php if ($admin): ?>
                <?= form_eliminar('modules/equipos/eliminar.php', (int) $e['id_equipo'], '¿Eliminar el equipo ' . $e['codigo'] . '?') ?>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
