<?php
require_once __DIR__ . '/../funciones.php';
requiere_sesion();

$seccionPortal = 'soporte';
$titulo = 'Soporte técnico';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'] ?? '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        responder(['ok' => false, 'mensaje' => 'La sesión expiró, intenta de nuevo.', 'tipo' => 'danger']);
    }

    $categorias = ['problema', 'duda', 'sugerencia', 'otro'];
    $categoria = in_array($_POST['categoria'] ?? '', $categorias, true) ? $_POST['categoria'] : 'problema';
    $pagina = mb_substr(trim($_POST['pagina'] ?? ''), 0, 255);
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (mb_strlen($descripcion) < 10) {
        responder(['ok' => false, 'mensaje' => 'Describe el problema o tu duda con al menos 10 caracteres.', 'tipo' => 'danger']);
    }

    $pdo->prepare('INSERT INTO soporte (usuario_id, nombre, email, categoria, pagina, descripcion) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([(int)$usuario['id'], $usuario['nombre'], $usuario['email'], $categoria, $pagina !== '' ? $pagina : null, mb_substr($descripcion, 0, 3000)]);

    responder([
        'ok'      => true,
        'titulo'  => '¡Reporte enviado!',
        'mensaje' => 'Tu reporte de soporte llegó al equipo. Revisamos y te avisamos aquí cuando haya respuesta.',
        'destino' => 'soporte',
    ]);
}

$stmt = $pdo->prepare('SELECT * FROM soporte WHERE usuario_id = ? OR LOWER(email) = LOWER(?) ORDER BY creado_en DESC LIMIT 50');
$stmt->execute([(int)$usuario['id'], $usuario['email']]);
$reportes = $stmt->fetchAll();

$estadoR = fn(string $est) => match ($est) {
    'nuevo'     => ['badge text-bg-danger', 'bi-exclamation-circle', 'Nuevo'],
    'atendido'  => ['badge text-bg-success', 'bi-check-circle', 'Atendido'],
    'cerrado'   => ['badge text-bg-secondary', 'bi-check2-circle', 'Cerrado'],
    default     => ['badge text-bg-light', 'bi-question-circle', $est],
};
$catR = fn(string $cat) => match ($cat) {
    'problema'   => ['Problema / error', 'bi-bug', 'text-danger'],
    'duda'       => ['Duda', 'bi-question-circle', 'text-primary'],
    'sugerencia' => ['Sugerencia', 'bi-lightbulb', 'text-warning'],
    default      => ['Otro', 'bi-chat', 'text-muted'],
};

require_once __DIR__ . '/includes/cabecera.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">Soporte técnico</h4>
        <p class="text-muted mb-0">¿Encontraste un error, una falla en la página o tienes una sugerencia? Cuéntanos y revisamos.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card portal-card border-0 shadow-sm">
            <div class="card-header bg-white"><b><i class="bi bi-headset me-1 text-primary"></i>Reportar algo</b></div>
            <div class="card-body p-4">
                <small class="text-muted d-block mb-3">Los reportes son privados y solo los ve el equipo de FV Digital.</small>
                <form method="POST" action="soporte.php" class="js-ajax" novalidate>
                    <?= campo_csrf() ?>
                    <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tipo de reporte *</label>
                        <select name="categoria" class="form-select" required>
                            <option value="problema">Problema / error en la página</option>
                            <option value="duda">Duda</option>
                            <option value="sugerencia">Sugerencia / mejora</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">¿En qué página ocurre? (opcional)</label>
                        <input type="text" name="pagina" class="form-control" placeholder="Ej. Mis solicitudes, pago, registro…" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Describe qué pasó *</label>
                        <textarea name="descripcion" class="form-control" rows="5" required minlength="10" maxlength="3000"
                            placeholder="Ej. Al guardar mi perfil aparece un error y no se guardan los cambios."></textarea>
                    </div>
                    <button type="submit" class="btn btn-fv w-100"><i class="bi bi-send me-1"></i>Enviar reporte</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card portal-card border-0 shadow-sm">
            <div class="card-header bg-white"><b><i class="bi bi-clock-history me-1 text-success"></i>Mis reportes</b></div>
            <div class="card-body p-3">
                <?php if (!$reportes): ?>
                    <p class="text-muted small text-center mb-0 py-4">Aún no has enviado reportes de soporte.</p>
                <?php else: ?>
                    <ul class="list-unstyled portal-notif mb-0">
                        <?php foreach ($reportes as $r): [$bg, $ic, $txt] = $estadoR($r['estado']); [$cTxt, $cIc, $cClr] = $catR($r['categoria']); ?>
                            <li class="card portal-card border-0 shadow-sm mb-2 no-leida">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 w-100">
                                    <div>
                                        <b class="d-block small"><?= e($cTxt) ?> <i class="bi <?= $cIc ?> <?= $cClr ?> ms-1"></i> <span class="badge text-bg-light align-middle">#<?= (int)$r['id'] ?></span></b>
                                        <?php if ($r['pagina']): ?><span class="small text-muted d-block"><i class="bi bi-globe2 me-1"></i><?= e($r['pagina']) ?></span><?php endif; ?>
                                        <p class="small text-muted mb-1" style="white-space:pre-line;"><?= e($r['descripcion']) ?></p>
                                        <?php if ($r['respuesta']): ?>
                                            <div class="alert alert-fv-light small py-2 mb-1"><i class="bi bi-reply me-1"></i><b>Respuesta del equipo:</b> <?= e($r['respuesta']) ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?= e(date('d/m/Y H:i', strtotime($r['creado_en']))) ?></small>
                                    </div>
                                    <span class="<?= $bg ?>"><i class="bi <?= $ic ?> me-1"></i><?= $txt ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>