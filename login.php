<?php
require __DIR__ . '/includes/init.php';

if (usuario_actual()) {
    redirect('dashboard.php');
}

$error  = '';
$correo = '';

if (es_post()) {
    csrf_check();
    $correo = post('correo');
    $clave  = $_POST['clave'] ?? '';

    $stmt = db()->prepare(
        "SELECT u.id_usuario, u.nombre, u.apellidos, u.password_hash, u.estado, r.nombre AS rol
         FROM usuarios u
         JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.correo = :correo
         LIMIT 1"
    );
    $stmt->execute([':correo' => $correo]);
    $u = $stmt->fetch();

    if ($u && $u['estado'] === 'activo' && password_verify($clave, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'     => (int) $u['id_usuario'],
            'nombre' => $u['nombre'] . ' ' . $u['apellidos'],
            'rol'    => $u['rol'],
        ];
        redirect('dashboard.php');
    }

    $error = 'El correo o la contraseña no coinciden, o el usuario está inactivo.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión · <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="login">
  <section class="login-info">
    <h1>Sepa qué equipo está disponible, quién lo tiene y cuándo vuelve.</h1>
    <p>Registre préstamos y devoluciones, controle los atrasos y consulte el historial de cada equipo desde un solo lugar.</p>
  </section>
  <section class="login-form">
    <form method="post" novalidate>
      <h2 class="h4 mb-3">Iniciar sesión</h2>
      <?php flash_render(); ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="correo">Correo</label>
        <input class="form-control" type="email" id="correo" name="correo" value="<?= e($correo) ?>" required autofocus autocomplete="username">
      </div>
      <div class="mb-3">
        <label class="form-label" for="clave">Contraseña</label>
        <input class="form-control" type="password" id="clave" name="clave" required autocomplete="current-password">
      </div>
      <button class="btn btn-primary w-100" type="submit">Entrar</button>
    </form>
  </section>
</div>
</body>
</html>
