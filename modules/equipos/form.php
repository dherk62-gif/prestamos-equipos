<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

$pdo = db();
$id  = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$eq  = ['codigo' => '', 'nombre' => '', 'marca' => '', 'modelo' => '', 'descripcion' => '', 'id_categoria' => '', 'estado' => 'disponible'];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE id_equipo = :id");
    $stmt->execute([':id' => $id]);
    $eq = $stmt->fetch();
    if (!$eq) {
        flash('danger', 'El equipo que intenta editar no existe.');
        redirect('modules/equipos/index.php');
    }
}

// Un equipo prestado solo cambia de estado al registrar su devolución.
$bloqueado  = $id && $eq['estado'] === 'prestado';
$categorias = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre")->fetchAll();
$estadosEditables = ['disponible' => 'Disponible', 'mantenimiento' => 'En mantenimiento', 'baja' => 'De baja'];
$errores = [];

if (es_post()) {
    csrf_check();
    foreach (['codigo', 'nombre', 'marca', 'modelo', 'descripcion'] as $campo) {
        $eq[$campo] = post($campo);
    }
    $eq['id_categoria'] = (int) post('id_categoria');
    if (!$bloqueado) {
        $eq['estado'] = post('estado');
    }

    if ($eq['codigo'] === '')  { $errores[] = 'El código es obligatorio.'; }
    if ($eq['nombre'] === '')  { $errores[] = 'El nombre es obligatorio.'; }
    if (mb_strlen($eq['codigo']) > 30)       { $errores[] = 'El código no puede superar 30 caracteres.'; }
    if (mb_strlen($eq['nombre']) > 80)       { $errores[] = 'El nombre no puede superar 80 caracteres.'; }
    if (mb_strlen($eq['marca']) > 50 || mb_strlen($eq['modelo']) > 50) { $errores[] = 'Marca y modelo no pueden superar 50 caracteres.'; }
    if (mb_strlen($eq['descripcion']) > 255) { $errores[] = 'La descripción no puede superar 255 caracteres.'; }
    if (!$bloqueado && !isset($estadosEditables[$eq['estado']])) { $errores[] = 'Seleccione un estado válido.'; }

    $existeCat = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE id_categoria = :id");
    $existeCat->execute([':id' => $eq['id_categoria']]);
    if (!$existeCat->fetchColumn()) {
        $errores[] = 'Seleccione una categoría.';
    }

    if (!$errores) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM equipos WHERE codigo = :c AND id_equipo <> :id");
        $dup->execute([':c' => $eq['codigo'], ':id' => $id]);
        if ($dup->fetchColumn() > 0) {
            $errores[] = 'Ya existe un equipo con ese código.';
        }
    }

    if (!$errores) {
        $datos = [
            ':codigo'      => $eq['codigo'],
            ':nombre'      => $eq['nombre'],
            ':marca'       => $eq['marca'] ?: null,
            ':modelo'      => $eq['modelo'] ?: null,
            ':descripcion' => $eq['descripcion'] ?: null,
            ':categoria'   => $eq['id_categoria'],
            ':estado'      => $eq['estado'],
        ];
        if ($id) {
            $datos[':id'] = $id;
            $pdo->prepare(
                "UPDATE equipos SET codigo = :codigo, nombre = :nombre, marca = :marca, modelo = :modelo,
                        descripcion = :descripcion, id_categoria = :categoria, estado = :estado
                 WHERE id_equipo = :id"
            )->execute($datos);
        } else {
            $pdo->prepare(
                "INSERT INTO equipos (codigo, nombre, marca, modelo, descripcion, id_categoria, estado)
                 VALUES (:codigo, :nombre, :marca, :modelo, :descripcion, :categoria, :estado)"
            )->execute($datos);
        }
        flash('success', $id ? 'Equipo actualizado.' : 'Equipo registrado.');
        redirect('modules/equipos/index.php');
    }
}

$titulo = $id ? 'Editar equipo' : 'Registrar equipo';
$activo = 'equipos';
require __DIR__ . '/../../includes/header.php';
page_head($titulo);
render_errores($errores);
?>
<?php if (!$categorias): ?>
  <div class="alert alert-warning">Antes de registrar un equipo necesita al menos una categoría.
    <a href="<?= e(url('modules/categorias/form.php')) ?>">Registrar categoría</a></div>
<?php else: ?>
<form method="post" class="panel form-estrecho">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label" for="codigo">Código</label>
      <input class="form-control" id="codigo" name="codigo" maxlength="30" required value="<?= e($eq['codigo']) ?>" placeholder="LAP-004">
    </div>
    <div class="col-md-8">
      <label class="form-label" for="nombre">Nombre</label>
      <input class="form-control" id="nombre" name="nombre" maxlength="80" required value="<?= e($eq['nombre']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="marca">Marca</label>
      <input class="form-control" id="marca" name="marca" maxlength="50" value="<?= e($eq['marca']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="modelo">Modelo</label>
      <input class="form-control" id="modelo" name="modelo" maxlength="50" value="<?= e($eq['modelo']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="id_categoria">Categoría</label>
      <select class="form-select" id="id_categoria" name="id_categoria" required>
        <option value="">Seleccione una categoría</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int) $c['id_categoria'] ?>"<?= selected($eq['id_categoria'], $c['id_categoria']) ?>><?= e($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="estado">Estado</label>
      <?php if ($bloqueado): ?>
        <div class="form-control-plaintext"><?= estado_badge('prestado') ?> <small class="text-muted">cambia al registrar la devolución</small></div>
      <?php else: ?>
        <select class="form-select" id="estado" name="estado">
          <?php foreach ($estadosEditables as $clave => $etiqueta): ?>
            <option value="<?= e($clave) ?>"<?= selected($eq['estado'], $clave) ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>
    <div class="col-12">
      <label class="form-label" for="descripcion">Descripción (opcional)</label>
      <input class="form-control" id="descripcion" name="descripcion" maxlength="255" value="<?= e($eq['descripcion']) ?>">
    </div>
  </div>
  <div class="mt-4">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-link" href="index.php">Cancelar</a>
  </div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
