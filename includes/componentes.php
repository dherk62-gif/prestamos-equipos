<?php
// Piezas de interfaz que se repiten en varias pantallas.

function page_head(string $titulo, string $acciones = ''): void
{
    echo '<div class="page-head"><h1>' . e($titulo) . '</h1>'
       . '<div class="page-actions">' . $acciones . '</div></div>';
}

function render_errores(array $errores): void
{
    if (!$errores) {
        return;
    }
    echo '<div class="alert alert-danger" role="alert"><strong>Revise estos datos:</strong><ul class="mb-0">';
    foreach ($errores as $error) {
        echo '<li>' . e($error) . '</li>';
    }
    echo '</ul></div>';
}

// Botón de eliminar: envía POST con token CSRF y pide confirmación (assets/js/app.js).
function form_eliminar(string $ruta, int $id, string $confirmacion): string
{
    return '<form method="post" action="' . e(url($ruta)) . '" class="d-inline" data-confirm="' . e($confirmacion) . '">'
         . csrf_field()
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<button class="btn btn-sm btn-outline-danger">Eliminar</button></form>';
}

// Tabla de préstamos usada en el panel, el listado y los reportes.
// $filas viene de la vista v_prestamos_detalle.
function render_tabla_prestamos(array $filas, bool|array $acciones = false, bool|array $verUsuario = true): void
{
    if (!$filas) {
        echo '<p class="vacio">No hay préstamos que mostrar con estos filtros.</p>';
        return;
    }
    // Asegurarnos de que verUsuario sea booleano para el condicional de la vista si pasan otra cosa por error
    $verUsuario = is_bool($verUsuario) ? $verUsuario : (bool)$verUsuario;
    $acciones = is_bool($acciones) ? $acciones : !empty($acciones);
    ?>
    <div class="table-responsive">
      <table class="table tabla align-middle">
        <thead>
          <tr>
            <th>Equipo</th>
            <?php if ($verUsuario): ?><th>Usuario</th><?php endif; ?>
            <th>Salida</th>
            <th>Fecha límite</th>
            <th>Devolución</th>
            <th>Estado</th>
            <?php if ($acciones): ?><th class="text-end">Acción</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $f): ?>
          <tr>
            <td><span class="codigo"><?= e($f['equipo_codigo']) ?></span><br><?= e($f['equipo_nombre']) ?></td>
            <?php if ($verUsuario): ?>
              <td><?= e($f['usuario']) ?><br><small class="text-muted"><?= e($f['cedula']) ?></small></td>
            <?php endif; ?>
            <td><?= e(fecha($f['fecha_salida'])) ?></td>
            <td><?= e(fecha($f['fecha_limite'])) ?></td>
            <td><?= e(fecha($f['fecha_devolucion'])) ?></td>
            <td>
              <?= estado_badge($f['estado']) ?>
              <?php if ((int) $f['dias_retraso'] > 0): ?>
                <br><small class="text-danger">Retraso: <?= (int) $f['dias_retraso'] ?> d</small>
              <?php endif; ?>
            </td>
            <?php if ($acciones): ?>
              <td class="text-end">
                <?php if ($f['estado'] !== 'devuelto'): ?>
                  <form method="post" action="<?= e(url('modules/prestamos/devolver.php')) ?>"
                        data-confirm="¿Registrar la devolución de <?= e($f['equipo_codigo']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $f['id_prestamo'] ?>">
                    <button class="btn btn-sm btn-primary">Registrar devolución</button>
                  </form>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}