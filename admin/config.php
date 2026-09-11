<?php
$titulo = 'Configuración';
$subtitulo = 'Configuración general: identidad, contacto, textos, facturación y correo';
$seccionAdmin = 'config.php';

require_once __DIR__ . '/includes/cabecera.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    if (($_POST['accion'] ?? '') === 'probar_correo') {
        $prueba = trim((string)($_POST['correo_prueba'] ?? ''));
        if ($prueba === '' || !filter_var($prueba, FILTER_VALIDATE_EMAIL)) {
            flash('Escribe un correo válido para la prueba.', 'warning');
            header('Location: config.php#correo');
            exit;
        }
        $result = enviar_correo(
            $prueba,
            'Prueba de envío — ' . SITE_NOMBRE,
            correo_plantilla('Correo de prueba', '<p>Si estás leyendo este mensaje, la configuración SMTP es correcta.</p><p><b>Fecha:</b> ' . e(date('d/m/Y H:i')) . '</p>')
        );
        if ($result['ok']) {
            flash('Correo de prueba enviado a ' . e($prueba) . ' correctamente.');
        } else {
            flash('No se pudo enviar: ' . $result['error'], 'danger');
        }
        header('Location: config.php#correo');
        exit;
    }

    foreach ($_POST as $clave => $valor) {
        if (str_starts_with($clave, 'cfg_')) {
            $real = substr($clave, 4);
            $stmt = $pdo->prepare('INSERT INTO datos_sitio (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
            $stmt->execute([$real, is_array($valor) ? implode(', ', $valor) : trim((string)$valor)]);
        }
    }
    flash('Configuración guardada correctamente.');
    header('Location: config.php#correo');
    exit;
}

$datos = datos_sitio();
$campos = [
    'Identidad (marca)' => [
        'nombre'    => ['Nombre de la marca', 'text', SITE_NOMBRE],
        'eslogan'   => ['Eslogan', 'text', SITE_ESLOGAN],
        'logo_ruta' => ['Ruta del logotipo', 'text', '', 'Déjalo vacío para usar el logo de la raíz (logo.png) o assets/img/logo.png.'],
        'footer'    => ['Pie de página', 'text', SITE_FOOTER],
    ],
    'Facturación' => [
        'factura_iva' => ['IVA (%) aplicado a las facturas', 'text', '16', 'Porcentaje de IVA que se desglosa en las facturas (default 16).'],
    ],
    'Datos fiscales (emisor)' => [
        'razon_social' => ['Razón social del emisor', 'text', '', 'Nombre fiscal / razón social que aparece en las facturas y recibos.'],
        'rfc_emisor'   => ['RFC del emisor', 'text', '', 'RFC con el que se emiten las facturas (sin espacios ni guiones).'],
    ],
    'Contacto' => [
        'telefono'  => ['Teléfono', 'text', SITE_TELEFONO],
        'whatsapp'  => ['Número de WhatsApp (solo dígitos)', 'text', SITE_WHATSAPP],
        'email'     => ['Correo electrónico', 'email', SITE_EMAIL],
        'direccion' => ['Dirección', 'text', SITE_DIRECCION],
        'horario'   => ['Horario', 'text', SITE_HORARIO],
    ],
    'Redes sociales' => [
        'facebook'  => ['Facebook (URL)', 'text', ''],
        'instagram' => ['Instagram (URL)', 'text', ''],
        'tiktok'    => ['TikTok (URL)', 'text', ''],
    ],
    'Portada (Hero)' => [
        'hero_titulo'    => ['Título principal', 'text', SITE_HERO_TITULO],
        'hero_subtitulo' => ['Subtítulo', 'textarea', SITE_HERO_SUBTITULO],
        'hero_cta'       => ['Botón principal', 'text', SITE_HERO_CTA],
    ],
    'Acerca de' => [
        'acerca' => ['Texto "Conócenos"', 'textarea', SITE_ACERCA],
    ],
    'Correo (SMTP)' => [
        'smtp_host'     => ['Servidor SMTP', 'text', 'smtp.gmail.com'],
        'smtp_port'     => ['Puerto (465=TLS, 587=STARTTLS, 25)', 'text', '587'],
        'smtp_usuario'  => ['Usuario', 'text', ''],
        'smtp_clave'    => ['Contraseña o clave de aplicación', 'password', ''],
        'smtp_de'       => ['Correo remitente', 'email', SITE_EMAIL],
        'smtp_nombre'   => ['Nombre del remitente', 'text', SITE_NOMBRE],
    ],
];
?>

<form method="POST" action="config.php">
    <?= campo_csrf() ?>
    <?php foreach ($campos as $grupo => $items): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><b><i class="bi bi-sliders me-1"></i><?= e($grupo) ?></b></div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <?php foreach ($items as $clave => $conf): ?>
                        <?php
                        [$etiqueta, $tipo, $def] = $conf;
                        $hint = (string)($conf[3] ?? '');
                        $valor = $datos[$clave] ?? $def;
                        ?>
                        <div class="col-md-6 <?= $tipo === 'textarea' ? 'col-12' : '' ?>">
                            <label class="form-label small fw-semibold"><?= e($etiqueta) ?></label>
                            <?php if ($tipo === 'textarea'): ?>
                                <textarea name="cfg_<?= e($clave) ?>" class="form-control" rows="3"><?= e($valor) ?></textarea>
                            <?php else: ?>
                                <input type="<?= $tipo ?>" name="cfg_<?= e($clave) ?>" class="form-control" value="<?= e($valor) ?>" placeholder="<?= e($hint) ?>">
                            <?php endif; ?>
                            <?php if ($hint !== ''): ?>
                                <div class="small text-muted mt-1"><?= e($hint) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="d-grid col-md-4">
        <button class="btn btn-fv btn-lg"><i class="bi bi-check-lg me-2"></i>Guardar configuración</button>
    </div>
</form>

<div class="card border-0 shadow-sm mb-4" id="correo">
    <div class="card-header bg-white">
        <b><i class="bi bi-envelope-check me-1"></i>Probar envío de correo</b>
        <?php if (smtp_habilitado()): ?>
            <span class="badge text-bg-success ms-2">SMTP configurado</span>
        <?php else: ?>
            <span class="badge text-bg-warning ms-2">SMTP sin configurar</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-3">Guarda la configuración anterior y verifica que tu servidor de correo responde. Se enviará un mensaje de prueba a la dirección que escribas aquí (no del SMTP).</p>
        <form method="POST" action="config.php#correo" class="row g-2 align-items-end">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="probar_correo">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Correo para recibir la prueba</label>
                <input type="email" name="correo_prueba" class="form-control" value="<?= e($datos['smtp_de'] ?? '') ?>" required>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-fv"><i class="bi bi-send me-1"></i>Enviar correo de prueba</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>