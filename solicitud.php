<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'solicitud';
$titulo = 'Solicitar una cotización — ' . SITE_NOMBRE;

$servicios = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY destaque DESC, id ASC")->fetchAll();
$servicioSel = trim($_GET['servicio'] ?? '');
$usuarioSesion = sesion_actual();
$enviada = false;

/* Iniciar sesión es obligatorio para solicitar una cotización: así el cliente
   puede darle seguimiento a su solicitud en el portal. */
$retorno = 'solicitud.php' . ($servicioSel !== '' ? '?servicio=' . rawurlencode($servicioSel) : '');
$requiereSesion = !$usuarioSesion;

if ($requiereSesion && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (es_ajax()) {
        responder([
            'ok'      => false,
            'tipo'    => 'warning',
            'titulo'  => 'Inicia sesión primero',
            'mensaje' => 'Debes iniciar sesión para enviar una solicitud y poder darle seguimiento.',
            'destino' => url_sitio('iniciar-sesion.php?retorno=' . rawurlencode($retorno)),
        ]);
    }
    redirigir('iniciar-sesion.php?retorno=' . rawurlencode($retorno));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf() && empty($_POST['empresa'])) {
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $telefono = trim($_POST['telefono']);
    $servicio = trim($_POST['servicio'] ?? '');

    $presupuesto = '';
    if (!empty($_POST['presupuesto'])) {
        $monto = (float)str_replace([',', '$'], '', $_POST['presupuesto']);
        $presupuesto = $monto > 0 ? number_format($monto, 2, '.', '') : '';
    }

    $descripcion = trim($_POST['descripcion']);

    $stmt = $pdo->prepare("SELECT titulo FROM servicios WHERE slug = ? LIMIT 1");
    $stmt->execute([$servicio]);
    $servicioNombre = $stmt->fetchColumn() ?: $servicio;

    if ($nombre === '' || !$email || $telefono === '' || $servicio === '' || $descripcion === '') {
        if (es_ajax()) {
            responder(['ok' => false, 'mensaje' => 'Todos los campos son obligatorios. Verifica que el correo sea válido.', 'tipo' => 'danger']);
        }
        flash('Todos los campos son obligatorios. Verifica que el correo sea válido.', 'danger');
    } else {
        $usuarioId = $usuarioSesion ? (int)$usuarioSesion['id'] : null;
        $pdo->prepare("INSERT INTO solicitudes (usuario_id, nombre, email, telefono, tipo_servicio, presupuesto, mensaje) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$usuarioId, $nombre, $email, $telefono, $servicioNombre, $presupuesto ?: null, $descripcion]);

        if ($usuarioSesion) {
            $solicitudId = (int)$pdo->lastInsertId();
            registrar_historial($solicitudId, 'nueva', 'Solicitud registrada.');
            notificar((int)$usuarioSesion['id'], 'exito', '¡Solicitud recibida!',
                "Registramos tu solicitud de $servicioNombre y empezamos a revisarla.", url_sitio('portal/solicitudes.php'));
            responder([
                'ok'      => true,
                'titulo'  => '¡Solicitud enviada!',
                'mensaje' => 'Tu solicitud fue registrada. Podrás darle seguimiento en tu portal de clientes.',
                'destino' => 'portal/solicitudes.php',
            ]);
        }

        if (es_ajax()) {
            responder([
                'ok'      => true,
                'titulo'  => '¡Solicitud enviada!',
                'mensaje' => "Gracias $nombre, tu solicitud fue registrada. Te responderemos a la brevedad por correo o WhatsApp.",
            ]);
        }

        $enviada = true;
        $datosForm = compact('nombre', 'email', 'telefono', 'servicio', 'descripcion');
    }
}

require_once 'includes/cabecera.php';
?>

<?php if ($enviada): ?>
    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="card-fv p-5 text-center">
                        <div class="icono-caja destacado mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;"><i class="bi bi-check-lg"></i></div>
                        <h3>¡Solicitud recibida!</h3>
                        <p class="text-secondary mb-4">Gracias <?= e($datosForm['nombre']) ?>, tu solicitud fue registrada. Te responderemos a la brevedad por correo o WhatsApp.</p>
                        <div class="d-flex justify-content-center flex-wrap gap-2">
                            <a href="index.php" class="btn btn-fv"><i class="bi bi-house me-1"></i>Ir al inicio</a>
                            <a href="<?= e(whatsapp_enlace('Hola FV Digital, acabo de enviar una solicitud y quiero acelerar mi cotización.')) ?>" target="_blank" class="btn btn-outline-fv"><i class="bi bi-whatsapp me-1"></i>Hablar por WhatsApp</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php elseif ($requiereSesion): ?>
    <section class="hero-mini">
        <div class="container">
            <h1 class="mb-2">Solicita una cotización</h1>
            <p class="mb-0">Para enviarla y darle seguimiento paso a paso, necesitas una cuenta.</p>
        </div>
    </section>

    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="card-fv p-5 text-center">
                        <div class="icono-caja destacado mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;"><i class="bi bi-person-lock"></i></div>
                        <h3>Inicia sesión para cotizar</h3>
                        <p class="text-secondary mb-4">Crear tu cuenta es gratis y en segundos. Así podrás <b>guardar y dar seguimiento</b> a cada solicitud: verás su estado, historial y notificaciones en tu portal de clientes.</p>
                        <div class="d-flex justify-content-center flex-wrap gap-2 mb-3">
                            <a href="iniciar-sesion.php?retorno=<?= e(rawurlencode($retorno)) ?>" class="btn btn-fv"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
                            <a href="registro.php?retorno=<?= e(rawurlencode($retorno)) ?>" class="btn btn-outline-fv"><i class="bi bi-person-plus me-1"></i>Crear cuenta gratis</a>
                        </div>
                        <small class="text-muted">¿Solo quieres hablarnos rápido? Usa el <a href="contacto.php" class="fw-semibold">formulario de contacto</a>.</small>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="hero-mini">
        <div class="container">
            <h1 class="mb-2">Solicita una cotización</h1>
            <p class="mb-0">Cuéntanos qué necesitas y te enviaremos una propuesta sin compromiso.</p>
        </div>
    </section>

    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card-fv p-4 p-md-5">
                        <div class="alert alert-fv-light small py-2 mb-3">
                            <i class="bi bi-buildings me-1"></i><b>¿Ocupas algo para tu empresa?</b> Usa el <a href="solicitud-empresa.php" class="fw-semibold">formulario de solicitud empresarial</a>.
                        </div>
                        <?= mostrar_flash() ?>

                        <form method="POST" action="solicitud.php" class="js-ajax" novalidate><?= campo_csrf() ?>
                            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <?php if ($usuarioSesion): ?>
                                <div class="alert alert-fv-light small py-2 mb-3">
                                    <i class="bi bi-person-check me-1"></i>Envías como <b><?= e($usuarioSesion['nombre']) ?></b>. Podrás dar seguimiento en tu <a href="portal/index.php" class="fw-semibold">portal de clientes</a>.
                                </div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre completo *</label>
                                    <input type="text" name="nombre" class="form-control" required placeholder="Tu nombre" value="<?= e(trim($_POST['nombre'] ?? ($usuarioSesion['nombre'] ?? ''))) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Correo electrónico *</label>
                                    <input type="email" name="email" class="form-control" required placeholder="tucorreo@ejemplo.com" <?= $usuarioSesion ? 'readonly' : '' ?> value="<?= e(trim($_POST['email'] ?? ($usuarioSesion['email'] ?? ''))) ?>">
                                    <?php if ($usuarioSesion): ?>
                                        <div class="form-text">Correo de tu cuenta (no modificable).</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono / WhatsApp *</label>
                                    <input type="tel" name="telefono" class="form-control" required placeholder="662 123 4567" value="<?= e(trim($_POST['telefono'] ?? ($usuarioSesion['telefono'] ?? ''))) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Servicio que te interesa *</label>
                                    <select name="servicio" class="form-select" required>
                                        <option value="">-- Selecciona un servicio --</option>
                                        <?php foreach ($servicios as $s): ?>
                                            <?php $enfoque = $servicioSel === $s['slug'] ? ' selected' : ''; ?>
                                            <option value="<?= e($s['slug']) ?>"<?= $enfoque ?>><?= e($s['titulo']) ?></option>
                                        <?php endforeach; ?>
                                        <option value="Otro / proyecto especial">Otro / proyecto especial</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">¿Cuál es tu presupuesto aproximado?</label>
                                    <select name="presupuesto" class="form-select">
                                        <option value="">Sin definir</option>
                                        <option value="2000">Hasta $2,000</option>
                                        <option value="5000">$2,000 – $5,000</option>
                                        <option value="10000">$5,000 – $10,000</option>
                                        <option value="20000">$10,000 – $20,000</option>
                                        <option value="50000">Más de $20,000</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Cuéntanos tu proyecto *</label>
                                    <textarea name="descripcion" class="form-control" rows="5" required placeholder="Describe tu idea, objetivos, referencias, plazos…"></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-fv btn-lg w-100"><i class="bi bi-send me-2"></i>Enviar solicitud</button>
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