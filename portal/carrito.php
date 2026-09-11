<?php
require_once __DIR__ . '/includes/cabecera.php';

$seccionPortal = 'carrito';
$titulo = 'Mi carrito';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'email' => ''];
$usuarioId = (int)$usuario['id'];

/* ---------- Acciones POST (agregar / actualizar / eliminar / pagar) ---------- */
function resumen_carrito(int $usuarioId): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT c.*, sv.titulo AS servicio_titulo, pr.titulo AS producto_titulo FROM carrito c
                           LEFT JOIN servicios sv ON c.servicio_id = sv.id
                           LEFT JOIN productos pr ON c.producto_id = pr.id
                           WHERE c.usuario_id = ?');
    $stmt->execute([$usuarioId]);
    $items = $stmt->fetchAll();
    $subtotal = 0.0;
    $unidades = 0;
    foreach ($items as $it) {
        $subtotal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
        $unidades += (int)$it['cantidad'];
    }
    return [
        'items'    => count($items),
        'unidades' => $unidades,
        'subtotal' => $subtotal,
        'count'    => $unidades,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        responder(['ok' => false, 'mensaje' => 'Token de seguridad inválido. Recarga la página.', 'tipo' => 'danger']);
    }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $servicioId = (int)($_POST['servicio_id'] ?? 0);
        $productoId = (int)($_POST['producto_id'] ?? 0);
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
        $precio = 0.0;
        $tipoItem = '';
        $tituloItem = '';

        if ($productoId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ? AND activo = 1');
            $stmt->execute([$productoId]);
            $p = $stmt->fetch();
            if (!$p) {
                responder(['ok' => false, 'mensaje' => 'El producto ya no está disponible.', 'tipo' => 'warning']);
            }
            $precio = (float)$p['precio'];
            $tipoItem = 'producto';
            $tituloItem = $p['titulo'];
        } elseif ($servicioId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM servicios WHERE id = ? AND activo = 1');
            $stmt->execute([$servicioId]);
            $s = $stmt->fetch();
            if (!$s) {
                responder(['ok' => false, 'mensaje' => 'El servicio ya no está disponible.', 'tipo' => 'warning']);
            }
            $precio = (float)($s['precio_desde'] ?? 0);
            $tipoItem = 'servicio';
            $tituloItem = $s['titulo'];
            $productoId = null;
        } else {
            responder(['ok' => false, 'mensaje' => 'No se especificó qué agregar.', 'tipo' => 'warning']);
        }

        if ($precio <= 0) {
            responder(['ok' => false, 'mensaje' => 'Este ítem no tiene precio configurado.', 'tipo' => 'warning']);
        }

        /* Si el ítem ya está en el carrito, sumamos la cantidad */
        if ($tipoItem === 'producto') {
            $stmtChk = $pdo->prepare('SELECT id, cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ?');
            $stmtChk->execute([$usuarioId, $productoId]);
        } else {
            $stmtChk = $pdo->prepare('SELECT id, cantidad FROM carrito WHERE usuario_id = ? AND servicio_id = ?');
            $stmtChk->execute([$usuarioId, $servicioId]);
        }
        $existente = $stmtChk->fetch();
        if ($existente) {
            $pdo->prepare('UPDATE carrito SET cantidad = cantidad + ?, precio_unitario = ? WHERE id = ?')
                ->execute([$cantidad, $precio, (int)$existente['id']]);
        } else {
            $pdo->prepare('INSERT INTO carrito (usuario_id, servicio_id, producto_id, cantidad, precio_unitario) VALUES (?,?,?,?,?)')
                ->execute([$usuarioId, $servicioId, $productoId, $cantidad, $precio]);
        }
        $resumen = resumen_carrito($usuarioId);
        responder([
            'ok' => true,
            'mensaje' => "$tituloItem se agregó a tu carrito.",
            'tipo' => 'success',
            'cart_count' => $resumen['count'],
        ]);
    }

    if ($accion === 'actualizar') {
        $idItem = (int)($_POST['id'] ?? 0);
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
        $stmt = $pdo->prepare('SELECT c.* FROM carrito c WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$idItem, $usuarioId]);
        $item = $stmt->fetch();
        if (!$item) {
            responder(['ok' => false, 'mensaje' => 'Ese ítem ya no está en tu carrito.', 'tipo' => 'warning']);
        }
        $pdo->prepare('UPDATE carrito SET cantidad = ? WHERE id = ? AND usuario_id = ?')
            ->execute([$cantidad, $idItem, $usuarioId]);

        $resumen = resumen_carrito($usuarioId);
        responder([
            'ok' => true,
            'mensaje' => 'Carrito actualizado.',
            'tipo' => 'success',
            'cart_count' => $resumen['count'],
            'cart_items' => $resumen['items'],
            'cart_units' => $resumen['unidades'],
            'cart_subtotal' => '$' . number_format($resumen['subtotal'], 0) . ' MXN',
            'cart_total' => '$' . number_format($resumen['subtotal'], 0) . ' MXN',
            'item_id' => $idItem,
            'item_subtotal' => '$' . number_format((float)$item['precio_unitario'] * $cantidad, 0),
        ]);
    }

    if ($accion === 'eliminar') {
        $idItem = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM carrito WHERE id = ? AND usuario_id = ?')->execute([$idItem, $usuarioId]);

        $resumen = resumen_carrito($usuarioId);
        responder([
            'ok' => true,
            'mensaje' => 'Ítem eliminado del carrito.',
            'tipo' => 'success',
            'cart_count' => $resumen['count'],
            'cart_items' => $resumen['items'],
            'cart_units' => $resumen['unidades'],
            'cart_subtotal' => $resumen['subtotal'] > 0 ? '$' . number_format($resumen['subtotal'], 0) . ' MXN' : '$0 MXN',
            'cart_total' => $resumen['subtotal'] > 0 ? '$' . number_format($resumen['subtotal'], 0) . ' MXN' : '$0 MXN',
            'item_eliminado' => $idItem,
            'vacio' => $resumen['items'] === 0,
        ]);
    }

    if ($accion === 'vaciar') {
        $pdo->prepare('DELETE FROM carrito WHERE usuario_id = ?')->execute([$usuarioId]);
        responder([
            'ok' => true,
            'mensaje' => 'Carrito vaciado.',
            'tipo' => 'success',
            'cart_count' => 0,
            'cart_items' => 0,
            'cart_units' => 0,
            'cart_subtotal' => '$0 MXN',
            'cart_total' => '$0 MXN',
            'vacio' => true,
        ]);
    }
}

