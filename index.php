<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'inicio';
$titulo = SITE_NOMBRE . ' — ' . SITE_ESLOGAN;

$servicios = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY destaque DESC, id ASC LIMIT 4")->fetchAll();
$proyectos = $pdo->query("SELECT * FROM proyectos WHERE activo = 1 ORDER BY destaque DESC, creado_en DESC LIMIT 3")->fetchAll();
$testimonios = $pdo->query("SELECT * FROM testimonios WHERE activo = 1 ORDER BY id DESC LIMIT 4")->fetchAll();
$publicaciones = $pdo->query("SELECT * FROM publicaciones WHERE activo = 1 ORDER BY creado_en DESC LIMIT 3")->fetchAll();
$totalProyectos = (int)$pdo->query("SELECT COUNT(*) FROM proyectos WHERE activo = 1")->fetchColumn();

require_once 'includes/cabecera.php';
?>

<!-- HERO -->
<section class="hero py-5">
    <div class="container py-lg-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge-hero mb-3"><i class="bi bi-stars"></i> Soluciones digitales con propósito</span>
                <h1 class="mb-3"><?= e(dato_sitio('hero_titulo', SITE_HERO_TITULO)) ?></h1>
                <p class="lead mb-4"><?= e(dato_sitio('hero_subtitulo', SITE_HERO_SUBTITULO)) ?></p>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="solicitud.php" class="btn btn-light-fv btn-lg"><i class="bi bi-send me-2"></i><?= e(dato_sitio('hero_cta', SITE_HERO_CTA)) ?></a>
                    <a href="proyectos.php" class="btn btn-outline-light btn-lg">Ver proyectos</a>
                    <?php if (esta_logueado()): ?>
                        <a href="clientes" class="btn btn-outline-light btn-lg"><i class="bi bi-person-circle me-1"></i>Mi portal</a>
                    <?php else: ?>
                        <a href="crear-cuenta" class="btn btn-outline-light btn-lg"><i class="bi bi-person-plus me-1"></i>Crear cuenta gratis</a>
                    <?php endif; ?>
                </div>
                <div class="hero-stats row g-3 mt-2">
                    <div class="col-4"><div class="stat p-3 text-center"><b>+<?= (int)$totalProyectos ?></b><small>Proyectos</small></div></div>
                    <div class="col-4"><div class="stat p-3 text-center"><b>+60</b><small>Clientes felices</small></div></div>
                    <div class="col-4"><div class="stat p-3 text-center"><b>100%</b><small>Hecho a tu medida</small></div></div>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img src="assets/img/logo.png" alt="<?= e(SITE_NOMBRE) ?> — <?= e(SITE_ESLOGAN) ?>" class="hero-img" style="max-height:420px;width:auto;">
            </div>
        </div>
    </div>
</section>

<!-- BANNER EMPRESAS -->
<section class="seccion no-print">
    <div class="container">
        <div class="cta-banda d-lg-flex justify-content-between align-items-center gap-4 text-center text-lg-start" style="background:linear-gradient(120deg,#0a3d8f,#16b8f3);border-radius:20px;">
            <div class="d-flex align-items-center gap-3">
                <span class="icono-caja destacado d-none d-sm-inline-flex" style="width:58px;height:58px;font-size:1.4rem;"><i class="bi bi-buildings"></i></span>
                <div>
                    <h2 class="mb-1">¿Ocupas algo para tu empresa?</h2>
                    <p class="mb-0">Sitio web, tienda en línea, branding o automatización para tu negocio. Respuesta en máximo 48 h hábiles.</p>
                </div>
            </div>
            <a href="solicitud-empresa.php" class="btn btn-light-fv btn-lg flex-shrink-0"><i class="bi bi-buildings me-2"></i>Haz clic aquí</a>
        </div>
    </div>
</section>

