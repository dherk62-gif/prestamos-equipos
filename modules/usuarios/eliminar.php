<?php
require __DIR__ . '/../../includes/init.php';
require_rol('administrador');   // Regla de negocio: solo el administrador elimina usuarios

if (!es_post()) {
    redirect('modules/usuarios/index.php');
}
csrf_check();

$id = (int) post('id');

if ($id === usuario_actual()['id']) {
    flash('danger', 'No puede eliminar su propia cuenta.');
    redirect('modules/usuarios/index.php');
}

try {
    $stmt = db()->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
    $stmt->execute([':id' => $id]);
    flash('success', 'Usuario eliminado.');
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        flash('danger', 'No se puede eliminar: el usuario tiene préstamos o multas en el historial. Márquelo como inactivo.');
    } else {
        throw $e;
    }
}
redirect('modules/usuarios/index.php');
