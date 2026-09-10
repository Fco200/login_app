<?php
require_once __DIR__ . '/../funciones.php';
requiere_sesion();

$seccionPortal = 'juego';
$titulo = 'Mini-juegos';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id']];

/* ---------- Guardar puntaje ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        responder(['ok' => false, 'mensaje' => 'La sesión expiró, intenta de nuevo.', 'tipo' => 'danger']);
    }
    $juego = in_array($_POST['juego'] ?? '', ['memorama', 'serpiente'], true) ? $_POST['juego'] : 'memorama';
    $puntaje = max(0, (int)($_POST['puntaje'] ?? 0));

    $stmt = $pdo->prepare('SELECT puntaje FROM juego_puntajes WHERE usuario_id = ? AND juego = ? ORDER BY puntaje DESC LIMIT 1');
    $stmt->execute([(int)$usuario['id'], $juego]);
    $record = (int)$stmt->fetchColumn();

    if ($puntaje > 0 && $puntaje > $record) {
        $pdo->prepare('INSERT INTO juego_puntajes (usuario_id, juego, puntaje) VALUES (?, ?, ?)')
            ->execute([(int)$usuario['id'], $juego, $puntaje]);
        $record = $puntaje;
    }

    responder([
        'ok'      => true,
        'titulo'  => $record === $puntaje && $puntaje > 0 ? '¡Nuevo récord!' : '¡Puntaje guardado!',
        'mensaje' => 'Jugamos mientras esperas, ¿por qué no una partida más?',
        'destino' => null,
    ]);
}

$stmt = $pdo->prepare('SELECT juego, MAX(puntaje) AS mejor FROM juego_puntajes WHERE usuario_id = ? GROUP BY juego');
$stmt->execute([(int)$usuario['id']]);
$records = [];
foreach ($stmt as $r) {
    $records[$r['juego']] = (int)$r['mejor'];
}

/* ---------- Tabla de mejores puntajes (todos los clientes) ---------- */
$mejores = [];
foreach (['memorama', 'serpiente'] as $juego) {
    $stmtT = $pdo->prepare('SELECT jp.puntaje, u.nombre, jp.creado_en
                            FROM juego_puntajes jp JOIN usuarios u ON u.id = jp.usuario_id
                            WHERE jp.juego = ? AND jp.puntaje > 0
                            ORDER BY jp.puntaje DESC, jp.creado_en ASC LIMIT 5');
    $stmtT->execute([$juego]);
    $mejores[$juego] = $stmtT->fetchAll();
}

$puestoIconos = ['🥇', '🥈', '🥉'];

require_once __DIR__ . '/includes/cabecera.php';
?>

<div class="mb-4">
    <h4 class="mb-1">Juega mientras esperamos</h4>
    <p class="text-muted mb-0">Tu solicitud sigue en proceso… mientras tanto, diviértete un rato. ¡Cuidado con el récord!</p>
</div>

<div class="d-none"><?= campo_csrf() ?></div>

<ul class="nav nav-pills nav-pills-fv mb-4 gap-2 flex-wrap">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#jMemorama"><i class="bi bi-grid-3x3-gap me-1"></i>Memorama</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#jSerpiente"><i class="bi bi-arrow-repeat me-1"></i>La serpiente</a></li>
</ul>

<div class="tab-content">
    <!-- ============ MEMORAMA ============ -->
    <div class="tab-pane fade show active" id="jMemorama">
        <div class="row justify-content-center g-4">
            <div class="col-lg-7">
                <div class="card portal-card border-0 shadow-sm">
                    <div class="card-body p-4 text-center">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-fv"><i class="bi bi-stopwatch me-1"></i><span id="memTiempo">0s</span></span>
                            <span class="badge bg-fv"><i class="bi bi-hand-index-thumb me-1"></i><span id="memMovimientos">0</span> movimientos</span>
                            <span class="badge bg-fv"><i class="bi bi-trophy me-1"></i>Récord: <span id="memRecord"><?= $records['memorama'] ?? 0 ?></span></span>
                        </div>

                        <div id="memGrid" class="mem-grid"></div>

                        <div class="mt-3">
                            <button class="btn btn-fv" type="button" onclick="FV.jugos.memory.nuevo()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reiniciar partida</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card portal-card border-0 shadow-sm">
                    <div class="card-header bg-white"><b><i class="bi bi-info-circle me-1 text-primary"></i>Cómo jugar</b></div>
                    <div class="card-body">
                        <p class="small text-muted mb-0">Encuentra todas las parejas de emojis en el menor número de movimientos. Mientras más rápido termines, más puntos ganas. Tu mejor puntaje se guarda en tu portal.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SERPIENTE ============ -->
    <div class="tab-pane fade" id="jSerpiente">
        <div class="row justify-content-center g-4">
            <div class="col-lg-7">
                <div class="card portal-card border-0 shadow-sm">
                    <div class="card-body p-4 text-center">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-fv"><i class="bi bi-apple me-1"></i><span id="snkPuntos">0</span> puntos</span>
                            <span class="badge bg-fv"><i class="bi bi-trophy me-1"></i>Récord: <span id="snkRecord"><?= $records['serpiente'] ?? 0 ?></span></span>
                        </div>

                        <canvas id="snakeCanvas" class="border rounded" width="400" height="400"
                                style="max-width:100%;height:auto;background:#0a1e39;border-color:#0a3d8f!important;"></canvas>

                        <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
                            <button class="btn btn-fv" type="button" onclick="FV.jugos.snake.iniciar()"><i class="bi bi-play me-1"></i>Jugar / Reiniciar</button>
                            <button class="btn btn-outline-fv" type="button" onclick="FV.jugos.snake.pausar()"><i class="bi bi-pause me-1"></i>Pausa</button>
                        </div>
                        <small class="d-block text-muted mt-2"><i class="bi bi-keyboard me-1"></i>Teclas de flecha o <b>W A S D</b>. En móvil desliza.</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card portal-card border-0 shadow-sm">
                    <div class="card-header bg-white"><b><i class="bi bi-info-circle me-1 text-primary"></i>Cómo jugar</b></div>
                    <div class="card-body">
                        <p class="small text-muted mb-0">Mueve la serpiente para comer las frutas y crece cada vez más. No choques con las paredes ni contigo mismo. Cada fruta = 10 puntos.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mejores puntajes -->
<div class="mt-5">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="etiqueta mb-0">Tabla de líderes</span>
        <h5 class="mb-0">Mejores puntajes entre clientes</h5>
    </div>
    <div class="row g-4">
        <?php foreach (['memorama' => 'Memorama', 'serpiente' => 'La serpiente'] as $claveJuego => $nombreJuego): ?>
            <div class="col-md-6">
                <div class="card portal-card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex align-items-center gap-2">
                        <i class="bi <?= $claveJuego === 'memorama' ? 'bi-grid-3x3-gap' : 'bi-arrow-repeat' ?> text-primary"></i>
                        <b><?= e($nombreJuego) ?></b>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($mejores[$claveJuego])): ?>
                            <p class="text-muted small text-center mb-0 py-3">Aún no hay puntajes. ¡Sé la primera persona en aparecer aquí!</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($mejores[$claveJuego] as $i => $t): ?>
                                    <li class="d-flex align-items-center gap-2 py-2 <?= $i > 0 ? 'border-top' : '' ?>" style="border-color:var(--borde)!important;">
                                        <span class="fs-5"><?= $puestoIconos[$i] ?? $i + 1 ?></span>
                                        <div class="flex-grow-1">
                                            <b class="d-block small"><?= e($t['nombre']) ?></b>
                                            <small class="text-muted"><?= e(date('d/m/Y', strtotime($t['creado_en']))) ?></small>
                                        </div>
                                        <span class="badge bg-fv"><?= (int)$t['puntaje'] ?> pts</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>