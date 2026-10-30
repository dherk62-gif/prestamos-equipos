<?php
// Cabecera común con menú lateral. Cada página define antes $titulo y $activo.
// Todo corre dentro de una función anónima para que las variables del menú
// ($roles, $staff, $u...) no pisen las variables de la página que la incluye.
(function (string $titulo, string $activo): void {
$u = usuario_actual();
$todos = ['administrador', 'encargado', 'usuario'];
$staff = ['administrador', 'encargado'];

$menu = [
    // Orden de uso: primero lo que se presta, luego a quién, después la operación y la consulta.
    ['dashboard',  'Panel',                                  'dashboard.php',                $todos],
    ['categorias', 'Categorías',                             'modules/categorias/index.php', $staff],
    ['equipos',    'Equipos',                                'modules/equipos/index.php',    $todos],
    ['usuarios',   'Usuarios',                               'modules/usuarios/index.php',   $staff],
    ['prestamos',  es_staff() ? 'Préstamos' : 'Mis préstamos', 'modules/prestamos/index.php',  $todos],
    ['multas',     es_staff() ? 'Multas' : 'Mis multas',     'modules/multas/index.php',     $todos],
    ['reportes',   'Reportes',                               'modules/reportes/index.php',   $staff],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="side-top">
      <a class="brand" href="<?= e(url('dashboard.php')) ?>"><?= e(APP_NAME) ?></a>
      <button class="side-toggle d-lg-none" type="button" data-bs-toggle="collapse"
              data-bs-target="#menu" aria-controls="menu" aria-label="Abrir el menú">Menú</button>
    </div>
    <div id="menu" class="collapse d-lg-flex side-body">
      <nav aria-label="Secciones">
        <?php foreach ($menu as [$clave, $texto, $ruta, $roles]): ?>
          <?php if (in_array($u['rol'], $roles, true)): ?>
            <a class="nav-item-app<?= $activo === $clave ? ' activo' : '' ?>"
               href="<?= e(url($ruta)) ?>"<?= $activo === $clave ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <div class="side-user">
        <strong><?= e($u['nombre']) ?></strong>
        <span><?= e(ucfirst($u['rol'])) ?></span>
        <a href="<?= e(url('logout.php')) ?>">Cerrar sesión</a>
      </div>
    </div>
  </aside>
  <main class="main">
    <?php flash_render(); ?>
<?php })($titulo ?? APP_NAME, $activo ?? ''); ?>