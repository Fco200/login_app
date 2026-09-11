<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'productos';
$titulo = 'Productos — ' . SITE_NOMBRE;

require_once 'includes/cabecera.php';
?>

<section class="hero-mini">
    <div class="container">
        <h1 class="mb-2">Productos y plantillas</h1>
        <p class="mb-0">Listos para usar y agregar directo a tu carrito.</p>
    </div>
</section>

<section class="seccion">
    <div class="container">
        <?php $productos = $pdo->query('SELECT * FROM productos WHERE activo = 1 ORDER BY id DESC')->fetchAll(); ?>
        <?php if (!$productos): ?>
            <div class="text-center py-5">
                <i class="bi bi-box-seam fs-1 text-primary d-block mb-3"></i>
                <h4>Próximamente</h4>
                <p class="text-secondary">Estamos preparando productos y plantillas digitales. Vuelve pronto.</p>
                <a href="servicios.php" class="btn btn-fv">Ver servicios</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($productos as $p): ?>
                    <div class="col-md-6 col-lg-4 animar">
                        <div class="card-fv p-0 h-100 d-flex flex-column overflow-hidden">
                            <div style="height:170px;background:#e8f0fe;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                                <?php if ($p['imagen'] && file_exists(__DIR__ . '/' . $p['imagen'])): ?>
                                    <img src="<?= e($p['imagen']) ?>" alt="<?= e($p['titulo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <i class="bi bi-box-seam" style="font-size:3rem;color:#7d93b8;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 d-flex flex-column flex-grow-1">
                                <?php if ($p['categoria']): ?><span class="badge text-bg-light text-uppercase small mb-2 align-self-start"><?= e($p['categoria']) ?></span><?php endif; ?>
                                <h5><?= e($p['titulo']) ?></h5>
                                <?php if ($p['descripcion']): ?>
                                    <p class="small text-secondary mb-3"><?= e($p['descripcion']) ?></p>
                                <?php endif; ?>
                                <p class="precio-desde mt-auto mb-3"><b><?= e(formatear_precio((float)$p['precio'])) ?></b></p>
                                <div class="d-grid gap-2">
                                    <?php if ((int)$p['stock'] > 0): ?>
                                        <?= form_agregar_carrito((int)$p['id'], 'producto', $p['titulo']) ?>
                                    <?php else: ?>
                                        <button class="btn btn-outline-fv w-100" disabled><i class="bi bi-x-circle me-1"></i>Sin existencias</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="text-center mt-5">
            <p class="text-secondary">¿Buscas algo a la medida?</p>
            <a href="contacto.php" class="btn btn-fv">Envíanos tu idea <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    </div>
</section>

<?php require_once 'includes/pie.php'; ?>