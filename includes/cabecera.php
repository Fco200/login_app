<?php
require_once __DIR__ . '/../funciones.php';
if (!isset($seccion)) $seccion = 'inicio';
$datos = datos_sitio();
$telefono = dato_sitio('telefono', SITE_TELEFONO);
$email = dato_sitio('email', SITE_EMAIL);
$horario = dato_sitio('horario', SITE_HORARIO);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(dato_sitio('hero_subtitulo', SITE_HERO_SUBTITULO)) ?>">
    <meta name="theme-color" content="#071c3d">
    <title><?= e($titulo ?? SITE_NOMBRE . ' — ' . SITE_ESLOGAN) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>

<!-- Barra superior -->
<div class="topbar py-2 no-print">
    <div class="container d-flex flex-wrap justify-content-center justify-content-md-between gap-2 align-items-center">
        <div class="d-flex flex-wrap gap-3">
            <span><i class="bi bi-telephone"></i><a href="tel:+52<?= e(preg_replace('/\D/', '', $telefono)) ?>"><?= e($telefono) ?></a></span>
            <span class="d-none d-sm-inline"><i class="bi bi-envelope"></i><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></span>
            <span class="d-none d-lg-inline"><i class="bi bi-clock"></i><?= e($horario) ?></span>
        </div>
        <div>
            <a href="<?= e(dato_sitio('facebook')) ?>" class="me-2 d-inline-block" <?= dato_sitio('facebook') ? '' : 'style="display:none !important;"' ?>><i class="bi bi-facebook"></i></a>
            <a href="<?= e(dato_sitio('instagram')) ?>" class="me-2 d-inline-block" <?= dato_sitio('instagram') ? '' : 'style="display:none !important;"' ?>><i class="bi bi-instagram"></i></a>
            <a href="<?= e(dato_sitio('tiktok')) ?>" class="d-inline-block" <?= dato_sitio('tiktok') ? '' : 'style="display:none !important;"' ?>><i class="bi bi-tiktok"></i></a>
        </div>
    </div>
</div>

<!-- Navegación -->
<nav class="navbar navbar-expand-lg navbar-fv sticky-top no-print">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="assets/img/logo.png" alt="<?= e(SITE_NOMBRE) ?>" class="logo-img" style="object-fit:contain;">
            <span class="logo-txt"><b><?= e(SITE_NOMBRE) ?></b><small><?= e(SITE_ESLOGAN) ?></small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPublico" aria-controls="menuPublico" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuPublico">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link <?= $seccion === 'inicio' ? 'active' : '' ?>" href="index.php">Inicio</a></li>
                <li class="nav-item"><a class="nav-link <?= $seccion === 'servicios' ? 'active' : '' ?>" href="servicios.php">Servicios</a></li>
                <li class="nav-item"><a class="nav-link <?= $seccion === 'proyectos' ? 'active' : '' ?>" href="proyectos.php">Proyectos</a></li>
                <li class="nav-item"><a class="nav-link <?= $seccion === 'publicaciones' ? 'active' : '' ?>" href="publicaciones.php">Publicaciones</a></li>
                <li class="nav-item"><a class="nav-link <?= $seccion === 'cartas' ? 'active' : '' ?>" href="cartas.php">Cartas</a></li>
                <li class="nav-item"><a class="nav-link <?= $seccion === 'contacto' ? 'active' : '' ?>" href="contacto.php">Contacto</a></li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a href="solicitud.php" class="btn btn-fv btn-sm">Cotizar <i class="bi bi-arrow-right ms-1"></i></a>
                </li>
                <?php if (esta_logueado()): ?>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <div class="dropdown">
                            <button class="btn btn-outline-fv btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i><span class="text-truncate" style="max-width:140px;display:inline-block;vertical-align:bottom;"><?= e($_SESSION['nombre'] ?? 'Mi cuenta') ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item" href="clientes"><i class="bi bi-person-circle me-1"></i>Mi portal</a></li>
                                <li><a class="dropdown-item" href="clientes/mis-solicitudes"><i class="bi bi-inbox me-1"></i>Mis solicitudes</a></li>
                                <li><a class="dropdown-item" href="clientes/chat"><i class="bi bi-chat-dots me-1"></i>Mensajes con el negocio</a></li>
                                <li><a class="dropdown-item" href="solicitud.php"><i class="bi bi-send me-1"></i>Nueva solicitud</a></li>
                                <?php if (esta_admin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="admin/index.php"><i class="bi bi-grid me-1"></i>Panel de administración</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="cerrar-sesion.php"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión</a></li>
                            </ul>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a href="acceso" class="btn btn-outline-fv btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>