<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

$pdo = db();
$reportes = ['vencidos' => 'Préstamos vencidos', 'mas-prestados' => 'Equipos más prestados', 'por-usuario' => 'Historial por usuario'];
$r = $_GET['r'] ?? 'vencidos';
if (!isset($reportes[$r])) {
    $r = 'vencidos';
}

if ($r === 'vencidos') {
    $vencidos = $pdo->query("SELECT * FROM v_prestamos_vencidos ORDER BY fecha_limite")->fetchAll();
} elseif ($r === 'mas-prestados') {
    $ranking = $pdo->query("SELECT * FROM v_equipos_mas_prestados ORDER BY total_prestamos DESC, nombre LIMIT 10")->fetchAll();
    $maximo  = $ranking ? max(1, (int) $ranking[0]['total_prestamos']) : 1;
} else {
    $idUsuario = (int) ($_GET['usuario'] ?? 0);
    $usuarios  = $pdo->query("SELECT id_usuario, nombre, apellidos, cedula FROM usuarios ORDER BY nombre, apellidos")->fetchAll();
    $historial = [];
    $multasUsuario = ['n' => 0, 'total' => 0];
    if ($idUsuario) {
        $stmt = $pdo->prepare("SELECT * FROM v_prestamos_detalle WHERE id_usuario = :id ORDER BY fecha_salida DESC");
        $stmt->execute([':id' => $idUsuario]);
        $historial = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(monto), 0) AS total FROM multas WHERE id_usuario = :id AND estado = 'pendiente'");
        $stmt->execute([':id' => $idUsuario]);
        $multasUsuario = $stmt->fetch();
    }
}

$titulo = 'Reportes';
$activo = 'reportes';
require __DIR__ . '/../../includes/header.php';
page_head('Reportes', '<button class="btn btn-outline-primary" type="button" onclick="window.print()">Imprimir</button>');
?>
<nav class="pestanas" aria-label="Tipo de reporte">
  <?php foreach ($reportes as $clave => $etiqueta): ?>
    <a href="?r=<?= e($clave) ?>" class="<?= $r === $clave ? 'activa' : '' ?>"<?= $r === $clave ? ' aria-current="page"' : '' ?>><?= e($etiqueta) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($r === 'vencidos'): ?>

  <p class="text-muted">Equipos que ya debieron devolverse. La multa estimada se calcula con <?= e(colones(MULTA_POR_DIA)) ?> por día y se cobra al registrar la devolución.</p>
  <?php if (!$vencidos): ?>
    <p class="vacio">No hay préstamos vencidos. Todo está al día.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table tabla align-middle">
        <thead><tr><th>Equipo</th><th>Usuario</th><th>Contacto</th><th>Fecha límite</th><th>Retraso</th><th>Multa estimada</th></tr></thead>
        <tbody>
        <?php foreach ($vencidos as $f): ?>
          <tr>
            <td><span class="codigo"><?= e($f['equipo_codigo']) ?></span><br><?= e($f['equipo_nombre']) ?></td>
            <td><?= e($f['usuario']) ?><br><small class="text-muted"><?= e($f['cedula']) ?></small></td>
            <td><?= e($f['correo']) ?><br><small class="text-muted"><?= e($f['telefono'] ?: 'Sin teléfono') ?></small></td>
            <td><?= e(fecha($f['fecha_limite'])) ?></td>
            <td><?= (int) $f['dias_retraso'] ?> día(s)</td>
            <td><?= e(colones($f['dias_retraso'] * MULTA_POR_DIA)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php elseif ($r === 'mas-prestados'): ?>

  <p class="text-muted">Los 10 equipos con más préstamos en todo el historial.</p>
  <?php if (!$ranking): ?>
    <p class="vacio">Todavía no hay equipos registrados.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table tabla align-middle">
        <thead><tr><th>Equipo</th><th>Categoría</th><th>Préstamos</th><th>Último préstamo</th></tr></thead>
        <tbody>
        <?php foreach ($ranking as $f): ?>
          <tr>
            <td><span class="codigo"><?= e($f['codigo']) ?></span><br><?= e($f['nombre']) ?></td>
            <td><?= e($f['categoria']) ?></td>
            <td style="min-width: 12rem">
              <div class="d-flex align-items-center gap-2">
                <div class="barra flex-grow-1" role="img" aria-label="<?= (int) $f['total_prestamos'] ?> préstamos"><span style="width: <?= round(((int) $f['total_prestamos'] / $maximo) * 100) ?>%"></span></div>
                <strong><?= (int) $f['total_prestamos'] ?></strong>
              </div>
            </td>
            <td><?= e(fecha($f['ultimo_prestamo'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php else: ?>

  <form class="filtros" method="get">
    <input type="hidden" name="r" value="por-usuario">
    <select name="usuario" class="form-select" style="max-width: 24rem" aria-label="Seleccione un usuario" required>
      <option value="">Seleccione un usuario</option>
      <?php foreach ($usuarios as $f): ?>
        <option value="<?= (int) $f['id_usuario'] ?>"<?= selected($idUsuario, $f['id_usuario']) ?>><?= e($f['nombre'] . ' ' . $f['apellidos'] . ' · ' . $f['cedula']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-primary">Ver historial</button>
  </form>

  <?php if ($idUsuario): ?>
    <?php if ((int) $multasUsuario['n'] > 0): ?>
      <div class="alert alert-warning">Este usuario tiene <?= (int) $multasUsuario['n'] ?> multa(s) pendiente(s) por <?= e(colones($multasUsuario['total'])) ?>.</div>
    <?php endif; ?>
    <?php render_tabla_prestamos($historial, false, false); ?>
  <?php else: ?>
    <p class="vacio">Elija un usuario para ver todos sus préstamos.</p>
  <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
