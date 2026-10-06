<?php
require_once __DIR__ . '/../funciones.php';
requiere_sesion();

$seccionPortal = 'solicitud-empresa';
$titulo = 'Solicitud para empresas';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'nombre' => '', 'email' => ''];
$servicios = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY destaque DESC, id ASC")->fetchAll();
$servicioSel = trim($_GET['servicio'] ?? '');
$errores = [];

/* ---------- Reutilizar una solicitud empresarial anterior ---------- */
$pre = null;
$reutilizar = (int)($_GET['reutilizar'] ?? 0);
if ($reutilizar > 0) {
    $rs = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ? AND (usuario_id = ? OR LOWER(email) = LOWER(?))");
    $rs->execute([$reutilizar, (int)$usuario['id'], $usuario['email']]);
    $pre = $rs->fetch() ?: null;
    if ($pre && ($pre['tipo_solicitud'] ?? '') !== 'empresa') $pre = null;
}
if ($pre && $servicioSel === '') {
    foreach ($servicios as $s) {
        if ($s['titulo'] === $pre['tipo_servicio']) { $servicioSel = $s['slug']; break; }
    }
    if ($servicioSel === '') $servicioSel = $pre['tipo_servicio'] ?? '';
}
$descPre = (string)($pre['mensaje'] ?? '');
if (($pre['empleados'] ?? '') === '' && str_contains($descPre, "\n\nTamaño de la empresa: ")) {
    $descPre = preg_replace('/\n\nTamaño de la empresa: .*$/s', '', $descPre);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['web'])) {
        responder(['ok' => false, 'mensaje' => 'La sesión expiró, intenta de nuevo.', 'tipo' => 'danger']);
    }

    $empresa = trim($_POST['empresa']);
    $cargo = trim($_POST['cargo']);
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $telefono = trim($_POST['telefono']);
    $servicio = trim($_POST['servicio'] ?? '');
    $empleados = trim($_POST['empleados'] ?? '');
    $rango = trim($_POST['rango'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    $presupuesto = '';
    if ($rango !== '') {
        $monto = (float)str_replace([',', '$'], '', $rango);
        $presupuesto = $monto > 0 ? number_format($monto, 2, '.', '') : $rango;
    }

    $stmt = $pdo->prepare('SELECT titulo FROM servicios WHERE slug = ? LIMIT 1');
    $stmt->execute([$servicio]);
    $servicioNombre = $stmt->fetchColumn() ?: $servicio;

    if ($empresa === '' || $nombre === '' || !$email || $telefono === '' || $servicio === '' || $rango === '' || $descripcion === '') {
        responder(['ok' => false, 'mensaje' => 'Completa los campos obligatorios: empresa, tu nombre, correo válido, teléfono, servicio, rango de inversión y la descripción de lo que necesitas.', 'tipo' => 'danger']);
    }

    $detalle = $descripcion . ($empleados !== '' ? "\n\nTamaño de la empresa: " . $empleados : '');
    $pdo->prepare("INSERT INTO solicitudes (usuario_id, nombre, email, empresa, cargo, telefono, tipo_servicio, presupuesto, mensaje, tipo_solicitud, empleados, rango) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'empresa', ?, ?)")
        ->execute([(int)$usuario['id'], $nombre, $email, $empresa, $cargo ?: null, $telefono, $servicioNombre, $presupuesto ?: null, $detalle, $empleados !== '' ? $empleados : null, $rango !== '' ? $rango : null]);

    $solicitudId = (int)$pdo->lastInsertId();
    registrar_historial($solicitudId, 'nueva', 'Solicitud empresarial registrada.');
    notificar((int)$usuario['id'], 'exito', '¡Solicitud empresarial recibida!',
        "Recibimos la solicitud de $empresa por $servicioNombre. Te responderemos en máximo 48 h hábiles.", url_sitio('portal/solicitudes.php'));
    notificar_admins('estado', 'Nueva solicitud empresarial', "$nombre de $empresa solicitó una cotización de $servicioNombre.", url_sitio('admin/solicitudes.php'));

    responder([
        'ok'      => true,
        'titulo'  => '¡Solicitud empresarial enviada!',
        'mensaje' => "Gracias $nombre. El equipo de FV Digital revisará la propuesta para $empresa y te notificará cada avance.",
        'destino' => 'solicitudes.php',
    ]);
}

require_once __DIR__ . '/includes/cabecera.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="mb-1">Solicitud para empresas</h4>
        <p class="text-muted mb-0">Proceso empresarial con respuesta en máximo 48 h hábiles.</p>
    </div>
    <a href="nueva-solicitud" class="btn btn-sm btn-outline-fv"><i class="bi bi-person me-1"></i>Solicitud personal</a>
</div>

<div class="card portal-card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
        <div class="alert alert-fv-light small py-2 mb-4">
            <i class="bi bi-buildings me-1"></i>La envías desde la cuenta de <b><?= e($usuario['nombre']) ?></b>. Aparecerá en "Mis solicitudes" con la etiqueta <b>Empresa</b>.
        </div>

        <?php if ($pre): ?>
            <div class="alert alert-fv-light small py-2 mb-4 d-flex justify-content-between align-items-center gap-3">
                <span><i class="bi bi-arrow-repeat me-1"></i>Copiamos los datos de tu solicitud <b><?= e($pre['tipo_servicio']) ?></b> del <?= e(date('d/m/Y', strtotime($pre['creado_en']))) ?>. Revisa y vuelve a enviarla.</span>
                <a href="nueva-solicitud-empresa" class="btn btn-sm btn-outline-fv flex-shrink-0"><i class="bi bi-x-lg"></i></a>
            </div>
        <?php endif; ?>

        <form method="POST" action="solicitud-empresa.php" class="js-ajax" novalidate id="formSolicitudEmpresa">
            <?= campo_csrf() ?>
            <input type="text" name="web" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre de la empresa *</label>
                    <input type="text" name="empresa" class="form-control" required placeholder="Nombre de tu empresa" value="<?= e(trim($_POST['empresa'] ?? ($pre['empresa'] ?? ''))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tu cargo / puesto</label>
                    <input type="text" name="cargo" class="form-control" placeholder="Ej. Director, Gerente…" value="<?= e(trim($_POST['cargo'] ?? ($pre['cargo'] ?? ''))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tu nombre completo *</label>
                    <input type="text" name="nombre" class="form-control" required value="<?= e($usuario['nombre']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo *</label>
                    <input type="email" name="email" class="form-control" required value="<?= e($usuario['email']) ?>" readonly>
                    <div class="form-text">Correo de tu cuenta (no modificable).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono / WhatsApp *</label>
                    <input type="tel" name="telefono" class="form-control" required placeholder="662 123 4567" value="<?= e(trim($_POST['telefono'] ?? ($pre['telefono'] ?? $usuario['telefono'] ?? ''))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tamaño de tu empresa</label>
                    <select name="empleados" class="form-select">
                        <option value="">-- Selecciona --</option>
                        <?php foreach (['1 a 5 personas', '6 a 20 personas', '21 a 50 personas', 'Más de 50 personas'] as $opc): $empPre = trim($_POST['empleados'] ?? ($pre['empleados'] ?? '')); ?>
                            <option <?= $empPre === $opc ? 'selected' : '' ?>><?= $opc ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Servicio que necesitas *</label>
                    <select name="servicio" class="form-select" required>
                        <option value="">-- Selecciona un servicio --</option>
                        <?php foreach ($servicios as $s): ?>
                            <option value="<?= e($s['slug']) ?>" <?= $servicioSel === $s['slug'] ? 'selected' : '' ?>><?= e($s['titulo']) ?></option>
                        <?php endforeach; ?>
                        <option value="Proyecto empresarial especial">Proyecto empresarial especial</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rango de inversión aprox. *</label>
                    <select name="rango" class="form-select" required>
                        <option value="">-- Selecciona --</option>
                        <?php $rangoPre = trim($_POST['rango'] ?? ($pre['rango'] ?? ($pre['presupuesto'] ?? ''))); foreach (['$5,000 – $15,000', '$15,000 – $40,000', '$40,000 – $100,000', 'Más de $100,000', 'A convenir / cotizar'] as $opc): ?>
                            <option <?= $rangoPre === $opc ? 'selected' : '' ?>><?= $opc ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Cuéntanos qué necesitas para tu empresa *</label>
                    <textarea name="descripcion" class="form-control" rows="5" required placeholder="Objetivos, plazos, número de empleados que usarán el sistema, referencias…"><?= e($descPre) ?></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-fv btn-lg w-100"><i class="bi bi-buildings me-2"></i>Enviar solicitud empresarial</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>