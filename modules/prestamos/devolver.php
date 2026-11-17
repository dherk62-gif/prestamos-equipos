<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

if (!es_post()) {
    redirect('modules/prestamos/index.php');
}
csrf_check();

$pdo = db();
$id  = (int) post('id');
$tipo    = 'success';
$mensaje = '';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id_prestamo, id_usuario, id_equipo, fecha_limite, estado
         FROM prestamos WHERE id_prestamo = :id FOR UPDATE"
    );
    $stmt->execute([':id' => $id]);
    $p = $stmt->fetch();

    if (!$p || $p['estado'] !== 'activo') {
        throw new RuntimeException('Ese préstamo no existe o ya fue devuelto.');
    }

    $ahora  = new DateTime();
    $limite = new DateTime($p['fecha_limite']);

    $pdo->prepare("UPDATE prestamos SET estado = 'devuelto', fecha_devolucion = :f WHERE id_prestamo = :id")
        ->execute([':f' => $ahora->format('Y-m-d H:i:s'), ':id' => $id]);

    // Al devolver, el equipo vuelve a estar disponible.
    $pdo->prepare("UPDATE equipos SET estado = 'disponible' WHERE id_equipo = :id")
        ->execute([':id' => $p['id_equipo']]);

    $mensaje = 'Devolución registrada. El equipo está disponible de nuevo.';

    // Regla de negocio: si la devolución supera la fecha límite se genera una multa automática.
    if ($ahora > $limite) {
        $dias  = (int) ceil(($ahora->getTimestamp() - $limite->getTimestamp()) / 86400);
        $monto = $dias * MULTA_POR_DIA;

        $pdo->prepare(
            "INSERT INTO multas (id_prestamo, id_usuario, dias_retraso, monto)
             VALUES (:prestamo, :usuario, :dias, :monto)"
        )->execute([':prestamo' => $id, ':usuario' => $p['id_usuario'], ':dias' => $dias, ':monto' => $monto]);

        $tipo     = 'warning';
        $mensaje .= ' Se generó una multa de ' . colones($monto) . ' por ' . $dias . ' día(s) de retraso.';
    }

    $pdo->commit();
    flash($tipo, $mensaje);
} catch (RuntimeException $ex) {
    $pdo->rollBack();
    flash('danger', $ex->getMessage());
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $ex;
}

redirect('modules/prestamos/index.php');
