<?php
require __DIR__ . '/../../includes/init.php';
require_login();

$pdo   = db();
$staff = es_staff();
$yo    = usuario_actual()['id'];

$q      = trim($_GET['q'] ?? '');
$estado = $_GET['estado'] ?? 'pendiente';
$pestanas = ['pendiente' => 'Pendientes', 'pagada' => 'Pagadas', 'todas' => 'Todas'];
if (!isset($pestanas[$estado])) {
    $estado = 'pendiente';
}

$where  = [];
$params = [];
if (!$staff) {                       // Un usuario común solo ve sus propias multas
    $where[]       = "m.id_usuario = :yo";
    $params[':yo'] = $yo;
}
if ($estado !== 'todas') {
    $where[]           = "m.estado = :estado";
    $params[':estado'] = $estado;
}
if ($q !== '') {
    $where[] = "(CONCAT(u.nombre, ' ', u.apellidos) LIKE :q1 OR u.cedula LIKE :q2 OR e.codigo LIKE :q3)";
    $params[':q1'] = $params[':q2'] = $params[':q3'] = "%$q%";
}

$stmt = $pdo->prepare(
    "SELECT m.id_multa, m.dias_retraso, m.monto, m.estado, m.fecha_generada, m.fecha_pago,
            CONCAT(u.nombre, ' ', u.apellidos) AS usuario, u.cedula,
            e.codigo AS equipo_codigo, e.nombre AS equipo_nombre
     FROM multas m
     JOIN usuarios  u ON u.id_usuario  = m.id_usuario
     JOIN prestamos p ON p.id_prestamo = m.id_prestamo
     JOIN equipos   e ON e.id_equipo   = p.id_equipo"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . " ORDER BY m.fecha_generada DESC"
);
$stmt->execute($params);
$multas = $stmt->fetchAll();

$totalPendiente = 0;
foreach ($multas as $m) {
    if ($m['estado'] === 'pendiente') {
        $totalPendiente += (float) $m['monto'];
    }
}

$titulo = $staff ? 'Multas' : 'Mis multas';
$activo = 'multas';
require __DIR__ . '/../../includes/header.php';
page_head($titulo);
?>
<nav class="pestanas" aria-label="Filtrar por estado">
  <?php foreach ($pestanas as $clave => $etiqueta): ?>
    <a href="?estado=<?= e($clave) ?>&amp;q=<?= e(urlencode($q)) ?>" class="<?= $estado === $clave ? 'activa' : '' ?>"<?= $estado === $clave ? ' aria-current="page"' : '' ?>><?= e($etiqueta) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($staff): ?>
  <form class="filtros" method="get">
    <input type="hidden" name="estado" value="<?= e($estado) ?>">
    <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar por usuario, cédula o código de equipo" aria-label="Buscar multas">
    <button class="btn btn-outline-primary">Buscar</button>
    <?php if ($q !== ''): ?><a class="btn btn-link" href="?estado=<?= e($estado) ?>">Quitar búsqueda</a><?php endif; ?>
  </form>
<?php endif; ?>

<?php if ($totalPendiente > 0): ?>
  <p class="mb-3">Total pendiente en esta lista: <strong><?= e(colones($totalPendiente)) ?></strong></p>
<?php endif; ?>

<?php if (!$multas): ?>
  <p class="vacio">No hay multas en esta lista.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table tabla align-middle">
      <thead>
        <tr>
          <?php if ($staff): ?><th>Usuario</th><?php endif; ?>
          <th>Equipo</th><th>Días de retraso</th><th>Monto</th><th>Generada</th><th>Pagada</th><th>Estado</th>
          <?php if ($staff): ?><th class="text-end">Acción</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($multas as $m): ?>
        <tr>
          <?php if ($staff): ?>
            <td><?= e($m['usuario']) ?><br><small class="text-muted"><?= e($m['cedula']) ?></small></td>
          <?php endif; ?>
          <td><span class="codigo"><?= e($m['equipo_codigo']) ?></span><br><?= e($m['equipo_nombre']) ?></td>
          <td><?= (int) $m['dias_retraso'] ?></td>
          <td><?= e(colones($m['monto'])) ?></td>
          <td><?= e(fecha($m['fecha_generada'], false)) ?></td>
          <td><?= e(fecha($m['fecha_pago'], false)) ?></td>
          <td><?= estado_badge($m['estado']) ?></td>
          <?php if ($staff): ?>
            <td class="text-end">
              <?php if ($m['estado'] === 'pendiente'): ?>
                <form method="post" action="<?= e(url('modules/multas/pagar.php')) ?>"
                      data-confirm="¿Marcar como pagada la multa de <?= e($m['usuario']) ?> (<?= e(colones($m['monto'])) ?>)?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $m['id_multa'] ?>">
                  <button class="btn btn-sm btn-primary">Marcar como pagada</button>
                </form>
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
