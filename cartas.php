<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'cartas';
$titulo = 'Cartas de presentación — ' . SITE_NOMBRE;

require_once 'includes/cabecera.php';
?>

<section class="hero-mini">
    <div class="container">
        <h1 class="mb-2">Cartas de presentación</h1>
        <p class="mb-0">Documentos listos para descargar e imprimir o compartir.</p>
    </div>
</section>

<section class="seccion">
    <div class="container">
        <div class="row g-4">
            <?php
            $cartas = $pdo->query("SELECT * FROM cartas WHERE activo = 1 ORDER BY creado_en DESC")->fetchAll();
            if ($cartas):
                foreach ($cartas as $c):
                    $extracto = strip_tags($c['contenido'] ?? '');
            ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-fv p-4 h-100 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="icono-caja"><i class="bi bi-file-earmark-text"></i></div>
                                <span class="badge bg-fv"><?= e(ucfirst(str_replace('-', ' ', $c['slug'] ?? 'carta'))) ?></span>
                            </div>
                            <h5><?= e($c['titulo']) ?></h5>
                            <p class="small text-secondary mb-3"><?= e(mb_strimwidth($extracto, 0, 110, '…')) ?></p>
                            <p class="small text-muted mb-3"><i class="bi bi-calendar3 me-1"></i><?= e(date('d M Y', strtotime($c['creado_en']))) ?></p>
                            <div class="mt-auto d-flex gap-2 flex-wrap">
                                <a href="carta.php?slug=<?= e($c['slug']) ?>" class="btn btn-sm btn-fv"><i class="bi bi-eye me-1"></i>Ver</a>
                                <button type="button" class="btn btn-sm btn-outline-fv no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                            </div>
                        </div>
                    </div>
            <?php endforeach; else: ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-file-earmark-x fs-1 texto-azul d-block mb-3"></i>
                    <h5>No hay cartas disponibles</h5>
                    <p class="text-secondary">Contacta con nosotros y te haremos llegar una carta de presentación.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/pie.php'; ?>