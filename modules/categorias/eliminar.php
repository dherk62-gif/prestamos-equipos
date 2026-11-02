<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador', 'encargado');

if (!es_post()) {
    redirect('modules/categorias/index.php');
}
csrf_check();

try {
    $stmt = db()->prepare("DELETE FROM categorias WHERE id_categoria = :id");
    $stmt->execute([':id' => (int) post('id')]);
    flash('success', 'Categoría eliminada.');
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        flash('danger', 'No se puede eliminar: hay equipos en esta categoría. Cámbielos de categoría primero.');
    } else {
        throw $e;
    }
}
redirect('modules/categorias/index.php');