/* ---------- Contenido del carrito ---------- */
$stmtItems = $pdo->prepare('SELECT c.*, sv.titulo AS servicio_titulo, sv.icono AS servicio_icono,
                            pr.titulo AS producto_titulo, pr.imagen AS producto_imagen
                            FROM carrito c
                            LEFT JOIN servicios sv ON c.servicio_id = sv.id
                            LEFT JOIN productos pr ON c.producto_id = pr.id
                            WHERE c.usuario_id = ?
                            ORDER BY c.creado_en DESC');
$stmtItems->execute([$usuarioId]);
$items = $stmtItems->fetchAll();

$subtotalGlobal = 0.0;
foreach ($items as $it) {
    $subtotalGlobal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
}

/* Métodos de pago activos para el checkout */
$metodosPago = $pdo->query('SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY nombre ASC')->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">Mi carrito</h4>
        <p class="text-muted mb-0">Revisa los servicios y productos que deseas contratar.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="pagos" class="btn btn-outline-fv btn-sm"><i class="bi bi-credit-card me-1"></i>Mis pagos</a>
        <a href="mis-solicitudes" class="btn btn-outline-fv btn-sm"><i class="bi bi-inbox me-1"></i>Mis solicitudes</a>
    </div>
</div>

<?php $vacioClase = $items ? 'd-none' : ''; $llenoClase = $items ? '' : 'd-none'; ?>

<div id="carritoVacio" class="<?= $vacioClase ?>">
    <div class="card portal-card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-cart-x fs-1 text-primary d-block mb-3"></i>
        <h5>Tu carrito está vacío</h5>
        <p class="text-muted">Agrega servicios o productos desde el sitio y luego procede al pago.</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="../servicios.php" class="btn btn-fv"><i class="bi bi-grid me-1"></i>Ver servicios</a>
            <?php $nProductos = (int)$pdo->query('SELECT COUNT(*) FROM productos WHERE activo = 1')->fetchColumn(); ?>
            <?php if ($nProductos > 0): ?>
                <a href="../productos.php" class="btn btn-outline-fv"><i class="bi bi-box-seam me-1"></i>Ver productos</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="carritoLleno" class="<?= $llenoClase ?>">
    <div class="row g-4">
        <!-- Lista de ítems -->
        <div class="col-lg-8">
            <div class="card portal-card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <b><i class="bi bi-cart3 me-1 text-primary"></i>Ítems del carrito</b>
                    <form method="POST" action="carrito.php" onsubmit="return confirm('¿Vaciar todo el carrito?')" class="js-ajax d-inline">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="vaciar">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Vaciar</button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($items as $it): ?>
                        <?php
                        $tituloItem = $it['servicio_titulo'] ?: ($it['producto_titulo'] ?: 'Ítem');
                        $esServicio = (int)$it['servicio_id'] > 0;
                        $img = !$esServicio ? $it['producto_imagen'] : null;
                        $subtotal = (float)$it['precio_unitario'] * (int)$it['cantidad'];
                        ?>
                        <div class="d-flex flex-wrap align-items-center gap-3 p-3 border-bottom" id="item-<?= (int)$it['id'] ?>">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                                 style="width:64px;height:64px;border-radius:12px;background:#e8f0fe;object-fit:cover;overflow:hidden;">
                                <?php if ($img && file_exists(__DIR__ . '/../' . $img)): ?>
                                    <img src="../<?= e($img) ?>" alt="<?= e($tituloItem) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <i class="bi <?= e($it['servicio_icono'] ?: 'bi-box-seam') ?> text-primary fs-3"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <b><?= e($tituloItem) ?></b>
                                <small class="text-muted d-block">
                                    <?= $esServicio ? '<i class="bi bi-grid me-1"></i>Servicio' : '<i class="bi bi-box-seam me-1"></i>Producto' ?>
                                    · $<?= number_format((float)$it['precio_unitario'], 0) ?> c/u
                                </small>
                            </div>
                            <form method="POST" action="carrito.php" class="js-ajax d-flex align-items-center gap-2">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="actualizar">
                                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                <div class="input-group input-group-sm" style="width:110px;">
                                    <input type="number" name="cantidad" class="form-control text-center" min="1" value="<?= (int)$it['cantidad'] ?>">
                                    <button class="btn btn-outline-primary" title="Actualizar"><i class="bi bi-arrow-clockwise"></i></button>
                                </div>
                            </form>
                            <b class="text-primary" style="min-width:90px;text-align:right;" data-subtotal-item="<?= (int)$it['id'] ?>">$<?= number_format($subtotal, 0) ?></b>
                            <form method="POST" action="carrito.php" onsubmit="return confirm('¿Eliminar este ítem?')" class="js-ajax d-inline">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Resumen y checkout -->
        <div class="col-lg-4">
            <div class="card portal-card border-0 shadow-sm sticky-top" style="top:90px;">
                <div class="card-header bg-white"><b><i class="bi bi-receipt me-1 text-primary"></i>Resumen</b></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Subtotal (<span data-cart-items><?= count($items) ?></span> ítems · <span data-cart-units><?= array_sum(array_map(fn($i) => (int)$i['cantidad'], $items)) ?></span> unidades)</span>
                        <b data-cart-subtotal>$<?= number_format($subtotalGlobal, 0) ?> MXN</b>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5 mb-1">
                        <span><b>Total</b></span>
                        <b class="text-primary" data-cart-total>$<?= number_format($subtotalGlobal, 0) ?> MXN</b>
                    </div>
                    <small class="text-muted d-block mb-3">Puedes pagar ahora o enviar tu comprobante después.</small>

                    <button class="btn btn-fv w-100 mb-2 <?= count($metodosPago) === 0 ? 'disabled' : '' ?>" <?= count($metodosPago) === 0 ? 'disabled title="No hay métodos de pago configurados"' : '' ?> data-bs-toggle="modal" data-bs-target="#modalCheckout"><i class="bi bi-credit-card me-1"></i>Proceder al pago</button>
                    <?php if (count($metodosPago) === 0): ?>
                        <small class="text-danger d-block text-center mb-2"><i class="bi bi-exclamation-triangle me-1"></i>No hay métodos de pago disponibles. Contacta al administrador.</small>
                    <?php endif; ?>
                    <a href="../servicios.php" class="btn btn-outline-fv w-100"><i class="bi bi-plus-lg me-1"></i>Seguir agregando</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de checkout -->
    <div class="modal fade" id="modalCheckout" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" class="js-ajax" action="pagos.php" enctype="multipart/form-data">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="pagar_producto">
                    <input type="hidden" name="clave_unica" value="<?= e(pago_generar_clave(['usuario' => (int)($usuario['id'] ?? 0), 'accion' => 'pagar_producto', 'sesion' => session_id()])) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-credit-card me-2 text-primary"></i>Proceder al pago</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-between py-2 border-bottom mb-3">
                            <span class="text-muted">Total a pagar</span>
                            <b class="text-primary fs-5">$<?= number_format($subtotalGlobal, 0) ?> MXN</b>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Método de pago</label>
                                <select name="metodo_pago_id" id="metodoPagoSelect" class="form-select" required>
                                    <option value="">Selecciona un método...</option>
                                    <?php foreach ($metodosPago as $mp): ?>
                                        <option value="<?= (int)$mp['id'] ?>" data-nombre="<?= e($mp['nombre']) ?>" data-descripcion="<?= e($mp['descripcion'] ?? '') ?>" data-detalles="<?= e($mp['detalles_cuenta'] ?? '') ?>" data-instrucciones="<?= e($mp['instrucciones'] ?? '') ?>" data-icono="<?= e($mp['icono'] ?? 'bi-credit-card') ?>"><?= e($mp['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="metodoPagoInfo" class="d-none mt-3">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary-subtle d-flex align-items-center gap-2 py-2">
                                            <i class="bi bi-credit-card text-primary" id="infoIcono"></i>
                                            <b class="small" id="infoNombre"></b>
                                        </div>
                                        <div class="card-body p-3 small">
                                            <p class="text-muted mb-2" id="infoDescripcion"></p>
                                            <div class="mb-2" id="infoDetalles"></div>
                                            <div id="infoInstrucciones"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Monto (MXN)</label>
                                <input type="number" name="monto" class="form-control" min="1" step="0.01" value="<?= number_format($subtotalGlobal, 0, '.', '') ?>" readonly>
                                <small class="text-muted">El monto queda fijo conforme a tu carrito.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Comprobante de pago (obligatorio) *</label>
                                <input type="file" name="comprobante" class="form-control" accept="image/*,.pdf" required>
                                <small class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Tu pedido no será procesado hasta que subas tu comprobante de pago.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-fv"><i class="bi bi-check-lg me-1"></i>Confirmar pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Mostrar datos de pago al seleccionar el método en el modal por método de pago
document.getElementById('metodoPagoSelect')?.addEventListener('change', function () {
    var info = document.getElementById('metodoPagoInfo');
    var opt = this.options[this.selectedIndex];
    if (!opt || !opt.value) { info.classList.add('d-none'); return; }
    document.getElementById('infoNombre').textContent = opt.dataset.nombre || '';
    document.getElementById('infoDescripcion').textContent = opt.dataset.descripcion || '';
    document.getElementById('infoIcono').className = 'bi ' + (opt.dataset.icono || 'bi-credit-card') + ' text-primary';
    var detalles = document.getElementById('infoDetalles');
    if (opt.dataset.detalles) {
        detalles.className = 'mb-2 p-2 rounded bg-light border d-flex align-items-start gap-2';
        detalles.innerHTML = '<i class="bi bi-info-circle text-primary mt-1"></i><span><b class="text-muted d-block">Datos para el pago</b>' + opt.dataset.detalles.replace(/\n/g, '<br>') + '</span>';
    } else { detalles.className = 'd-none'; detalles.innerHTML = ''; }
    var inst = document.getElementById('infoInstrucciones');
    if (opt.dataset.instrucciones) {
        inst.className = 'p-2 rounded bg-primary-subtle border d-flex align-items-start gap-2';
        inst.innerHTML = '<i class="bi bi-list-check text-primary mt-1"></i><span><b class="text-muted d-block">Instrucciones</b>' + opt.dataset.instrucciones.replace(/\n/g, '<br>') + '</span>';
    } else { inst.className = 'd-none'; inst.innerHTML = ''; }
    info.classList.remove('d-none');
});

// Actualizaciones en vivo del carrito (sin recargar la página)
document.addEventListener('fv:ajaxok', function (e) {
    var d = (e && e.detail) || {};
    if (typeof d.cart_count !== 'number') return;

    var itemEliminado = d.item_eliminado;
    if (itemEliminado) {
        var fila = document.getElementById('item-' + itemEliminado);
        if (fila && fila.parentNode) fila.parentNode.removeChild(fila);
    }
    if (d.item_id && d.item_subtotal) {
        var sub = document.querySelector('[data-subtotal-item="' + d.item_id + '"]');
        if (sub) sub.textContent = d.item_subtotal;
    }
    if (d.vacio) {
        var lleno = document.getElementById('carritoLleno');
        var vacio = document.getElementById('carritoVacio');
        if (lleno) lleno.classList.add('d-none');
        if (vacio) vacio.classList.remove('d-none');
    }
});
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>