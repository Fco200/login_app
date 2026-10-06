<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'contacto';
$titulo = 'Contacto — ' . SITE_NOMBRE;

$telefono = dato_sitio('telefono', SITE_TELEFONO);
$email = dato_sitio('email', SITE_EMAIL);
$direccion = dato_sitio('direccion', SITE_DIRECCION);
$horario = dato_sitio('horario', SITE_HORARIO);
$facebook = dato_sitio('facebook', '');
$instagram = dato_sitio('instagram', '');
$tiktok = dato_sitio('tiktok', '');

$enviado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf() && empty($_POST['empresa'])) {
    $nombre = trim($_POST['nombre']);
    $email2 = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $asunto = trim($_POST['asunto']);
    $mensaje = trim($_POST['mensaje']);

    if ($nombre === '' || !$email2 || $asunto === '' || $mensaje === '') {
        if (es_ajax()) {
            responder(['ok' => false, 'mensaje' => 'Todos los campos son obligatorios.', 'tipo' => 'danger']);
        }
        flash('Todos los campos son obligatorios.', 'danger');
    } else {
        $pdo->prepare('INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES (?, ?, ?, ?)')
            ->execute([$nombre, $email2, $asunto, $mensaje]);
        if (es_ajax()) {
            responder([
                'ok'      => true,
                'titulo'  => '¡Mensaje enviado!',
                'mensaje' => 'Gracias por escribirnos. Te responderemos lo antes posible.',
            ]);
        }
        $enviado = true;
    }
}

require_once 'includes/cabecera.php';

$redes = [
    ['facebook', 'bi-facebook', 'Facebook'],
    ['instagram', 'bi-instagram', 'Instagram'],
    ['tiktok', 'bi-tiktok', 'TikTok'],
];
?>

<?php if ($enviado): ?>
    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="card-fv p-5 text-center">
                        <div class="icono-caja destacado mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;"><i class="bi bi-check-lg"></i></div>
                        <h3>¡Mensaje enviado!</h3>
                        <p class="text-secondary mb-4">Gracias por escribirnos. Te responderemos lo antes posible.</p>
                        <a href="index.php" class="btn btn-fv"><i class="bi bi-house me-1"></i>Ir al inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="hero-mini">
        <div class="container">
            <h1 class="mb-2">Contáctanos</h1>
            <p class="mb-0">Estamos listos para escuchar tu proyecto.</p>
        </div>
    </section>

    <section class="seccion">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card-fv p-4 h-100">
                        <h5>Información de contacto</h5>
                        <ul class="lista-contacto mt-3">
                            <li><i class="bi bi-telephone fill"></i><div><b>Teléfono</b><span><?= e($telefono) ?></span></div></li>
                            <li><i class="bi bi-whatsapp"></i><div><b>WhatsApp</b><span><a href="<?= e(whatsapp_enlace('Hola FV Digital, vengo de su sitio web.')) ?>" target="_blank">Escribir por WhatsApp</a></span></div></li>
                            <li><i class="bi bi-envelope fill"></i><div><b>Correo</b><span><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></span></div></li>
                            <?php if ($direccion): ?>
                                <li><i class="bi bi-geo-alt fill"></i><div><b>Dirección</b><span><?= e($direccion) ?></span></div></li>
                            <?php endif; ?>
                            <?php if ($horario): ?>
                                <li><i class="bi bi-clock fill"></i><div><b>Horario</b><span><?= e($horario) ?></span></div></li>
                            <?php endif; ?>
                        </ul>

                        <h6 class="mt-4 mb-2">Síguenos</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach ($redes as $red): ?>
                                <?php if (${$red[0]}): ?>
                                    <a href="<?= e(${$red[0]}) ?>" target="_blank" class="icono-caja" style="text-decoration:none;" aria-label="<?= e($red[2]) ?>"><i class="bi <?= $red[1] ?>"></i></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card-fv p-4 h-100">
                        <?= mostrar_flash() ?>

                        <form method="POST" action="contacto.php" class="js-ajax" novalidate><?= campo_csrf() ?>
                            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" name="nombre" class="form-control" required placeholder="Tu nombre">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Correo *</label>
                                    <input type="email" name="email" class="form-control" required placeholder="tucorreo@ejemplo.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Asunto *</label>
                                    <input type="text" name="asunto" class="form-control" required placeholder="¿Sobre qué nos escribes?">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Mensaje *</label>
                                    <textarea name="mensaje" class="form-control" rows="5" required placeholder="Tu mensaje…"></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-fv w-100"><i class="bi bi-envelope-send me-2"></i>Enviar mensaje</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require_once 'includes/pie.php'; ?>