<!-- SERVICIOS -->
<section class="seccion">
    <div class="container">
        <div class="text-center mb-5">
            <span class="etiqueta">Nuestros servicios</span>
            <h2 class="titulo-seccion">¿Qué hacemos por tu marca?</h2>
            <p class="subtitulo-seccion mx-auto">Soluciones integrales para digitalizar y hacer crecer tu negocio.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($servicios as $s): ?>
                <div class="col-md-6 col-lg-3 animar">
                    <div class="card-fv p-4">
                        <div class="icono-caja <?= $s['destaque'] ? 'destacado' : '' ?> mb-3"><i class="bi <?= e($s['icono']) ?>"></i></div>
                        <h5><?= e($s['titulo']) ?></h5>
                        <p class="small text-secondary mb-3"><?= e($s['descripcion_corta']) ?></p>
                        <p class="precio-desde mb-3">Desde <b><?= e(formatear_precio((float)$s['precio_desde'])) ?></b></p>
                        <a href="solicitud.php?servicio=<?= e($s['slug']) ?>" class="btn-enlace">Solicitar <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="servicios.php" class="btn btn-outline-fv">Ver todos los servicios <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    </div>
</section>

<!-- POR QUÉ NOSOTROS -->
<section class="seccion seccion-alt">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="etiqueta">¿Por qué FV Digital?</span>
                <h2 class="titulo-seccion mb-3">Tecnología, diseño y estrategia en un solo lugar</h2>
                <p class="subtitulo-seccion">No solo construimos páginas: ayudamos a tu negocio a vender, comunicar y crecer con herramientas digitales profesionales.</p>
                <ul class="lista-checks mt-4">
                    <li><i class="bi bi-check-circle-fill"></i><b>Atención personalizada</b> en cada etapa del proyecto.</li>
                    <li><i class="bi bi-check-circle-fill"></i><b>Diseños modernos</b> y adaptados a todo dispositivo.</li>
                    <li><i class="bi bi-check-circle-fill"></i><b>Entregas ágiles</b> con calidad garantizada.</li>
                    <li><i class="bi bi-check-circle-fill"></i><b>Soporte después</b> del lanzamiento.</li>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-6"><div class="card-fv p-4 text-center"><i class="bi bi-lightning-charge-fill texto-azul fs-1"></i><h6 class="mt-2 mb-0">Rápido</h6></div></div>
                    <div class="col-6"><div class="card-fv p-4 text-center"><i class="bi bi-palette-fill texto-azul fs-1"></i><h6 class="mt-2 mb-0">Creativo</h6></div></div>
                    <div class="col-6"><div class="card-fv p-4 text-center"><i class="bi bi-shield-check texto-azul fs-1"></i><h6 class="mt-2 mb-0">Confiables</h6></div></div>
                    <div class="col-6"><div class="card-fv p-4 text-center"><i class="bi bi-headset texto-azul fs-1"></i><h6 class="mt-2 mb-0">Soporte</h6></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PROYECTOS DESTACADOS -->
<?php if ($proyectos): ?>
<section class="seccion">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-3">
            <div>
                <span class="etiqueta">Portafolio</span>
                <h2 class="titulo-seccion">Proyectos destacados</h2>
            </div>
            <a href="proyectos.php" class="btn btn-outline-fv">Ver todos <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-4">
            <?php foreach ($proyectos as $p): ?>
                <div class="col-md-4 animar">
                    <div class="card-fv card-proyecto">
                        <div class="img-wrap">
                            <?php if ($p['imagen']): ?>
                                <img src="<?= e($p['imagen']) ?>" alt="<?= e($p['titulo']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="placeholder-img"><i class="bi bi-briefcase"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <span class="badge-cat mb-2"><?= e($p['categoria']) ?></span>
                            <h5 class="mt-2"><?= e($p['titulo']) ?></h5>
                            <p class="small text-secondary mb-3"><?= e(mb_strimwidth($p['descripcion'] ?? '', 0, 95, '…')) ?></p>
                            <a href="proyectos.php#proyecto<?= (int)$p['id'] ?>" class="btn-enlace">Ver proyecto <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- TESTIMONIOS -->
