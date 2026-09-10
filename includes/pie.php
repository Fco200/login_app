<?php
$datos = datos_sitio();
$telefono = dato_sitio('telefono', SITE_TELEFONO);
$email = dato_sitio('email', SITE_EMAIL);
$direccion = dato_sitio('direccion', SITE_DIRECCION);
$horario = dato_sitio('horario', SITE_HORARIO);
?>

<!-- CTA principal -->
<section class="seccion no-print">
    <div class="container">
        <div class="cta-banda text-center">
            <h2 class="mb-2">¿Listo para dar el siguiente paso?</h2>
            <p class="mb-4 mx-auto" style="max-width:520px;">Cuéntanos tu idea y recibe una propuesta sin compromiso. Atendemos proyectos de todos los tamaños.</p>
            <a href="solicitud.php" class="btn btn-light-fv btn-lg me-2 mb-2"><i class="bi bi-send me-2"></i>Solicitar presupuesto</a>
            <a href="<?= e(whatsapp_enlace('Hola FV Digital, quiero más información sobre sus servicios.')) ?>" target="_blank" class="btn btn-outline-light btn-lg mb-2"><i class="bi bi-whatsapp me-2"></i>WhatsApp</a>
        </div>
    </div>
</section>

<!-- Boletín -->
<section class="seccion no-print">
    <div class="container">
        <?php if (!empty($_SESSION['boletin'])): ?>
            <div class="alert <?= $_SESSION['boletin'] === 'ok' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert" data-alerta="<?= $_SESSION['boletin'] === 'ok' ? 'success' : 'danger' ?>">
                <?= $_SESSION['boletin'] === 'ok'
                    ? '<i class="bi bi-check-circle me-1"></i>¡Gracias por suscribirte! Te avisaremos de novedades y promociones.'
                    : '<i class="bi bi-exclamation-triangle me-1"></i>El correo ingresado no es válido.' ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
            <?php unset($_SESSION['boletin']); ?>
        <?php endif; ?>
        <div class="cta-banda d-lg-flex justify-content-between align-items-center text-center text-lg-start">
            <div class="mb-3 mb-lg-0">
                <h2 class="mb-1">Únete a nuestra lista de novedades</h2>
                <p class="mb-0">Recibe tips digitales, promociones y nuevas plantillas directamente en tu correo.</p>
            </div>
            <form method="POST" action="boletin.php" class="js-ajax d-flex flex-column flex-sm-row gap-2 mx-auto mx-lg-0" style="max-width:440px;">
                <input type="email" name="email" class="form-control" placeholder="tucorreo@ejemplo.com" required aria-label="Correo">
                <input type="hidden" name="retorno" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
                <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                <button class="btn btn-light-fv flex-shrink-0" type="submit">Suscribirme</button>
            </form>
        </div>
    </div>
</section>

<footer class="no-print">
    <div class="container">
        <div class="row g-4 pb-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="assets/img/logo.png" alt="<?= e(SITE_NOMBRE) ?>" style="height:52px;width:auto;object-fit:contain;">
                    <span class="logo-txt"><b class="text-white"><?= e(SITE_NOMBRE) ?></b><small><?= e(SITE_ESLOGAN) ?></small></span>
                </div>
                <p><?= e(dato_sitio('acerca', SITE_ACERCA)) ?></p>
                <div class="footer-redes">
                    <?php $fb = dato_sitio('facebook'); $ig = dato_sitio('instagram'); $tk = dato_sitio('tiktok'); ?>
                    <?php if ($fb): ?><a href="<?= e($fb) ?>" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a><?php endif; ?>
                    <?php if ($ig): ?><a href="<?= e($ig) ?>" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a><?php endif; ?>
                    <?php if ($tk): ?><a href="<?= e($tk) ?>" target="_blank" title="TikTok"><i class="bi bi-tiktok"></i></a><?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Enlaces</h6>
                <ul class="list-unstyled">
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="servicios.php">Servicios</a></li>
                    <li><a href="proyectos.php">Proyectos</a></li>
                    <li><a href="solicitud.php">Cotizar</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Información</h6>
                <ul class="list-unstyled">
                    <li><a href="publicaciones.php">Publicaciones</a></li>
                    <li><a href="cartas.php">Cartas</a></li>
                    <li><a href="contacto.php">Contacto</a></li>
                    <li><a href="admin/login.php">Panel</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6>Contacto</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-telephone me-2 text-info"></i><a href="tel:+52<?= e(preg_replace('/\D/', '', $telefono)) ?>"><?= e($telefono) ?></a></li>
                    <li class="mb-2"><i class="bi bi-envelope me-2 text-info"></i><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                    <li class="mb-2"><i class="bi bi-geo-alt me-2 text-info"></i><?= e($direccion) ?></li>
                    <li class="mb-2"><i class="bi bi-clock me-2 text-info"></i><?= e($horario) ?></li>
                </ul>
            </div>
        </div>
        <div class="footer-bajo py-3 d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; <?= date('Y') ?> <?= e(SITE_NOMBRE) ?> — <?= e(SITE_ESLOGAN) ?>. Todos los derechos reservados.</span>
            <span>Desarrollado con <i class="bi bi-heart-fill text-danger"></i> por <?= e(SITE_NOMBRE) ?></span>
        </div>
    </div>
</footer>

<!-- Botón flotante de WhatsApp -->
<a href="<?= e(whatsapp_enlace()) ?>" target="_blank" class="whatsapp-float" title="Escríbenos por WhatsApp" aria-label="WhatsApp">
    <i class="bi bi-whatsapp"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/avisos.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/portal.js"></script>
</body>
</html>