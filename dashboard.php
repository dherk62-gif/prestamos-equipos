<?php
require __DIR__ . '/includes/init.php';
require_login();

$pdo   = db();
$u     = usuario_actual();
$staff = es_staff();

// Valores iniciales: más abajo se llenan según el rol (personal o usuario).
$porEstado     = [];
$vencidos      = 0;
$multas        = ['n' => 0, 'total' => 0];
$listaVencidos = [];
$listaEnCurso  = [];
$misPrestamos  = [];
$misMultas     = ['n' => 0, 'total' => 0];
$disponibles   = 0;

if ($staff) {
    $porEstado = $pdo->query("SELECT estado, COUNT(*) FROM equipos GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
    $vencidos  = (int) $pdo->query("SELECT COUNT(*) FROM v_prestamos_detalle WHERE estado = 'vencido'")->fetchColumn();
    $multas    = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(monto), 0) AS total FROM multas WHERE estado = 'pendiente'")->fetch();
    // Todos los préstamos abiertos, sin límite: primero los vencidos y luego los que van en plazo.
    $listaVencidos = $pdo->query("SELECT * FROM v_prestamos_detalle WHERE estado = 'vencido' ORDER BY fecha_limite")->fetchAll();
    $listaEnCurso  = $pdo->query("SELECT * FROM v_prestamos_detalle WHERE estado = 'activo' ORDER BY fecha_limite")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM v_prestamos_detalle WHERE id_usuario = :id AND estado <> 'devuelto' ORDER BY fecha_limite");
    $stmt->execute([':id' => $u['id']]);
    $misPrestamos = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(monto), 0) AS total FROM multas WHERE id_usuario = :id AND estado = 'pendiente'");
    $stmt->execute([':id' => $u['id']]);
    $misMultas = $stmt->fetch();

    $disponibles = (int) $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'disponible'")->fetchColumn();
}

$titulo = 'Panel';
$activo = 'dashboard';
require __DIR__ . '/includes/header.php';

if ($staff):
    $segmentos = ['disponible' => 'Disponibles', 'prestado' => 'Prestados', 'mantenimiento' => 'En mantenimiento', 'baja' => 'De baja'];
    page_head('Panel', '<a class="btn btn-primary" href="' . e(url('modules/prestamos/nuevo.php')) . '">Nuevo préstamo</a>');
?>
  <div class="resumen">
    <div class="dato"><strong><?= (int) ($porEstado['disponible'] ?? 0) ?></strong><span>Equipos disponibles</span></div>
    <div class="dato"><strong><?= (int) ($porEstado['prestado'] ?? 0) ?></strong><span>Equipos prestados</span></div>
    <div class="dato<?= $vencidos > 0 ? ' alerta' : '' ?>"><strong><?= $vencidos ?></strong><span>Préstamos vencidos</span></div>
    <div class="dato<?= (int) $multas['n'] > 0 ? ' alerta' : '' ?>"><strong><?= e(colones($multas['total'])) ?></strong><span>En <?= (int) $multas['n'] ?> multa(s) pendiente(s)</span></div>
  </div>

  <section class="inventario" aria-labelledby="t-inventario">
    <h2 id="t-inventario">Inventario por estado</h2>
    <div class="barra-estado" role="img" aria-label="Distribución de los equipos por estado">
      <?php foreach ($segmentos as $clave => $etiqueta): $n = (int) ($porEstado[$clave] ?? 0); ?>
        <?php if ($n > 0): ?><span class="seg-<?= e($clave) ?>" style="flex: <?= $n ?> 1 0" title="<?= e($etiqueta) ?>: <?= $n ?>"></span><?php endif; ?>
      <?php endforeach; ?>
    </div>
    <ul class="leyenda">
      <?php foreach ($segmentos as $clave => $etiqueta): ?>
        <li><span class="punto seg-<?= e($clave) ?>"></span><?= e($etiqueta) ?>: <strong><?= (int) ($porEstado[$clave] ?? 0) ?></strong></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <h2 class="seccion-titulo">Préstamos vencidos (<?= count($listaVencidos) ?>)</h2>
  <p class="text-muted">El más atrasado aparece primero.</p>
  <?php if ($listaVencidos): ?>
    <?php render_tabla_prestamos($listaVencidos, true); ?>
  <?php else: ?>
    <p class="vacio">No hay préstamos vencidos. Todo está al día.</p>
  <?php endif; ?>

  <h2 class="seccion-titulo mt-4">Préstamos en curso (<?= count($listaEnCurso) ?>)</h2>
  <p class="text-muted">Dentro del plazo; los que vencen primero van arriba.</p>
  <?php if ($listaEnCurso): ?>
    <?php render_tabla_prestamos($listaEnCurso, true); ?>
  <?php else: ?>
    <p class="vacio">No hay préstamos en curso en este momento.</p>
  <?php endif; ?>

<?php else:
    page_head('Hola, ' . $u['nombre']);
?>
  <div class="resumen">
    <div class="dato"><strong><?= count($misPrestamos) ?></strong><span>Préstamos en curso</span></div>
    <div class="dato<?= (int) $misMultas['n'] > 0 ? ' alerta' : '' ?>"><strong><?= e(colones($misMultas['total'])) ?></strong><span>En multas pendientes</span></div>
    <div class="dato"><strong><?= $disponibles ?></strong><span>Equipos disponibles ahora</span></div>
  </div>

  <?php if ((int) $misMultas['n'] > 0): ?>
    <div class="alert alert-warning">Tiene multas pendientes. Mientras no las pague no podrá solicitar nuevos préstamos.</div>
  <?php endif; ?>

  <h2 class="seccion-titulo">Mis préstamos en curso</h2>
  <?php render_tabla_prestamos($misPrestamos, false, false); ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>