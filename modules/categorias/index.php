<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

$q = trim($_GET['q'] ?? '');

$stmt = db()->prepare(
    "SELECT c.id_categoria, c.nombre, c.descripcion,
            (SELECT COUNT(*) FROM equipos e WHERE e.id_categoria = c.id_categoria) AS total_equipos
     FROM categorias c
     WHERE c.nombre LIKE :q1 OR c.descripcion LIKE :q2
     ORDER BY c.nombre"
);
$stmt->execute([':q1' => "%$q%", ':q2' => "%$q%"]);
$categorias = $stmt->fetchAll();

$titulo = 'Categorías';
$activo = 'categorias';
require __DIR__ . '/../../includes/header.php';
page_head('Categorías', '<a class="btn btn-primary" href="form.php">Registrar categoría</a>');
?>
<form class="filtros" method="get">
  <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar por nombre o descripción" aria-label="Buscar categorías">
  <button class="btn btn-outline-primary">Buscar</button>
  <?php if ($q !== ''): ?><a class="btn btn-link" href="index.php">Quitar búsqueda</a><?php endif; ?>
</form>

<?php if (!$categorias): ?>
  <p class="vacio">No hay categorías que coincidan. Pruebe otra búsqueda o registre una nueva.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table tabla align-middle">
      <thead><tr><th>Nombre</th><th>Descripción</th><th>Equipos</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($categorias as $c): ?>
        <tr>
          <td><strong><?= e($c['nombre']) ?></strong></td>
          <td><?= e($c['descripcion'] ?: '—') ?></td>
          <td><?= (int) $c['total_equipos'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int) $c['id_categoria'] ?>">Editar</a>
            <?= form_eliminar('modules/categorias/eliminar.php', (int) $c['id_categoria'], '¿Eliminar la categoría ' . $c['nombre'] . '?') ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
