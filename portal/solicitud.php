<?php
require_once __DIR__ . '/../funciones.php';
requiere_sesion();

$seccionPortal = 'solicitud';
$titulo = 'Nueva solicitud';

$usuario = sesion_actual() ?? ['id' => (string)($_SESSION['usuario_id'] ?? ''), 'nombre' => '', 'email' => ''];
$servicios = srv_activos();
$servicioSel = trim($_GET['servicio'] ?? '');
$errores = [];

/* ---------- Reutilizar una solicitud anterior ---------- */
$pre = null;
$reutilizar = trim((string)($_GET['reutilizar'] ?? ''));
if (oid($reutilizar) !== null) {
    $pre = sol_de_usuario($reutilizar, $usuario['id'], (string)$usuario['email']);
    if ($pre && ($pre['tipo_solicitud'] ?? '') === 'empresa') $pre = null;
}
$presupPre = '';
if ($pre && $servicioSel === '') {
    foreach ($servicios as $s) {
        if ($s['titulo'] === $pre['tipo_servicio']) { $servicioSel = $s['slug']; break; }
    }
    if ($servicioSel === '') $servicioSel = $pre['tipo_servicio'] ?? '';
}
if ($pre && is_numeric($pre['presupuesto'])) {
    $monto = (int)floor((float)$pre['presupuesto']);
    foreach ([2000, 5000, 10000, 20000, 50000] as $val) {
        if ($monto === $val) { $presupPre = (string)$val; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        responder(['ok' => false, 'mensaje' => 'La sesión expiró, intenta de nuevo.', 'tipo' => 'danger']);
    }

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

    $srv = srv_por_slug($servicio);
    $servicioNombre = (string)($srv['titulo'] ?? $servicio);

    if ($nombre === '' || !$email || $telefono === '' || $servicio === '' || $descripcion === '') {
        responder(['ok' => false, 'mensaje' => 'Todos los campos son obligatorios. Verifica que el correo sea válido.', 'tipo' => 'danger']);
    }

    $nueva = sol_crear([
        'usuario_id'    => $usuario['id'],
        'nombre'        => $nombre,
        'email'         => (string)$email,
        'telefono'      => $telefono,
        'tipo_servicio' => $servicioNombre,
        'presupuesto'   => $presupuesto ?: null,
        'mensaje'       => $descripcion,
        'tipo_solicitud'=> 'individual',
    ]);

    if (empty($nueva['ok'])) {
        responder(['ok' => false, 'mensaje' => $nueva['mensaje'] ?? 'No se pudo registrar la solicitud.', 'tipo' => 'danger']);
    }

    notificar($usuario['id'], 'exito', '¡Solicitud recibida!', "Registramos tu solicitud de $servicioNombre y empezamos a revisarla.", url_sitio('portal/solicitudes.php'));

    responder([
        'ok'      => true,
        'titulo'  => '¡Solicitud enviada!',
        'mensaje' => 'Tu solicitud fue registrada. Te avisaremos de cada avance en tus notificaciones.',
        'destino' => 'solicitudes.php',
    ]);
}

require_once __DIR__ . '/includes/cabecera.php';
?>

<div class="mb-4">
    <h4 class="mb-1">Nueva solicitud</h4>
    <p class="text-muted mb-0">Cuéntanos qué necesitas y te enviaremos una propuesta sin compromiso.</p>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card portal-card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div class="alert alert-fv-light small py-2 mb-4">
                    <i class="bi bi-person-check me-1"></i>La envías como <b><?= e($usuario['nombre']) ?></b>. Podrás dar seguimiento en tu portal.
                </div>

                <?php if ($pre): ?>
            <div class="alert alert-fv-light small py-2 mb-4 d-flex justify-content-between align-items-center gap-3">
                <span><i class="bi bi-arrow-repeat me-1"></i>Copiamos los datos de tu solicitud <b><?= e($pre['tipo_servicio']) ?></b> del <?= e(fecha_php($pre['creado_en'] ?? '', 'd/m/Y')) ?>. Ajusta lo que quieras y vuelve a enviarla.</span>
                <a href="nueva-solicitud" class="btn btn-sm btn-outline-fv flex-shrink-0"><i class="bi bi-x-lg"></i></a>
            </div>
        <?php endif; ?>

        <form method="POST" action="solicitud.php" class="js-ajax" novalidate id="formSolicitud">
            <?= campo_csrf() ?>
            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre completo *</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Tu nombre" value="<?= e($usuario['nombre']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo electrónico *</label>
                    <input type="email" name="email" class="form-control" required value="<?= e($usuario['email']) ?>" readonly>
                    <div class="form-text">Correo de tu cuenta (no modificable).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono / WhatsApp *</label>
                    <input type="tel" name="telefono" class="form-control" required placeholder="662 123 4567" value="<?= e(trim($_POST['telefono'] ?? ($pre['telefono'] ?? $usuario['telefono'] ?? ''))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Servicio que te interesa *</label>
                    <select name="servicio" class="form-select" required>
                        <option value="">-- Selecciona un servicio --</option>
                        <?php foreach ($servicios as $s): ?>
                            <option value="<?= e($s['slug']) ?>" <?= $servicioSel === $s['slug'] ? 'selected' : '' ?>><?= e($s['titulo']) ?></option>
                        <?php endforeach; ?>
                        <option value="Otro / proyecto especial" <?= $servicioSel === 'Otro / proyecto especial' ? 'selected' : '' ?>>Otro / proyecto especial</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">¿Cuál es tu presupuesto aproximado?</label>
                    <select name="presupuesto" class="form-select">
                        <option value="">Sin definir</option>
                        <option value="2000" <?= $presupPre === '2000' ? 'selected' : '' ?>>Hasta $2,000</option>
                        <option value="5000" <?= $presupPre === '5000' ? 'selected' : '' ?>>$2,000 – $5,000</option>
                        <option value="10000" <?= $presupPre === '10000' ? 'selected' : '' ?>>$5,000 – $10,000</option>
                        <option value="20000" <?= $presupPre === '20000' ? 'selected' : '' ?>>$10,000 – $20,000</option>
                        <option value="50000" <?= $presupPre === '50000' ? 'selected' : '' ?>>Más de $20,000</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Cuéntanos tu proyecto *</label>
                    <textarea name="descripcion" class="form-control" rows="5" required placeholder="Describe tu idea, objetivos, referencias, plazos…"><?= e((string)($pre['mensaje'] ?? '')) ?></textarea>
                </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-fv btn-lg w-100"><i class="bi bi-send me-2"></i>Enviar solicitud</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>