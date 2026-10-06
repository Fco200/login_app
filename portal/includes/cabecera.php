<?php
/* Cabecera del Portal de Clientes de FV Digital */
require_once __DIR__ . '/../../funciones.php';
requiere_sesion();

if (!isset($seccionPortal)) $seccionPortal = 'inicio';

$usuario = sesion_actual() ?? [
    'id'    => (int)$_SESSION['usuario_id'],
    'nombre' => $_SESSION['nombre'] ?? 'Cliente',
    'email'  => '',
];

$noNotif = contar_no_leidas('notificaciones', (int)$usuario['id']);
$noChat  = contar_no_leidas('mensajes_portal', (int)$usuario['id']);

/* Contador del carrito */
$noCarrito = 0;
try {
    $stmtCarrito = $GLOBALS['pdo']->prepare('SELECT COALESCE(SUM(cantidad),0) FROM carrito WHERE usuario_id = ?');
    $stmtCarrito->execute([(int)$usuario['id']]);
    $noCarrito = (int)$stmtCarrito->fetchColumn();
} catch (Throwable $e) {
    $noCarrito = 0;
}

$enlacesPortal = [
    'inicio'       => ['panel',            'Panel',            'bi-speedometer2'],
    'solicitudes'  => ['mis-solicitudes',  'Mis solicitudes',  'bi-inbox'],
    'solicitud'    => ['nueva-solicitud',  'Nueva solicitud',  'bi-send'],
    'solicitud-empresa' => ['nueva-solicitud-empresa', 'Para empresas', 'bi-building'],
    'procesos'     => ['procesos',         'Mis procesos',     'bi-bezier2'],
    'pagos'        => ['pagos',            'Mis pagos',        'bi-credit-card'],
    'facturacion'  => ['facturacion',      'Facturación',      'bi-receipt-cutoff'],
    'mensajes'     => ['chat',             'Mensajes',         'bi-chat-dots'],
    'soporte'      => ['soporte',           'Soporte',          'bi-headset'],
    'juego'        => ['juegos',           'Juegos',           'bi-controller'],
    'perfil'       => ['mi-perfil',        'Mi perfil',        'bi-person-circle'],
];
// Las URLs amigables requieren el .htaccess del portal; en caso de que el
// servidor no lo aplique, siempre existe un equivalente .php que funciona.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? 'Portal de clientes') ?> — <?= e(SITE_NOMBRE) ?></title>
    <meta name="theme-color" content="#071c3d">
    <base href="<?= e(url_sitio('portal/')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/estilos.css?v=20260910">
    <link rel="stylesheet" href="../assets/css/portal.css?v=20260910">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body class="portal-body">

<!-- Barra de navegación del portal -->
<nav class="navbar navbar-expand-lg navbar-fv sticky-top portal-nav no-print">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="panel">
            <img src="../assets/img/logo.png" alt="<?= e(SITE_NOMBRE) ?>" class="logo-img" style="object-fit:contain;">
            <span class="logo-txt"><b><?= e(SITE_NOMBRE) ?></b><small>Portal de clientes</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPortal" aria-controls="menuPortal" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuPortal">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php foreach ($enlacesPortal as $clave => $enlace): [$url, $texto, $icono] = $enlace; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $seccionPortal === $clave ? 'active' : '' ?>" href="<?= $url ?>">
                            <i class="bi <?= $icono ?> me-1"></i><?= $texto ?>
                            <?php if ($clave === 'mensajes' && $noChat > 0): ?>
                                <span class="badge text-bg-danger ms-1 notif-dot"><?= $noChat ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="configuracion" title="Configuración">
                        <i class="bi bi-gear fs-6"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="carrito" title="Mi carrito">
                        <i class="bi bi-cart3 fs-6"></i>
                        <?php if ($noCarrito > 0): ?>
                            <span class="badge text-bg-primary rounded-pill notif-dot" data-carrito-badge><?= $noCarrito ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-primary rounded-pill notif-dot" data-carrito-badge style="display:none;">0</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="notificaciones" title="Notificaciones">
                        <i class="bi bi-bell fs-6"></i>
                        <?php if ($noNotif > 0): ?>
                            <span class="badge text-bg-danger rounded-pill notif-dot"><?= $noNotif ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <div class="dropdown">
                        <button class="btn btn-outline-fv btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?= e($usuario['nombre']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="mi-perfil"><i class="bi bi-person-circle me-1"></i>Mi perfil</a></li>
                            <li><a class="dropdown-item" href="configuracion"><i class="bi bi-gear me-1"></i>Configuración</a></li>
                            <li><a class="dropdown-item" href="notificaciones"><i class="bi bi-bell me-1"></i>Notificaciones</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../index.php"><i class="bi bi-globe2 me-1"></i>Ver sitio público</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="salir"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="portal-main">
    <div class="container py-4">
        <?= mostrar_flash() ?>