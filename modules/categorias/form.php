<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

$pdo = db();
$id  = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$cat = ['nombre' => '', 'descripcion' => ''];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id_categoria = :id");
    $stmt->execute([':id' => $id]);
    $cat = $stmt->fetch();
    if (!$cat) {
        flash('danger', 'La categoría que intenta editar no existe.');
        redirect('modules/categorias/index.php');
    }
}

$errores = [];

if (es_post()) {
    csrf_check();
    $cat['nombre']      = post('nombre');
    $cat['descripcion'] = post('descripcion');

    if ($cat['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($cat['nombre']) > 60) {
        $errores[] = 'El nombre no puede superar 60 caracteres.';
    }
    if (mb_strlen($cat['descripcion']) > 200) {
        $errores[] = 'La descripción no puede superar 200 caracteres.';
    }

    if (!$errores) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE nombre = :n AND id_categoria <> :id");
        $dup->execute([':n' => $cat['nombre'], ':id' => $id]);
        if ($dup->fetchColumn() > 0) {
            $errores[] = 'Ya existe una categoría con ese nombre.';
        }
    }

    if (!$errores) {
        $datos = [':nombre' => $cat['nombre'], ':descripcion' => $cat['descripcion'] ?: null];
        if ($id) {
            $datos[':id'] = $id;
            $pdo->prepare("UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id_categoria = :id")->execute($datos);
        } else {
            $pdo->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)")->execute($datos);
        }
        flash('success', $id ? 'Categoría actualizada.' : 'Categoría registrada.');
        redirect('modules/categorias/index.php');
    }
}

$titulo = $id ? 'Editar categoría' : 'Registrar categoría';
$activo = 'categorias';
require __DIR__ . '/../../includes/header.php';
page_head($titulo);
render_errores($errores);
?>
<form method="post" class="panel form-estrecho">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="mb-3">
    <label class="form-label" for="nombre">Nombre</label>
    <input class="form-control" id="nombre" name="nombre" maxlength="60" required value="<?= e($cat['nombre']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label" for="descripcion">Descripción (opcional)</label>
    <input class="form-control" id="descripcion" name="descripcion" maxlength="200" value="<?= e($cat['descripcion']) ?>">
  </div>
  <button class="btn btn-primary" type="submit">Guardar</button>
  <a class="btn btn-link" href="index.php">Cancelar</a>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