<?php if ($testimonios): ?>
<section class="seccion seccion-alt">
    <div class="container">
        <div class="text-center mb-5">
            <span class="etiqueta">Testimonios</span>
            <h2 class="titulo-seccion">Lo que dicen nuestros clientes</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($testimonios as $t): ?>
                <div class="col-md-6 col-lg-3 animar">
                    <div class="card-fv p-4 card-testimonio">
                        <div class="mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star-fill <?= $i <= (int)$t['valoracion'] ? '' : 'text-secondary' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="small mb-3 text-secondary">"<?= e($t['mensaje']) ?>"</p>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-circulo"><?= e(strtoupper(mb_substr($t['nombre'], 0, 1))) ?></span>
                            <div>
                                <b class="small d-block"><?= e($t['nombre']) ?></b>
                                <small class="text-muted"><?= e($t['cargo']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CÓMO TRABAJAMOS -->
<section class="seccion">
    <div class="container">
        <div class="text-center mb-5">
            <span class="etiqueta">Proceso</span>
            <h2 class="titulo-seccion">Así trabajamos contigo</h2>
        </div>
        <div class="row g-4">
            <?php
            $pasos = [
                ['bi-chat-dots', '1. Cuéntanos tu idea', 'Escríbenos por el formulario o WhatsApp y cuéntanos qué necesitas.'],
                ['bi-clipboard-check', '2. Propuesta a tu medida', 'Te enviamos una cotización clara con alcance, tiempos y precio.'],
                ['bi-tools', '3. Desarrollo', 'Diseñamos y desarrollamos tu proyecto con comunicación constante.'],
                ['bi-rocket-takeoff', '4. Entrega y soporte', 'Publicamos tu proyecto y te acompañamos con soporte continuo.'],
            ];
            foreach ($pasos as $paso): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card-fv p-4 text-center h-100">
                        <div class="icono-caja destacado mx-auto mb-3"><i class="bi <?= $paso[0] ?>"></i></div>
                        <h6><?= $paso[1] ?></h6>
                        <p class="small text-secondary mb-0"><?= $paso[2] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ÁREA DE CLIENTES -->
<section class="seccion">
    <div class="container">
        <div class="text-center mb-5">
            <span class="etiqueta">Área de clientes</span>
            <h2 class="titulo-seccion">Todo tu proyecto en un solo lugar</h2>
            <p class="subtitulo-seccion mx-auto">Crea tu cuenta y lleva el control de tus cotizaciones, con descargas y novedades exclusivas.</p>
        </div>
        <div class="row g-4">
            <?php
            $beneficios = [
                ['bi-inbox-fill', 'Seguimiento de solicitudes', 'Consulta en tiempo real el estado de tus cotizaciones y proyectos.'],
                ['bi-download', 'Plantillas y descargas', 'Acceso a plantillas y documentos que hayas adquirido o solicitado.'],
                ['bi-bell-fill', 'Novedades para ti', 'Recibe promociones, tips digitales y avisos importantes en tu correo.'],
                ['bi-person-check-fill', 'Datos siempre actualizados', 'Administra tu información y contraseña de forma sencilla y segura.'],
            ];
            foreach ($beneficios as $b): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card-fv p-4 text-center h-100">
                        <div class="icono-caja destacado mx-auto mb-3"><i class="bi <?= $b[0] ?>"></i></div>
                        <h6><?= $b[1] ?></h6>
                        <p class="small text-secondary mb-0"><?= $b[2] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <?php if (esta_logueado()): ?>
                <a href="clientes" class="btn btn-fv btn-lg"><i class="bi bi-person-circle me-2"></i>Ir a mi portal</a>
            <?php else: ?>
                <a href="crear-cuenta" class="btn btn-fv btn-lg"><i class="bi bi-person-plus me-2"></i>Crear mi cuenta gratis</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- PUBLICACIONES RECIENTES -->
<?php if ($publicaciones): ?>
<section class="seccion seccion-alt">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-3">
            <div>
                <span class="etiqueta">Blog</span>
                <h2 class="titulo-seccion">Publicaciones recientes</h2>
            </div>
            <a href="publicaciones.php" class="btn btn-outline-fv">Ver todas <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-4">
            <?php foreach ($publicaciones as $pub): ?>
                <div class="col-md-4">
                    <div class="card-fv card-articulo">
                        <div class="img-wrap">
                            <?php if ($pub['imagen']): ?>
                                <img src="<?= e($pub['imagen']) ?>" alt="<?= e($pub['titulo']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="placeholder-img"><i class="bi bi-newspaper"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <small class="text-muted text-uppercase" style="letter-spacing:1px;font-size:.72rem;"><i class="bi bi-calendar3 me-1"></i><?= e(date('d M Y', strtotime($pub['creado_en']))) ?></small>
                            <h5 class="mt-2"><a href="publicacion.php?slug=<?= e($pub['slug']) ?>" style="color:inherit;"><?= e($pub['titulo']) ?></a></h5>
                            <p class="small text-secondary mb-0"><?= e($pub['resumen']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once 'includes/pie.php'; ?>