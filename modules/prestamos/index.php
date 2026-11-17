<?php
require __DIR__ . '/../../includes/init.php';
require_login();

$pdo   = db();
$staff = es_staff();
$yo    = usuario_actual()['id'];

$q      = trim($_GET['q'] ?? '');
$estado = $_GET['estado'] ?? 'todos';
$pestanas = ['todos' => 'Todos', 'activo' => 'En curso', 'vencido' => 'Vencidos', 'devuelto' => 'Devueltos'];
if (!isset($pestanas[$estado])) {
    $estado = 'todos';
}

$where  = [];
$params = [];
if (!$staff) {                       // Un usuario común solo ve sus propios préstamos
    $where[]       = "id_usuario = :yo";
    $params[':yo'] = $yo;
}
if ($estado !== 'todos') {
    $where[]          = "estado = :estado";
    $params[':estado'] = $estado;
}
if ($q !== '') {
    $where[] = "(usuario LIKE :q1 OR cedula LIKE :q2 OR equipo_nombre LIKE :q3 OR equipo_codigo LIKE :q4)";
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = "%$q%";
}

$stmt = $pdo->prepare(
    "SELECT * FROM v_prestamos_detalle"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . " ORDER BY fecha_salida DESC"
);
$stmt->execute($params);
$prestamos = $stmt->fetchAll();

$titulo = $staff ? 'Préstamos' : 'Mis préstamos';
$activo = 'prestamos';
require __DIR__ . '/../../includes/header.php';
page_head($titulo, $staff ? '<a class="btn btn-primary" href="nuevo.php">Nuevo préstamo</a>' : '');
?>
<nav class="pestanas" aria-label="Filtrar por estado">
  <?php foreach ($pestanas as $clave => $etiqueta): ?>
    <a href="?estado=<?= e($clave) ?>&amp;q=<?= e(urlencode($q)) ?>" class="<?= $estado === $clave ? 'activa' : '' ?>"<?= $estado === $clave ? ' aria-current="page"' : '' ?>><?= e($etiqueta) ?></a>
  <?php endforeach; ?>
</nav>

<form class="filtros" method="get">
  <input type="hidden" name="estado" value="<?= e($estado) ?>">
  <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar por <?= $staff ? 'usuario, cédula, ' : '' ?>equipo o código" aria-label="Buscar préstamos">
  <button class="btn btn-outline-primary">Buscar</button>
  <?php if ($q !== ''): ?><a class="btn btn-link" href="?estado=<?= e($estado) ?>">Quitar búsqueda</a><?php endif; ?>
</form>

<?php render_tabla_prestamos($prestamos, $staff, $staff); ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
