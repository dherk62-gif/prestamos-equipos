<?php
// Funciones de apoyo: escape de HTML, URLs, mensajes, CSRF y formatos.

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

function redirect(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

function es_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// Lee un campo de $_POST sin espacios sobrantes.
function post(string $campo): string
{
    return trim((string) ($_POST[$campo] ?? ''));
}

function selected($actual, $valor): string
{
    return (string) $actual === (string) $valor ? ' selected' : '';
}

/* ---------- Mensajes de una sola vez (flash) ---------- */

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_render(): void
{
    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<div class="alert alert-' . e($f['tipo']) . ' alert-dismissible fade show" role="alert">'
           . e($f['mensaje'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>'
           . '</div>';
    }
    unset($_SESSION['flash']);
}

/* ---------- Protección CSRF para formularios POST ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('El formulario expiró. Regrese a la página anterior, recárguela e intente de nuevo.');
    }
}

/* ---------- Formatos ---------- */

function fecha($valor, bool $conHora = true): string
{
    if (!$valor) {
        return '—';
    }
    return date($conHora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($valor));
}

function colones($monto): string
{
    return '₡' . number_format((float) $monto, 0, ',', '.');
}

function estado_badge(string $estado): string
{
    $etiquetas = [
        'disponible'    => 'Disponible',
        'prestado'      => 'Prestado',
        'mantenimiento' => 'En mantenimiento',
        'baja'          => 'De baja',
        'activo'        => 'En curso',
        'vencido'       => 'Vencido',
        'devuelto'      => 'Devuelto',
        'pendiente'     => 'Pendiente',
        'pagada'        => 'Pagada',
        'inactivo'      => 'Inactivo',
    ];
    $texto = $etiquetas[$estado] ?? ucfirst($estado);
    return '<span class="estado estado-' . e($estado) . '">' . e($texto) . '</span>';
}
