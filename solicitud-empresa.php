<?php
require_once __DIR__ . '/funciones.php';
$seccion = 'solicitud';
$titulo = 'Solicitud para empresas — ' . SITE_NOMBRE;

$servicios = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY destaque DESC, id ASC")->fetchAll();
$servicioSel = trim($_GET['servicio'] ?? '');
$usuarioSesion = sesion_actual();
$enviada = false;

/* Iniciar sesión es obligatorio para solicitar una cotización: así el cliente
   puede darle seguimiento a su solicitud en el portal. */
$retorno = 'solicitud-empresa.php' . ($servicioSel !== '' ? '?servicio=' . rawurlencode($servicioSel) : '');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf() && empty($_POST['web'])) {
    $empresa = trim($_POST['empresa']);
    $cargo = trim($_POST['cargo']);
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $telefono = trim($_POST['telefono']);
    $servicio = trim($_POST['servicio'] ?? '');
    $empleados = trim($_POST['empleados'] ?? '');
    $rango = trim($_POST['rango'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    $stmt = $pdo->prepare("SELECT titulo FROM servicios WHERE slug = ? LIMIT 1");
    $stmt->execute([$servicio]);
    $servicioNombre = $stmt->fetchColumn() ?: $servicio;

    if ($empresa === '' || $nombre === '' || !$email || $telefono === '' || $servicio === '' || $rango === '' || $descripcion === '') {
        if (es_ajax()) {
            responder(['ok' => false, 'mensaje' => 'Completa los campos obligatorios: empresa, tu nombre, correo válido, teléfono, servicio, rango de inversión y la descripción de lo que necesitas.', 'tipo' => 'danger']);
        }
        flash('Completa los campos obligatorios: empresa, tu nombre, correo válido, teléfono, servicio, rango de inversión y la descripción de lo que necesitas.', 'danger');
    } else {
        $presupuesto = $rango;
        $usuarioId = $usuarioSesion ? (int)$usuarioSesion['id'] : null;
        $detalle = $descripcion . ($empleados !== '' ? "\n\nTamaño de la empresa: " . $empleados : '');
        $pdo->prepare("INSERT INTO solicitudes (usuario_id, nombre, email, empresa, cargo, telefono, tipo_servicio, presupuesto, mensaje, tipo_solicitud, empleados, rango) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'empresa', ?, ?)")
            ->execute([$usuarioId, $nombre, $email, $empresa, $cargo ?: null, $telefono, $servicioNombre, $presupuesto ?: null, $detalle, $empleados !== '' ? $empleados : null, $rango !== '' ? $rango : null]);

        if ($usuarioSesion) {
            $solicitudId = (int)$pdo->lastInsertId();
            registrar_historial($solicitudId, 'nueva', 'Solicitud empresarial registrada.');
            notificar((int)$usuarioSesion['id'], 'exito', '¡Solicitud empresarial recibida!',
                "Recibimos la solicitud de $empresa por $servicioNombre. Te responderemos a la brevedad.",
                url_sitio('portal/solicitudes.php'));
            responder([
                'ok'      => true,
                'titulo'  => '¡Solicitud empresarial enviada!',
                'mensaje' => "Gracias $nombre. El equipo de FV Digital revisará la propuesta para $empresa y te contactará en máximo 48 h hábiles.",
                'destino' => 'portal/solicitudes.php',
            ]);
        }

        if (es_ajax()) {
            responder([
                'ok'      => true,
                'titulo'  => '¡Solicitud empresarial enviada!',
                'mensaje' => "Gracias $nombre. El equipo de FV Digital revisará la propuesta para $empresa y te contactará en máximo 48 h hábiles.",
            ]);
        }

        $enviada = true;
        $datosForm = compact('empresa', 'cargo', 'nombre', 'email', 'telefono', 'servicio', 'empleados', 'rango');
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
                        <div class="icono-caja destacado mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;"><i class="bi bi-buildings"></i></div>
                        <h3>¡Solicitud empresarial recibida!</h3>
                        <p class="text-secondary mb-4">Gracias <?= e($datosForm['nombre']) ?>, el equipo de FV Digital revisará la propuesta para <b><?= e($datosForm['empresa']) ?></b> y te contactará en máximo 48 h hábiles.</p>
                        <div class="d-flex justify-content-center flex-wrap gap-2">
                            <a href="index.php" class="btn btn-fv"><i class="bi bi-house me-1"></i>Ir al inicio</a>
                            <a href="<?= e(whatsapp_enlace('Hola FV Digital, soy de ' . ($datosForm['empresa'] ?? '') . ' y quiero agilizar mi solicitud empresarial.')) ?>" target="_blank" class="btn btn-outline-fv"><i class="bi bi-whatsapp me-1"></i>Hablar por WhatsApp</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php elseif ($requiereSesion): ?>
    <section class="hero-mini">
        <div class="container">
            <h1 class="mb-2">¿Necesitas algo para tu empresa?</h1>
            <p class="mb-0">Para enviar tu solicitud y darle seguimiento paso a paso, necesitas una cuenta.</p>
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
            <div class="row justify-content-center mt-3">
                <div class="col-lg-10">
                    <div class="alert alert-fv-light small py-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i><b>¿Tu proyecto es personal?</b> Usa el <a href="solicitud.php" class="fw-semibold">formulario individual</a>. Tu cuenta sirve para ambos.
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="hero-mini">
        <div class="container">
            <h1 class="mb-2">¿Necesitas algo para tu empresa?</h1>
            <p class="mb-0">Sitio web, tienda en línea, branding, automatización o soporte: arma tu solicitud empresarial y te enviamos una propuesta a la medida.</p>
        </div>
    </section>

    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="alert alert-fv-light small py-2 mb-3">
                        <i class="bi bi-lightning-charge me-1"></i><b>Proceso empresarial:</b> revisamos tu solicitud y respondemos en máximo <b>48 h hábiles</b> con una propuesta y cotización. <a href="solicitud.php" class="fw-semibold">¿Eres persona con un proyecto personal?</a> usa el formulario individual.
                    </div>
                    <div class="card-fv p-4 p-md-5">
                        <?= mostrar_flash() ?>

                        <form method="POST" action="solicitud-empresa.php" class="js-ajax" novalidate><?= campo_csrf() ?>
                            <input type="text" name="web" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <?php if ($usuarioSesion): ?>
                                <div class="alert alert-fv-light small py-2 mb-3">
                                    <i class="bi bi-person-check me-1"></i>Envías como <b><?= e($usuarioSesion['nombre']) ?></b>. Podrás dar seguimiento en tu <a href="portal/index.php" class="fw-semibold">portal de clientes</a>.
                                </div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre de la empresa *</label>
                                    <input type="text" name="empresa" class="form-control" required placeholder="Nombre de tu empresa" value="<?= e(trim($_POST['empresa'] ?? '')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tu cargo / puesto</label>
                                    <input type="text" name="cargo" class="form-control" placeholder="Ej. Director, Gerente, Encargado…" value="<?= e(trim($_POST['cargo'] ?? '')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tu nombre completo *</label>
                                    <input type="text" name="nombre" class="form-control" required placeholder="Tu nombre" value="<?= e(trim($_POST['nombre'] ?? ($usuarioSesion['nombre'] ?? ''))) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Correo corporativo *</label>
                                    <input type="email" name="email" class="form-control" required placeholder="contacto@tuempresa.com" <?= $usuarioSesion ? 'readonly' : '' ?> value="<?= e(trim($_POST['email'] ?? ($usuarioSesion['email'] ?? ''))) ?>">
                                    <?php if ($usuarioSesion): ?>
                                        <div class="form-text">Correo de tu cuenta (no modificable).</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono / WhatsApp *</label>
                                    <input type="tel" name="telefono" class="form-control" required placeholder="662 123 4567" value="<?= e(trim($_POST['telefono'] ?? ($usuarioSesion['telefono'] ?? ''))) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tamaño de tu empresa</label>
                                    <select name="empleados" class="form-select">
                                        <option value="">-- Selecciona --</option>
                                        <?php foreach (['1 a 5 personas', '6 a 20 personas', '21 a 50 personas', 'Más de 50 personas'] as $opc): ?>
                                            <option <?= ($_POST['empleados'] ?? '') === $opc ? 'selected' : '' ?>><?= $opc ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Servicio que necesitas *</label>
                                    <select name="servicio" class="form-select" required>
                                        <option value="">-- Selecciona un servicio --</option>
                                        <?php foreach ($servicios as $s): ?>
                                            <?php $enfoque = $servicioSel === $s['slug'] ? ' selected' : ''; ?>
                                            <option value="<?= e($s['slug']) ?>"<?= $enfoque ?>><?= e($s['titulo']) ?></option>
                                        <?php endforeach; ?>
                                        <option value="Proyecto empresarial especial">Proyecto empresarial especial</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rango de inversión aprox. *</label>
                                    <select name="rango" class="form-select" required>
                                        <option value="">-- Selecciona --</option>
                                        <?php foreach (['$5,000 – $15,000', '$15,000 – $40,000', '$40,000 – $100,000', 'Más de $100,000', 'A convenir / cotizar'] as $opc): ?>
                                            <option <?= ($_POST['rango'] ?? '') === $opc ? 'selected' : '' ?>><?= $opc ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Cuéntanos qué necesitas para tu empresa *</label>
                                    <textarea name="descripcion" class="form-control" rows="5" required placeholder="Describe objetivos, plazos estimados, número de empleados que usarán el sistema, referencias…"></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-fv btn-lg w-100"><i class="bi bi-buildings me-2"></i>Enviar solicitud empresarial</button>
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