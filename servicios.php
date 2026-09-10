<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'servicios';
$titulo = 'Servicios — ' . SITE_NOMBRE;

require_once 'includes/cabecera.php';
?>

<section class="hero-mini">
    <div class="container">
        <h1 class="mb-2">Nuestros servicios</h1>
        <p class="mb-0">Soluciones digitales completas para impulsar tu negocio.</p>
    </div>
</section>

<section class="seccion">
    <div class="container">
        <div class="row g-4">
            <?php foreach ($pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY destaque DESC, id ASC") as $s): ?>
                <div class="col-md-6 col-lg-3 animar">
                    <div class="card-fv p-4 h-100 d-flex flex-column">
                        <div class="icono-caja <?= $s['destaque'] ? 'destacado' : '' ?> mb-3"><i class="bi <?= e($s['icono']) ?>"></i></div>
                        <h5><?= e($s['titulo']) ?></h5>
                        <p class="small text-secondary mb-3"><?= e($s['descripcion_corta']) ?></p>
                        <?php if ($s['descripcion']): ?>
                            <p class="small text-secondary mb-3"><?= e($s['descripcion']) ?></p>
                        <?php endif; ?>
                        <?php if ($s['precio_desde']): ?>
                            <p class="precio-desde mt-auto mb-3">Desde <b><?= e(formatear_precio((float)$s['precio_desde'])) ?></b></p>
                        <?php endif; ?>
                        <a href="solicitud.php?servicio=<?= e($s['slug']) ?>" class="btn btn-outline-fv w-100">Solicitar este servicio</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <p class="text-secondary">¿Tienes un proyecto especial?</p>
            <a href="contacto.php" class="btn btn-fv">Contáctanos directamente <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    </div>
</section>

<?php require_once 'includes/pie.php'; ?>