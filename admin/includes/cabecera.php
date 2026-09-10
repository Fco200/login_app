<?php
/* Cabecera del panel administrativo de FV Digital */
require_once __DIR__ . '/../../funciones.php';
requiere_admin();

$archivo = basename($_SERVER['PHP_SELF']);
if (!isset($seccionAdmin)) $seccionAdmin = $archivo;

$enlaces = [
    'index.php'        => ['Dashboard', 'bi-speedometer2'],
    'servicios.php'    => ['Servicios', 'bi-grid'],
    'proyectos.php'    => ['Proyectos / Plantillas', 'bi-briefcase'],
    'publicaciones.php'=> ['Publicaciones', 'bi-journal-text'],
    'cartas.php'       => ['Cartas de presentación', 'bi-envelope-paper'],
    'testimonios.php'  => ['Testimonios', 'bi-chat-quote'],
    'solicitudes.php'  => ['Solicitudes', 'bi-inbox'],
    'mensajes.php'     => ['Mensajes', 'bi-envelope'],
    'mensajes_portal.php' => ['Chat del portal', 'bi-chat-dots'],
    'soporte.php'      => ['Soporte / Reportes', 'bi-headset'],
    'suscripciones.php'=> ['Suscripciones', 'bi-envelope-heart'],
    'usuarios.php'     => ['Usuarios', 'bi-people'],
    'config.php'       => ['Configuración', 'bi-gear'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? '') ?> — Panel FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/estilos.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body class="admin-body">
<div class="d-flex align-items-stretch">

    <!-- Barra lateral -->
    <aside class="admin-sidebar d-none d-lg-block" style="width:260px;flex-shrink:0;">
        <div class="logo d-flex align-items-center gap-2">
            <img src="../assets/img/logo.png" alt="FV Digital" style="height:42px;width:auto;object-fit:contain;">
            <div class="line-height-1">
                <b class="text-white d-block">FV DIGITAL</b>
                <small>Panel de administración</small>
            </div>
        </div>
        <nav class="nav flex-column mt-3">
            <?php foreach ($enlaces as $arch => $info): ?>
                <a class="nav-link <?= $archivo === $arch ? 'active' : '' ?>" href="<?= $arch ?>">
                    <i class="bi <?= $info[1] ?>"></i><?= $info[0] ?>
                    <?php if ($arch === 'solicitudes.php'): $n = (int)contar_registros('solicitudes', "estado = 'nueva'"); ?>
                        <?php if ($n > 0): ?><span class="badge text-bg-danger ms-1"><?= $n ?></span><?php endif; ?>
                    <?php elseif ($arch === 'mensajes.php'): $n = (int)contar_registros('mensajes_contacto', 'leido = 0'); ?>
                        <?php if ($n > 0): ?><span class="badge text-bg-warning ms-1"><?= $n ?></span><?php endif; ?>
                    <?php elseif ($arch === 'mensajes_portal.php'): $n = (int)$pdo->query("SELECT COUNT(*) FROM mensajes_portal WHERE remitente = 'cliente' AND leido = 0")->fetchColumn(); ?>
                        <?php if ($n > 0): ?><span class="badge text-bg-danger ms-1"><?= $n ?></span><?php endif; ?>
                    <?php elseif ($arch === 'soporte.php'): $n = (int)contar_registros('soporte', "estado = 'nuevo'"); ?>
                        <?php if ($n > 0): ?><span class="badge text-bg-danger ms-1"><?= $n ?></span><?php endif; ?>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <nav class="nav flex-column mt-4 border-top pt-3" style="border-color:rgba(255,255,255,.1)!important;">
            <a class="nav-link <?= $archivo === 'perfil.php' ? 'active' : '' ?>" href="perfil.php"><i class="bi bi-person-circle"></i>Mi perfil</a>
            <a class="nav-link" href="cerrar.php"><i class="bi bi-box-arrow-right"></i>Cerrar sesión</a>
        </nav>
    </aside>

    <!-- Móvil: menú colapsable -->
    <nav class="navbar navbar-dark d-lg-none w-100 sticky-top" style="background:#071c3d;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="../assets/img/logo.png" alt="FV Digital" style="height:34px;object-fit:contain;">
                <b>FV DIGITAL</b>
            </a>
            <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuMovil" aria-controls="menuMovil">
                <i class="bi bi-list fs-4"></i>
            </button>
        </div>
    </nav>
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="menuMovil" style="background:#071c3d;color:#cfe1ff;width:270px;">
        <div class="offcanvas-header">
            <h5 class="text-white offcanvas-title"><img src="../assets/img/logo.png" alt="" style="height:34px;object-fit:contain;"> FV DIGITAL</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body p-0">
            <?php foreach ($enlaces as $arch => $info): ?>
                <a class="nav-link text-light d-block px-4 py-2 <?= $archivo === $arch ? 'text-primary fw-bold' : '' ?>" href="<?= $arch ?>"><i class="bi <?= $info[1] ?> me-2"></i><?= $info[0] ?></a>
            <?php endforeach; ?>
            <hr class="opacity-25">
            <a class="nav-link text-light d-block px-4 py-2 <?= $archivo === 'perfil.php' ? 'text-primary fw-bold' : '' ?>" href="perfil.php"><i class="bi bi-person-circle me-2"></i>Mi perfil</a>
            <a class="nav-link text-light d-block px-4 py-2" href="cerrar.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a>
        </div>
    </div>

    <!-- Contenido -->
    <main class="contenido-admin flex-grow-1">
        <div class="topbar-admin d-flex flex-wrap justify-content-between align-items-center px-4 py-3 sticky-top" style="top:0;">
            <div>
                <h5 class="mb-0 fw-bold"><?= e($titulo ?? 'PANEL') ?></h5>
                <small class="text-muted"><?= e($subtitulo ?? 'Administración de FV Digital') ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="../../index.php" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-globe2 me-1"></i>Ver sitio</a>
                <span class="badge bg-light text-dark border d-none d-md-inline" id="chipSesion" title="Tiempo restante de sesión (se renueva con tu actividad)"><i class="bi bi-hourglass-split me-1"></i><span id="sesionRestante">--:--</span></span>
                <span class="small d-none d-md-inline"><i class="bi bi-person-circle me-1"></i><?= e($_SESSION['admin_nombre'] ?? $_SESSION['nombre'] ?? 'Admin') ?><span class="badge bg-primary text-uppercase ms-1"><?= e($_SESSION['admin_rol'] ?? 'admin') ?></span></span>
            </div>
        </div>
        <div class="p-4">
            <?php mostrar_flash(); ?>