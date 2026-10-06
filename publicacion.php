<?php
require_once __DIR__ . '/funciones.php';
$slug = trim($_GET['slug'] ?? '');
$stmt = $pdo->prepare("SELECT * FROM publicaciones WHERE slug = ? AND activo = 1 LIMIT 1");
$stmt->execute([$slug]);
$pub = $stmt->fetch();

$titulo = ($pub ? $pub['titulo'] : 'Publicación no encontrada') . ' — ' . SITE_NOMBRE;

require_once 'includes/cabecera.php';
?>

<?php if ($pub): ?>
    <section class="hero-mini">
        <div class="container">
            <a href="publicaciones.php" class="btn-enlace d-inline-flex align-items-center mb-3"><i class="bi bi-arrow-left me-1"></i>Volver a publicaciones</a>
            <h1 class="mb-2"><?= e($pub['titulo']) ?></h1>
            <small class="text-white-50"><i class="bi bi-calendar3 me-1"></i><?= e(date('d M Y', strtotime($pub['creado_en']))) ?></small>
        </div>
    </section>

    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <?php if ($pub['imagen']): ?>
                        <img src="<?= e($pub['imagen']) ?>" alt="<?= e($pub['titulo']) ?>" class="img-fluid rounded-4 shadow-sm mb-4 w-100" style="max-height:440px;object-fit:cover;">
                    <?php endif; ?>
                    <div class="contenido-nice">
                        <?= $pub['contenido'] ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="seccion">
        <div class="container">
            <div class="text-center py-5">
                <i class="bi bi-journal-x fs-1 texto-azul d-block mb-3"></i>
                <h4>Publicación no encontrada</h4>
                <p class="text-secondary">La publicación que buscas no existe o fue retirada.</p>
                <a href="publicaciones.php" class="btn btn-fv">Ver todas las publicaciones</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require_once 'includes/pie.php'; ?>