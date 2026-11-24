<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

if (!es_post()) {
    redirect('modules/multas/index.php');
}
csrf_check();

$stmt = db()->prepare("UPDATE multas SET estado = 'pagada', fecha_pago = :f WHERE id_multa = :id AND estado = 'pendiente'");
$stmt->execute([':f' => date('Y-m-d H:i:s'), ':id' => (int) post('id')]);

if ($stmt->rowCount() > 0) {
    flash('success', 'Multa marcada como pagada. El usuario ya puede pedir préstamos si no tiene otras pendientes.');
} else {
    flash('danger', 'Esa multa no existe o ya estaba pagada.');
}
redirect('modules/multas/index.php');
