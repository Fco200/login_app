<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'proyectos';
$titulo = 'Proyectos y plantillas — ' . SITE_NOMBRE;

$cat = trim($_GET['cat'] ?? '');
$catValida = in_array($cat, ['web', 'app', 'template', 'branding'], true) ? $cat : '';

$sql = "SELECT * FROM proyectos WHERE activo = 1";
$params = [];
if ($catValida) { $sql .= " AND categoria = ?"; $params[] = $catValida; }
$sql .= " ORDER BY destaque DESC, creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proyectos = $stmt->fetchAll();

$categorias = [
    ['web', 'bi-browser-chrome', 'Sitios web'],
    ['app', 'bi-phone', 'Apps'],
    ['template', 'bi-window-stack', 'Plantillas'],
    ['branding', 'bi-palette', 'Branding'],
];

require_once 'includes/cabecera.php';
?>

<section class="hero-mini">
    <div class="container">
        <h1 class="mb-2">Proyectos y plantillas</h1>
        <p class="mb-0">Estos son nuestros últimos proyectos y plantillas listas para personalizar.</p>
    </div>
</section>

<!-- FILTRO -->
<section class="seccion pb-0 no-print">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
            <a href="proyectos.php" class="btn btn-sm <?= $catValida ? 'btn-outline-fv' : 'btn-fv' ?>">Todos</a>
            <?php foreach ($categorias as $c): ?>
                <a href="proyectos.php?cat=<?= $c[0] ?>" class="btn btn-sm <?= $catValida === $c[0] ? 'btn-fv' : 'btn-outline-fv' ?>">
                    <i class="bi <?= $c[1] ?> me-1"></i><?= $c[2] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="seccion pt-0">
    <div class="container">
        <?php if ($proyectos): ?>
            <div class="row g-4">
                <?php foreach ($proyectos as $p): ?>
                    <div class="col-md-6 col-lg-4 animar" id="proyecto<?= (int)$p['id'] ?>">
                        <div class="card-fv card-proyecto h-100">
                            <div class="img-wrap">
                                <?php if ($p['imagen']): ?>
                                    <img src="<?= e($p['imagen']) ?>" alt="<?= e($p['titulo']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="placeholder-img"><i class="bi bi-briefcase"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 d-flex flex-column">
                                <span class="badge-cat mb-2"><?= e($p['categoria']) ?></span>
                                <h5><?= e($p['titulo']) ?></h5>
                                <p class="small text-secondary mb-3"><?= e($p['descripcion']) ?></p>
                                <div class="mt-auto d-flex flex-row gap-2 flex-wrap">
                                    <?php if ($p['url']): ?>
                                        <a href="<?= e($p['url']) ?>" target="_blank" class="btn btn-sm btn-outline-fv"><i class="bi bi-link-45deg me-1"></i>Ver demo</a>
                                    <?php endif; ?>
                                    <?php if ($p['archivo']): ?>
                                        <a href="<?= e($p['archivo']) ?>" target="_blank" class="btn btn-sm btn-fv"><i class="bi bi-download me-1"></i>Descargar plantilla</a>
                                    <?php endif; ?>
                                    <a href="solicitud.php" class="btn btn-sm btn-outline-fv"><i class="bi bi-send me-1"></i>Solicitar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 texto-azul d-block mb-3"></i>
                <h5>No hay proyectos en esta categoría</h5>
                <p class="text-secondary">Pronto estaremos publicando nuevos proyectos.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/pie.php'; ?>