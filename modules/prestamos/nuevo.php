<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

$pdo = db();
$yo  = usuario_actual()['id'];

$form = [
    'id_usuario'    => 0,
    'id_equipo'     => (int) ($_GET['equipo'] ?? 0),
    'fecha_limite'  => date('Y-m-d\TH:i', strtotime('+' . DIAS_PRESTAMO_DEFECTO . ' days')),
    'observaciones' => '',
];
$errores = [];

if (es_post()) {
    csrf_check();
    $form['id_usuario']    = (int) post('id_usuario');
    $form['id_equipo']     = (int) post('id_equipo');
    $form['fecha_limite']  = post('fecha_limite');
    $form['observaciones'] = post('observaciones');

    $limite = DateTime::createFromFormat('Y-m-d\TH:i', $form['fecha_limite']);
    $ahora  = new DateTime();

    if (!$form['id_usuario']) { $errores[] = 'Seleccione el usuario que recibe el equipo.'; }
    if (!$form['id_equipo'])  { $errores[] = 'Seleccione el equipo que se presta.'; }
    if (!$limite || $limite <= $ahora) { $errores[] = 'La fecha límite debe ser posterior a este momento.'; }
    if (mb_strlen($form['observaciones']) > 255) { $errores[] = 'Las observaciones no pueden superar 255 caracteres.'; }

    // Regla de negocio: un usuario con multas pendientes no puede solicitar préstamos.
    if (!$errores) {
        $stmt = $pdo->prepare(
            "SELECT u.estado,
                    (SELECT COUNT(*) FROM multas m WHERE m.id_usuario = u.id_usuario AND m.estado = 'pendiente') AS multas
             FROM usuarios u WHERE u.id_usuario = :id"
        );
        $stmt->execute([':id' => $form['id_usuario']]);
        $usr = $stmt->fetch();

        if (!$usr) {
            $errores[] = 'El usuario seleccionado no existe.';
        } elseif ($usr['estado'] !== 'activo') {
            $errores[] = 'El usuario está inactivo y no puede recibir préstamos.';
        } elseif ((int) $usr['multas'] > 0) {
            $errores[] = 'El usuario tiene multas pendientes. Debe pagarlas antes de pedir un nuevo préstamo.';
        }
    }

    if (!$errores) {
        try {
            $pdo->beginTransaction();

            // Regla de negocio: un equipo prestado no se presta de nuevo hasta que sea devuelto.
            // FOR UPDATE bloquea la fila para que dos encargados no presten el mismo equipo a la vez.
            $stmt = $pdo->prepare("SELECT estado FROM equipos WHERE id_equipo = :id FOR UPDATE");
            $stmt->execute([':id' => $form['id_equipo']]);
            $estadoEquipo = $stmt->fetchColumn();

            if ($estadoEquipo === false) {
                throw new RuntimeException('El equipo seleccionado no existe.');
            }
            if ($estadoEquipo !== 'disponible') {
                throw new RuntimeException('Ese equipo no está disponible: ya está prestado o fuera de servicio.');
            }

            $pdo->prepare(
                "INSERT INTO prestamos (id_usuario, id_equipo, id_encargado, fecha_salida, fecha_limite, observaciones)
                 VALUES (:usuario, :equipo, :encargado, :salida, :limite, :obs)"
            )->execute([
                ':usuario'   => $form['id_usuario'],
                ':equipo'    => $form['id_equipo'],
                ':encargado' => $yo,
                ':salida'    => $ahora->format('Y-m-d H:i:s'),
                ':limite'    => $limite->format('Y-m-d H:i:s'),
                ':obs'       => $form['observaciones'] ?: null,
            ]);

            // Al prestar, el equipo pasa a "prestado".
            $pdo->prepare("UPDATE equipos SET estado = 'prestado' WHERE id_equipo = :id")
                ->execute([':id' => $form['id_equipo']]);

            $pdo->commit();
            flash('success', 'Préstamo registrado. El equipo quedó como prestado.');
            redirect('modules/prestamos/index.php');
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errores[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }
}

$usuarios = $pdo->query(
    "SELECT u.id_usuario, u.nombre, u.apellidos, u.cedula,
            (SELECT COUNT(*) FROM multas m WHERE m.id_usuario = u.id_usuario AND m.estado = 'pendiente') AS multas
     FROM usuarios u
     WHERE u.estado = 'activo'
     ORDER BY u.nombre, u.apellidos"
)->fetchAll();

$equipos = $pdo->query(
    "SELECT e.id_equipo, e.codigo, e.nombre, c.nombre AS categoria
     FROM equipos e
     JOIN categorias c ON c.id_categoria = e.id_categoria
     WHERE e.estado = 'disponible'
     ORDER BY c.nombre, e.codigo"
)->fetchAll();

$titulo = 'Nuevo préstamo';
$activo = 'prestamos';
require __DIR__ . '/../../includes/header.php';
page_head($titulo);
render_errores($errores);
?>
<?php if (!$equipos): ?>
  <div class="alert alert-warning">No hay equipos disponibles en este momento. Cuando se registre una devolución volverán a aparecer aquí.</div>
<?php else: ?>
<form method="post" class="panel form-estrecho">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="id_usuario">Usuario que recibe el equipo</label>
    <select class="form-select" id="id_usuario" name="id_usuario" required>
      <option value="">Seleccione un usuario</option>
      <?php foreach ($usuarios as $f): ?>
        <option value="<?= (int) $f['id_usuario'] ?>"<?= selected($form['id_usuario'], $f['id_usuario']) ?><?= (int) $f['multas'] > 0 ? ' disabled' : '' ?>>
          <?= e($f['nombre'] . ' ' . $f['apellidos'] . ' · ' . $f['cedula']) ?><?= (int) $f['multas'] > 0 ? ' (tiene multas pendientes)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label" for="id_equipo">Equipo disponible</label>
    <select class="form-select" id="id_equipo" name="id_equipo" required>
      <option value="">Seleccione un equipo</option>
      <?php foreach ($equipos as $f): ?>
        <option value="<?= (int) $f['id_equipo'] ?>"<?= selected($form['id_equipo'], $f['id_equipo']) ?>>
          <?= e($f['codigo'] . ' · ' . $f['nombre'] . ' (' . $f['categoria'] . ')') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label" for="fecha_limite">Fecha y hora límite de devolución</label>
    <input class="form-control" type="datetime-local" id="fecha_limite" name="fecha_limite" required value="<?= e($form['fecha_limite']) ?>">
    <div class="form-text">Después de esta fecha se genera una multa de <?= e(colones(MULTA_POR_DIA)) ?> por cada día de retraso.</div>
  </div>
  <div class="mb-4">
    <label class="form-label" for="observaciones">Observaciones (opcional)</label>
    <input class="form-control" id="observaciones" name="observaciones" maxlength="255" value="<?= e($form['observaciones']) ?>">
  </div>
  <button class="btn btn-primary" type="submit">Registrar préstamo</button>
  <a class="btn btn-link" href="index.php">Cancelar</a>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
