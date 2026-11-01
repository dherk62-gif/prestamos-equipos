<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

$pdo   = db();
$admin = tiene_rol('administrador');
$yo    = usuario_actual()['id'];
$id    = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$us = ['nombre' => '', 'apellidos' => '', 'cedula' => '', 'correo' => '', 'telefono' => '', 'id_rol' => '', 'estado' => 'activo'];

// El administrador asigna cualquier rol; el encargado solo registra usuarios comunes.
$roles = $pdo->query("SELECT id_rol, nombre FROM roles ORDER BY id_rol")->fetchAll();
if (!$admin) {
    $roles = array_values(array_filter($roles, fn($r) => $r['nombre'] === 'usuario'));
}
$idsPermitidos = array_map('intval', array_column($roles, 'id_rol'));

// Rol sugerido al registrar: "usuario" (nadie debería quedar como administrador por descuido).
$rolPorDefecto = $idsPermitidos[0];
foreach ($roles as $r) {
    if ($r['nombre'] === 'usuario') {
        $rolPorDefecto = (int) $r['id_rol'];
    }
}

if ($id) {
    $stmt = $pdo->prepare(
        "SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = :id"
    );
    $stmt->execute([':id' => $id]);
    $us = $stmt->fetch();
    if (!$us) {
        flash('danger', 'El usuario que intenta editar no existe.');
        redirect('modules/usuarios/index.php');
    }
    if (!$admin && $us['rol'] !== 'usuario') {
        flash('danger', 'Solo un administrador puede editar a este usuario.');
        redirect('modules/usuarios/index.php');
    }
}

$esMismo = $id === $yo;
$errores = [];

if (es_post()) {
    csrf_check();
    $rolActual = $us['id_rol'];

    foreach (['nombre', 'apellidos', 'cedula', 'correo', 'telefono'] as $campo) {
        $us[$campo] = post($campo);
    }
    $us['id_rol'] = (int) post('id_rol');
    $us['estado'] = post('estado');
    $clave        = $_POST['clave'] ?? '';

    // Nadie puede quitarse su propio acceso de administración.
    if ($esMismo) {
        $us['id_rol'] = (int) $rolActual;
        $us['estado'] = 'activo';
    }

    if ($us['nombre'] === '' || $us['apellidos'] === '') { $errores[] = 'El nombre y los apellidos son obligatorios.'; }
    if ($us['cedula'] === '')  { $errores[] = 'La cédula es obligatoria.'; }
    if (!filter_var($us['correo'], FILTER_VALIDATE_EMAIL)) { $errores[] = 'Escriba un correo válido.'; }
    if (mb_strlen($us['nombre']) > 60 || mb_strlen($us['apellidos']) > 80) { $errores[] = 'El nombre o los apellidos son demasiado largos.'; }
    if (mb_strlen($us['cedula']) > 20 || mb_strlen($us['telefono']) > 20)   { $errores[] = 'La cédula y el teléfono admiten hasta 20 caracteres.'; }
    if (!in_array($us['estado'], ['activo', 'inactivo'], true)) { $errores[] = 'Seleccione un estado válido.'; }
    if (!$esMismo && !in_array($us['id_rol'], $idsPermitidos, true)) { $errores[] = 'Seleccione un rol válido.'; }

    if (!$id && strlen($clave) < 8) {
        $errores[] = 'La contraseña es obligatoria y debe tener al menos 8 caracteres.';
    } elseif ($id && $clave !== '' && strlen($clave) < 8) {
        $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    }

    if (!$errores) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE (cedula = :ced OR correo = :cor) AND id_usuario <> :id");
        $dup->execute([':ced' => $us['cedula'], ':cor' => $us['correo'], ':id' => $id]);
        if ($dup->fetchColumn() > 0) {
            $errores[] = 'Ya existe un usuario con esa cédula o ese correo.';
        }
    }

    if (!$errores) {
        $datos = [
            ':nombre'    => $us['nombre'],
            ':apellidos' => $us['apellidos'],
            ':cedula'    => $us['cedula'],
            ':correo'    => $us['correo'],
            ':telefono'  => $us['telefono'] ?: null,
            ':rol'       => $us['id_rol'],
            ':estado'    => $us['estado'],
        ];
        if ($id) {
            $sql = "UPDATE usuarios SET nombre = :nombre, apellidos = :apellidos, cedula = :cedula, correo = :correo,
                           telefono = :telefono, id_rol = :rol, estado = :estado";
            if ($clave !== '') {
                $sql .= ", password_hash = :hash";
                $datos[':hash'] = password_hash($clave, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id_usuario = :id";
            $datos[':id'] = $id;
            $pdo->prepare($sql)->execute($datos);
        } else {
            $datos[':hash'] = password_hash($clave, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO usuarios (nombre, apellidos, cedula, correo, telefono, id_rol, estado, password_hash)
                 VALUES (:nombre, :apellidos, :cedula, :correo, :telefono, :rol, :estado, :hash)"
            )->execute($datos);
        }
        flash('success', $id ? 'Usuario actualizado.' : 'Usuario registrado.');
        redirect('modules/usuarios/index.php');
    }
}

$titulo = $id ? 'Editar usuario' : 'Registrar usuario';
$activo = 'usuarios';
require __DIR__ . '/../../includes/header.php';
page_head($titulo);
render_errores($errores);
?>
<form method="post" class="panel form-estrecho" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label" for="nombre">Nombre</label>
      <input class="form-control" id="nombre" name="nombre" maxlength="60" required value="<?= e($us['nombre']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="apellidos">Apellidos</label>
      <input class="form-control" id="apellidos" name="apellidos" maxlength="80" required value="<?= e($us['apellidos']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="cedula">Cédula</label>
      <input class="form-control" id="cedula" name="cedula" maxlength="20" required value="<?= e($us['cedula']) ?>" placeholder="1-1234-5678">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="telefono">Teléfono (opcional)</label>
      <input class="form-control" id="telefono" name="telefono" maxlength="20" value="<?= e($us['telefono']) ?>">
    </div>
    <div class="col-12">
      <label class="form-label" for="correo">Correo (es el usuario para iniciar sesión)</label>
      <input class="form-control" type="email" id="correo" name="correo" maxlength="100" required value="<?= e($us['correo']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="id_rol">Rol</label>
      <?php if ($esMismo): ?>
        <div class="form-control-plaintext">Administrador <small class="text-muted">(no puede cambiar su propio rol)</small></div>
      <?php else: ?>
        <select class="form-select" id="id_rol" name="id_rol" required>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id_rol'] ?>"<?= selected($us['id_rol'] !== '' ? $us['id_rol'] : $rolPorDefecto, $r['id_rol']) ?>><?= e(ucfirst($r['nombre'])) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="estado">Estado</label>
      <?php if ($esMismo): ?>
        <div class="form-control-plaintext"><?= estado_badge('activo') ?></div>
      <?php else: ?>
        <select class="form-select" id="estado" name="estado">
          <option value="activo"<?= selected($us['estado'], 'activo') ?>>Activo</option>
          <option value="inactivo"<?= selected($us['estado'], 'inactivo') ?>>Inactivo</option>
        </select>
      <?php endif; ?>
    </div>
    <div class="col-12">
      <label class="form-label" for="clave"><?= $id ? 'Nueva contraseña (déjela vacía para no cambiarla)' : 'Contraseña (mínimo 8 caracteres)' ?></label>
      <input class="form-control" type="password" id="clave" name="clave" minlength="8" <?= $id ? '' : 'required' ?> autocomplete="new-password">
    </div>
  </div>
  <div class="mt-4">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-link" href="index.php">Cancelar</a>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
