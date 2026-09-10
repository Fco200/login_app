<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'publicaciones';
$titulo = 'Publicaciones — ' . SITE_NOMBRE;

require_once 'includes/cabecera.php';
?>

<section class="hero-mini">
    <div class="container">
        <h1 class="mb-2">Publicaciones</h1>
        <p class="mb-0">Tips, novedades y contenido digital para tu negocio.</p>
    </div>
</section>

<section class="seccion">
    <div class="container">
        <div class="row g-4">
            <?php
            $pubs = $pdo->query("SELECT * FROM publicaciones WHERE activo = 1 ORDER BY creado_en DESC")->fetchAll();
            if ($pubs):
                foreach ($pubs as $pub):
            ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-fv card-articulo h-100">
                            <div class="img-wrap">
                                <?php if ($pub['imagen']): ?>
                                    <img src="<?= e($pub['imagen']) ?>" alt="<?= e($pub['titulo']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="placeholder-img"><i class="bi bi-newspaper"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 d-flex flex-column">
                                <small class="text-muted text-uppercase" style="letter-spacing:1px;font-size:.72rem;"><i class="bi bi-calendar3 me-1"></i><?= e(date('d M Y', strtotime($pub['creado_en']))) ?></small>
                                <h5 class="mt-2"><a href="publicacion.php?slug=<?= e($pub['slug']) ?>" style="color:inherit;"><?= e($pub['titulo']) ?></a></h5>
                                <p class="small text-secondary mb-3"><?= e($pub['resumen']) ?></p>
                                <a href="publicacion.php?slug=<?= e($pub['slug']) ?>" class="btn-enlace mt-auto">Leer más <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
            <?php endforeach; else: ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-journal-text fs-1 texto-azul d-block mb-3"></i>
                    <h5>No hay publicaciones todavía</h5>
                    <p class="text-secondary">Vuelve pronto, estaremos publicando contenido nuevo.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/pie.php'; ?>