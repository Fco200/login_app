<?php
$titulo = 'Configuración del sitio';
$subtitulo = 'Datos generales y textos que se muestran en la página pública';
$seccionAdmin = 'config.php';

require_once __DIR__ . '/includes/cabecera.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    foreach ($_POST as $clave => $valor) {
        if (str_starts_with($clave, 'cfg_')) {
            $real = substr($clave, 4);
            $stmt = $pdo->prepare('INSERT INTO datos_sitio (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
            $stmt->execute([$real, is_array($valor) ? implode(', ', $valor) : trim((string)$valor)]);
        }
    }
    flash('Configuración guardada correctamente.');
    header('Location: config.php');
    exit;
}

$datos = datos_sitio();
$campos = [
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
                        $valor = $datos[$clave] ?? $def;
                        ?>
                        <div class="col-md-6 <?= $tipo === 'textarea' ? 'col-12' : '' ?>">
                            <label class="form-label small fw-semibold"><?= e($etiqueta) ?></label>
                            <?php if ($tipo === 'textarea'): ?>
                                <textarea name="cfg_<?= e($clave) ?>" class="form-control" rows="3"><?= e($valor) ?></textarea>
                            <?php else: ?>
                                <input type="<?= $tipo ?>" name="cfg_<?= e($clave) ?>" class="form-control" value="<?= e($valor) ?>">
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

<?php require_once __DIR__ . '/includes/pie.php'; ?>