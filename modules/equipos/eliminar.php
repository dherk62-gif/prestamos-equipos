<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador');   // Regla de negocio: solo el administrador elimina equipos

if (!es_post()) {
    redirect('modules/equipos/index.php');
}
csrf_check();

try {
    $stmt = db()->prepare("DELETE FROM equipos WHERE id_equipo = :id");
    $stmt->execute([':id' => (int) post('id')]);
    flash('success', 'Equipo eliminado.');
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        flash('danger', 'No se puede eliminar: el equipo tiene préstamos en el historial. Márquelo como "De baja" en su lugar.');
    } else {
        throw $e;
    }
}
redirect('modules/equipos/index.php');
