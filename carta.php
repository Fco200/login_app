<?php
require_once __DIR__ . '/funciones.php';
$slug = trim($_GET['slug'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM cartas WHERE slug = ? AND activo = 1 LIMIT 1');
$stmt->execute([$slug]);
$carta = $stmt->fetch();

$titulo = ($carta ? $carta['titulo'] : 'Carta no encontrada') . ' — ' . SITE_NOMBRE;

require_once 'includes/cabecera.php';

$datos = datos_sitio();
$telefono = dato_sitio('telefono', SITE_TELEFONO);
$email = dato_sitio('email', SITE_EMAIL);
$direccion = dato_sitio('direccion', SITE_DIRECCION);
$horario = dato_sitio('horario', SITE_HORARIO);
?>

<?php if ($carta): ?>
    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="carta-borde no-print mb-3">
                        <a href="cartas.php" class="btn-enlace"><i class="bi bi-arrow-left me-1"></i>Volver a cartas</a>
                        <button class="btn btn-sm btn-outline-fv ms-2" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir esta carta</button>
                    </div>

                    <div class="carta-documento p-4 p-md-5" id="documento">
                        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                            <img src="assets/img/logo.png" alt="" class="rounded" style="max-height:70px;width:auto;">
                            <div>
                                <div class="texto-azul fw-bold" style="letter-spacing:3px;font-size:.8rem;"><?= e(SITE_NOMBRE) ?></div>
                                <div class="small text-secondary"><?= e(SITE_ESLOGAN) ?></div>
                            </div>
                        </div>
                        <h1 class="h3 mb-3"><?= e($carta['titulo']) ?></h1>
                        <?php if ($carta['destinatario']): ?>
                            <p class="mb-4"><b>Para:</b> <?= e($carta['destinatario']) ?></p>
                        <?php endif; ?>
                        <p class="small text-secondary mb-4">
                            <?= e(date('d de F de Y', strtotime($carta['creado_en']))) ?>
                        </p>
                        <div class="contenido-nice">
                            <?= $carta['contenido'] ?>
                        </div>
                        <?php if ($carta['firmado_por']): ?>
                            <div class="mt-5 pt-3 text-secondary small">
                                <div class="mb-4">Atentamente,</div>
                                <div><b class="texto-azul"><?= e($carta['firmado_por']) ?></b></div>
                            </div>
                        <?php endif; ?>
                        <div class="mt-5 pt-4 border-top text-secondary small">
                            <div><b class="texto-azul"><?= e(SITE_NOMBRE) ?></b></div>
                            <div><?= e($telefono) ?> &nbsp;•&nbsp; <?= e($email) ?></div>
                            <?php if ($direccion): ?><div><?= e($direccion) ?></div><?php endif; ?>
                            <?php if ($horario): ?><div>Horario: <?= e($horario) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="seccion">
        <div class="container">
            <div class="text-center py-5">
                <i class="bi bi-file-earmark-x fs-1 texto-azul d-block mb-3"></i>
                <h4>Carta no encontrada</h4>
                <a href="cartas.php" class="btn btn-fv">Ver todas las cartas</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require_once 'includes/pie.php'; ?>