<?php
// Sesión y control de acceso por rol (administrador, encargado, usuario).

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function tiene_rol(string ...$roles): bool
{
    $u = usuario_actual();
    return $u !== null && in_array($u['rol'], $roles, true);
}

// Administrador y encargado trabajan con el inventario y los préstamos.
function es_staff(): bool
{
    return tiene_rol('administrador', 'encargado');
}

function require_login(): void
{
    if (usuario_actual() === null) {
        redirect('login.php');
    }
}

function require_rol(string ...$roles): void
{
    require_login();

    if (!tiene_rol(...$roles)) {
        http_response_code(403);
        $titulo = 'Acceso denegado';
        $activo = '';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-danger">Su rol no tiene permiso para entrar a esta sección.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}
