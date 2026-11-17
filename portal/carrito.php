<?php
require_once __DIR__ . '/includes/cabecera.php';

$seccionPortal = 'carrito';
$titulo = 'Mi carrito';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'email' => ''];
$usuarioId = (int)$usuario['id'];

/* ---------- Acciones POST (agregar / actualizar / eliminar / vaciar / resumen) ----------
   Todas las respuestas se construyen con datos_carrito(): así el resumen, los
   totales y el badge siempre se recalculan desde la BD y nunca desde el cliente. */
function datos_carrito(int $usuarioId, array $extra = []): array {
    $c = carrito_calcular($usuarioId, true);
    return array_merge([
        'cart_count'      => $c['count'],
        'cart_items'      => $c['item_count'],
        'cart_units'      => $c['unidades'],
        'cart_subtotal'   => carrito_monto($c['subtotal']),
        'cart_total'      => carrito_monto($c['subtotal']),
        'cart_subtotal_num' => $c['subtotal'],
        'cart_total_num'    => $c['subtotal'],
        'vacio'           => $c['item_count'] === 0,
        'avisos'          => $c['avisos'],
    ], $extra);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        responder(['ok' => false, 'mensaje' => 'Token de seguridad inválido. Recarga la página.', 'tipo' => 'danger']);
    }
    $accion = $_POST['accion'] ?? '';

    /* Refrescar precios/stock y totales antes de abrir el checkout. */
    if ($accion === 'resumen') {
        responder(datos_carrito($usuarioId));
    }

    if ($accion === 'agregar') {
        $servicioId = (int)($_POST['servicio_id'] ?? 0);
        $productoId = (int)($_POST['producto_id'] ?? 0);
        $cantidad   = max(1, (int)($_POST['cantidad'] ?? 1));

        /* Los servicios NO se compran por carrito: se solicitan con el
           formulario de cotización para darles seguimiento. */
        if ($servicioId > 0 && $productoId <= 0) {
            responder([
                'ok'      => false,
                'tipo'    => 'info',
                'titulo'  => 'Los servicios se cotizan',
                'mensaje' => 'Para contratar un servicio completa el formulario de solicitud y te enviaremos una propuesta.',
                'destino' => 'nueva-solicitud',
            ]);
        }

        if ($productoId > 0) {
            $stmt = $pdo->prepare('SELECT id, titulo, precio, stock, activo FROM productos WHERE id = ? AND activo = 1');
            $stmt->execute([$productoId]);
            $p = $stmt->fetch();
            if (!$p) {
                responder(['ok' => false, 'mensaje' => 'El producto ya no está disponible.', 'tipo' => 'warning']);
            }
            $precio     = (float)$p['precio'];
            $stock      = max(0, (int)$p['stock']);
            $tituloItem = (string)$p['titulo'];

            if ($precio <= 0) {
                responder(['ok' => false, 'mensaje' => 'Este producto no tiene precio configurado.', 'tipo' => 'warning']);
            }
            if ($stock <= 0) {
                responder(['ok' => false, 'mensaje' => 'Lo sentimos, ' . $tituloItem . ' está sin existencias.', 'tipo' => 'warning']);
            }

            /* Tope real: lo que ya hay en el carrito + lo nuevo nunca supera
               las existencias. Si sobra solicitud se recorta y se avisa. */
            $stmtChk = $pdo->prepare('SELECT id, cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ?');
            $stmtChk->execute([$usuarioId, $productoId]);
            $existente   = $stmtChk->fetch();
            $enCarrito   = $existente ? max(0, (int)$existente['cantidad']) : 0;
            $disponible  = $stock - $enCarrito;
            if ($disponible <= 0) {
                responder([
                    'ok'      => false,
                    'mensaje' => 'Ya tienes las ' . $stock . ' unidad(es) disponibles de ' . $tituloItem . ' en tu carrito.',
                    'tipo'    => 'warning',
                ]);
            }
            $final = min($cantidad, $disponible);
            $aviso = $final < $cantidad
                ? 'Solo quedaban ' . $disponible . ' unidad(es) de ' . $tituloItem . '; se agregó esa cantidad.'
                : $tituloItem . ' se agregó a tu carrito.';

            if ($existente) {
                $idItem = (int)$existente['id'];
                $pdo->prepare('UPDATE carrito SET cantidad = ?, precio_unitario = ? WHERE id = ? AND usuario_id = ?')
                    ->execute([$final, $precio, $idItem, $usuarioId]);
            } else {
                $pdo->prepare('INSERT INTO carrito (usuario_id, servicio_id, producto_id, cantidad, precio_unitario) VALUES (?,?,?,?,?)')
                    ->execute([$usuarioId, null, $productoId, $final, $precio]);
                $idItem = (int)$pdo->lastInsertId();
            }

            responder(datos_carrito($usuarioId, [
                'mensaje'       => $aviso,
                'tipo'          => $final < $cantidad ? 'warning' : 'success',
                'item_id'       => $idItem,
                'item_cantidad' => $final,
                'item_subtotal' => carrito_monto($precio * $final, false),
                'stock_max'     => $stock,
            ]));
        }

        if ($servicioId > 0) {
            $stmt = $pdo->prepare('SELECT id, titulo, precio_desde FROM servicios WHERE id = ? AND activo = 1');
            $stmt->execute([$servicioId]);
            $s = $stmt->fetch();
            if (!$s) {
                responder(['ok' => false, 'mensaje' => 'El servicio ya no está disponible.', 'tipo' => 'warning']);
            }
            $precio     = (float)($s['precio_desde'] ?? 0);
            $tituloItem = (string)$s['titulo'];
            if ($precio <= 0) {
                responder(['ok' => false, 'mensaje' => 'Este servicio no tiene precio configurado.', 'tipo' => 'warning']);
            }
            $stmtChk = $pdo->prepare('SELECT id FROM carrito WHERE usuario_id = ? AND servicio_id = ?');
            $stmtChk->execute([$usuarioId, $servicioId]);
            $existente = $stmtChk->fetch();
            if ($existente) {
                $idItem = (int)$existente['id'];
                $pdo->prepare('UPDATE carrito SET cantidad = cantidad + ?, precio_unitario = ? WHERE id = ? AND usuario_id = ?')
                    ->execute([$cantidad, $precio, $idItem, $usuarioId]);
                $stmtCant = $pdo->prepare('SELECT cantidad FROM carrito WHERE id = ?');
                $stmtCant->execute([$idItem]);
                $final = (int)$stmtCant->fetchColumn();
            } else {
                $pdo->prepare('INSERT INTO carrito (usuario_id, servicio_id, producto_id, cantidad, precio_unitario) VALUES (?,?,?,?,?)')
                    ->execute([$usuarioId, $servicioId, null, $cantidad, $precio]);
                $idItem = (int)$pdo->lastInsertId();
                $final  = $cantidad;
            }
            responder(datos_carrito($usuarioId, [
                'mensaje'       => $tituloItem . ' se agregó a tu carrito.',
                'item_id'       => $idItem,
                'item_cantidad' => $final,
                'item_subtotal' => carrito_monto($precio * $final, false),
                'stock_max'     => 0,
            ]));
        }

        responder(['ok' => false, 'mensaje' => 'No se especificó qué agregar.', 'tipo' => 'warning']);
    }

    if ($accion === 'actualizar') {
        $idItem   = (int)($_POST['id'] ?? 0);
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
        $stmt = $pdo->prepare('SELECT c.*, pr.precio AS producto_precio, pr.stock AS producto_stock, pr.activo AS producto_activo,
                                     sv.precio_desde AS servicio_precio, sv.activo AS servicio_activo
                              FROM carrito c
                              LEFT JOIN productos pr ON c.producto_id = pr.id
                              LEFT JOIN servicios sv ON c.servicio_id = sv.id
                              WHERE c.id = ? AND c.usuario_id = ?');
        $stmt->execute([$idItem, $usuarioId]);
        $item = $stmt->fetch();
        if (!$item) {
            responder(['ok' => false, 'mensaje' => 'Ese ítem ya no está en tu carrito.', 'tipo' => 'warning']);
        }

        if ((int)($item['producto_id'] ?? 0) > 0) {
            $stock = max(0, (int)($item['producto_stock'] ?? 0));
            $precio = (float)($item['producto_precio'] ?? 0);
            if ($stock <= 0 || (int)($item['producto_activo'] ?? 0) !== 1 || $precio <= 0) {
                $pdo->prepare('DELETE FROM carrito WHERE id = ? AND usuario_id = ?')->execute([$idItem, $usuarioId]);
                responder(datos_carrito($usuarioId, [
                    'mensaje'       => 'El producto ya no tiene existencias y se quitó del carrito.',
                    'tipo'          => 'warning',
                    'item_eliminado'=> $idItem,
                ]));
            }
            $cantidad = min($cantidad, $stock); // Nunca por encima del stock.
            $pdo->prepare('UPDATE carrito SET cantidad = ?, precio_unitario = ? WHERE id = ? AND usuario_id = ?')
                ->execute([$cantidad, $precio, $idItem, $usuarioId]);
            responder(datos_carrito($usuarioId, [
                'mensaje'       => $cantidad < max(1, (int)($_POST['cantidad'] ?? 1))
                    ? 'Cantidad ajustada: solo hay ' . $stock . ' unidad(es) disponibles.'
                    : 'Carrito actualizado.',
                'tipo'          => $cantidad < max(1, (int)($_POST['cantidad'] ?? 1)) ? 'warning' : 'success',
                'item_id'       => $idItem,
                'item_cantidad' => $cantidad,
                'item_subtotal' => carrito_monto($precio * $cantidad, false),
                'stock_max'     => $stock,
            ]));
        }

        $pdo->prepare('UPDATE carrito SET cantidad = ? WHERE id = ? AND usuario_id = ?')
            ->execute([$cantidad, $idItem, $usuarioId]);
        responder(datos_carrito($usuarioId, [
            'mensaje'       => 'Carrito actualizado.',
            'item_id'       => $idItem,
            'item_cantidad' => $cantidad,
            'item_subtotal' => carrito_monto((float)($item['servicio_precio'] ?? $item['precio_unitario']) * $cantidad, false),
            'stock_max'     => 0,
        ]));
    }

    if ($accion === 'eliminar') {
        $idItem = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM carrito WHERE id = ? AND usuario_id = ?')->execute([$idItem, $usuarioId]);
        responder(datos_carrito($usuarioId, [
            'mensaje'        => 'Ítem eliminado del carrito.',
            'item_eliminado' => $idItem,
        ]));
    }

    if ($accion === 'vaciar') {
        $pdo->prepare('DELETE FROM carrito WHERE usuario_id = ?')->execute([$usuarioId]);
        responder(datos_carrito($usuarioId, ['mensaje' => 'Carrito vaciado.']));
    }
}

/* ---------- Contenido del carrito ----------
   Sincronizar al cargar evita que un precio cambiado por el admin o una
   bajada de stock se siga cobrando con datos viejos. */
$carrito     = carrito_calcular($usuarioId, true);
$items       = $carrito['items'];
$subtotalGlobal = $carrito['subtotal'];
$unidadesGlobal = $carrito['unidades'];

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

<!-- Avisos de stock / precio recalculados automáticamente -->
<div id="avisosStock" class="alert alert-warning py-2 px-3 small <?= $carrito['avisos'] ? '' : 'd-none' ?>">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <span id="avisosStockTexto"><?= $carrito['avisos'] ? e(implode(' ', $carrito['avisos'])) : '' ?></span>
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
                        $tituloItem = $it['titulo'];
                        $esServicio = !$it['es_producto'];
                        $img = $it['imagen'];
                        $stockItem = $it['stock']; // null en servicios
                        $subtotal = $it['subtotal'];
                        $maxCant  = $esServicio ? 9999 : (int)$stockItem;
                        ?>
                        <div class="d-flex flex-wrap align-items-center gap-3 p-3 border-bottom" id="item-<?= (int)$it['id'] ?>" data-precio-item="<?= (float)$it['precio_unitario'] ?>">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                                 style="width:64px;height:64px;border-radius:12px;background:#e8f0fe;object-fit:cover;overflow:hidden;">
                                <?php if ($img && file_exists(__DIR__ . '/../' . $img)): ?>
                                    <img src="../<?= e($img) ?>" alt="<?= e($tituloItem) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <i class="bi <?= e($it['icono'] ?: 'bi-box-seam') ?> text-primary fs-3"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <b><?= e($tituloItem) ?></b>
                                <small class="text-muted d-block">
                                    <?= $esServicio ? '<i class="bi bi-grid me-1"></i>Servicio' : '<i class="bi bi-box-seam me-1"></i>Producto' ?>
                                    · <?= e(carrito_monto((float)$it['precio_unitario'], false)) ?> c/u
                                </small>
                                <?php if (!$esServicio): ?>
                                    <small class="d-block mt-1 <?= $stockItem <= 3 ? 'text-danger' : 'text-success' ?>">
                                        <i class="bi bi-<?= $stockItem <= 3 ? 'exclamation-triangle' : 'check-circle' ?> me-1"></i>
                                        <?= $stockItem <= 3 ? 'Quedan solo ' . (int)$stockItem . ' disponibles' : 'Quedan ' . (int)$stockItem . ' disponibles' ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <form method="POST" action="carrito.php" class="js-ajax d-flex align-items-center gap-2" data-form-item="<?= (int)$it['id'] ?>">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="actualizar">
                                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                <div class="input-group input-group-sm" style="width:132px;">
                                    <button type="button" class="btn btn-outline-secondary" data-cart-menos aria-label="Quitar una unidad" <?= (int)$it['cantidad'] <= 1 ? 'disabled' : '' ?>>−</button>
                                    <input type="number" name="cantidad" class="form-control text-center" min="1" max="<?= $maxCant ?>" value="<?= (int)$it['cantidad'] ?>" aria-label="Cantidad">
                                    <button type="button" class="btn btn-outline-secondary" data-cart-mas aria-label="Agregar una unidad" <?= (int)$it['cantidad'] >= $maxCant ? 'disabled' : '' ?>>+</button>
                                </div>
                                <button class="btn btn-outline-primary btn-sm" title="Actualizar"><i class="bi bi-arrow-clockwise"></i></button>
                            </form>
                            <b class="text-primary" style="min-width:96px;text-align:right;" data-subtotal-item="<?= (int)$it['id'] ?>"><?= e(carrito_monto($subtotal, false)) ?></b>
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
                        <span class="text-muted">Subtotal (<span data-cart-items><?= $carrito['item_count'] ?></span> ítems · <span data-cart-units><?= $unidadesGlobal ?></span> unidades)</span>
                        <b data-cart-subtotal><?= e(carrito_monto($subtotalGlobal)) ?></b>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5 mb-1">
                        <span><b>Total</b></span>
                        <b class="text-primary" data-cart-total><?= e(carrito_monto($subtotalGlobal)) ?></b>
                    </div>
                    <small class="text-muted d-block mb-3">Precio y existencias se recalculan al abrir el pago. Puedes enviar tu comprobante después.</small>

                    <?php $pagoBloqueado = count($metodosPago) === 0 || $carrito['item_count'] === 0; ?>
                    <button type="button" id="btnProcederPago" class="btn btn-fv w-100 mb-2 <?= $pagoBloqueado ? 'disabled' : '' ?>" <?= $pagoBloqueado ? 'disabled title="Agrega productos y verifica los métodos de pago"' : '' ?>><i class="bi bi-credit-card me-1"></i>Proceder al pago</button>
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
                        <!-- Resumen de costos: se refresca desde el servidor al abrir el modal -->
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-2 px-3 small">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Productos (<span data-checkout-items><?= $carrito['item_count'] ?></span> ítems · <span data-checkout-unidades><?= $unidadesGlobal ?></span> unidades)</span>
                                    <b data-checkout-subtotal-texto><?= e(carrito_monto($subtotalGlobal)) ?></b>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <span class="text-muted">Subtotal</span>
                                    <b data-checkout-subtotal><?= e(carrito_monto($subtotalGlobal)) ?></b>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between fs-5">
                                    <span><b>Total a pagar</b></span>
                                    <b class="text-primary" data-checkout-total><?= e(carrito_monto($subtotalGlobal)) ?></b>
                                </div>
                            </div>
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
                                <input type="number" name="monto" class="form-control" min="1" step="0.01" value="<?= number_format($subtotalGlobal, 2, '.', '') ?>" readonly data-checkout-monto>
                                <small class="text-muted">Monto calculado por el servidor con precios y stock vigentes.</small>
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

/* ---------- Utilidades del carrito ---------- */
function fmtMXN(n) {
    return '$' + (Number(n) || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function ponerTexto(sel, valor) {
    var el = document.querySelector(sel);
    if (el && valor != null && el.textContent !== String(valor)) {
        el.textContent = String(valor);
        if (window.FV && FV.pulso) FV.pulso(el);
    }
}
function mostrarAvisos(d) {
    var caja = document.getElementById('avisosStock');
    var texto = document.getElementById('avisosStockTexto');
    if (!caja || !texto) return;
    var lista = Array.isArray(d.avisos) ? d.avisos.slice() : [];
    if (d.mensaje && d.tipo === 'warning' && lista.indexOf(d.mensaje) === -1) lista.push(d.mensaje);
    if (lista.length) { texto.textContent = lista.join(' '); caja.classList.remove('d-none'); }
    else { texto.textContent = ''; caja.classList.add('d-none'); }
}
function estadoBotones(fila) {
    var form = fila.querySelector('[data-form-item]');
    if (!form) return;
    var input = form.querySelector('[name="cantidad"]');
    if (!input) return;
    var val = parseInt(input.value, 10) || 1;
    var min = parseInt(input.min, 10) || 1;
    var max = parseInt(input.max, 10) || 1;
    var menos = form.querySelector('[data-cart-menos]');
    var mas = form.querySelector('[data-cart-mas]');
    if (menos) menos.disabled = val <= min;
    if (mas) mas.disabled = val >= max;
}
function vistaPrevia(fila, cantidad) {
    var precio = parseFloat(fila.dataset.precioItem || '0') || 0;
    var sub = fila.querySelector('[data-subtotal-item]');
    if (sub) sub.textContent = fmtMXN(precio * cantidad);
}

/* ---------- Controles +/- con debounce (un solo envío al servidor) ---------- */
var temporizadores = {};
function programarGuardado(form) {
    var hidden = form.querySelector('[name="id"]');
    if (!hidden) return;
    var id = hidden.value;
    clearTimeout(temporizadores[id]);
    temporizadores[id] = setTimeout(function () {
        if (!form.dataset.enviando && typeof form.requestSubmit === 'function') form.requestSubmit();
    }, 350);
}

document.querySelectorAll('[data-form-item]').forEach(function (form) {
    var fila = form.closest('[id^="item-"]');
    var input = form.querySelector('[name="cantidad"]');

    form.querySelectorAll('[data-cart-menos], [data-cart-mas]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!input || btn.disabled) return;
            var min = parseInt(input.min, 10) || 1;
            var max = parseInt(input.max, 10) || 1;
            var val = parseInt(input.value, 10) || min;
            var delta = btn.hasAttribute('data-cart-mas') ? 1 : -1;
            input.value = Math.min(max, Math.max(min, val + delta));
            vistaPrevia(fila, parseInt(input.value, 10));
            estadoBotones(fila);
            programarGuardado(form);
        });
    });

    if (input) {
        input.addEventListener('change', function () {
            var min = parseInt(input.min, 10) || 1;
            var max = parseInt(input.max, 10) || 1;
            input.value = Math.min(max, Math.max(min, parseInt(input.value, 10) || min));
            vistaPrevia(fila, parseInt(input.value, 10));
            estadoBotones(fila);
            programarGuardado(form);
        });
    }

    var submit = form.querySelector('button[type="submit"]');
    if (submit) submit.addEventListener('click', function () {
        var hidden = form.querySelector('[name="id"]');
        if (hidden) clearTimeout(temporizadores[hidden.value]);
    });

    estadoBotones(fila);
});

/* ---------- Resumen del checkout (se actualiza antes de abrir el modal) ---------- */
function refrescarCheckout(d) {
    ponerTexto('[data-checkout-items]', d.cart_items);
    ponerTexto('[data-checkout-unidades]', d.cart_units);
    ponerTexto('[data-checkout-subtotal]', d.cart_subtotal);
    ponerTexto('[data-checkout-subtotal-texto]', d.cart_subtotal);
    ponerTexto('[data-checkout-total]', d.cart_total);
    var monto = document.querySelector('[data-checkout-monto]');
    if (monto && d.cart_total_num != null) monto.value = Number(d.cart_total_num).toFixed(2);
}

var btnProceder = document.getElementById('btnProcederPago');
if (btnProceder) {
    btnProceder.addEventListener('click', function () {
        var fd = new FormData();
        fd.append('accion', 'resumen');
        var csrf = document.querySelector('[name="csrf"]');
        if (csrf) fd.append('csrf', csrf.value);

        var habilitado = !btnProceder.disabled;
        btnProceder.disabled = true;
        if (window.FV && FV.cargando) FV.cargando(true);

        fetch('carrito.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (window.FV && FV.cargando) FV.cargando(false);
            btnProceder.disabled = !habilitado;
            if (!d.ok) { if (window.FV && FV.overlay) FV.overlay(d); return; }
            if (d.vacio) {
                document.getElementById('carritoLleno').classList.add('d-none');
                document.getElementById('carritoVacio').classList.remove('d-none');
                if (window.FV && FV.overlay) FV.overlay({
                    ok: false, mensaje: 'Tu carrito quedó vacío. Agrega productos antes de pagar.', tipo: 'warning'
                });
                return;
            }
            mostrarAvisos(d);
            if (window.FV && FV.actualizarResumen) FV.actualizarResumen(d);
            refrescarCheckout(d);
            var modalEl = document.getElementById('modalCheckout');
            if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }).catch(function () {
            if (window.FV && FV.cargando) FV.cargando(false);
            btnProceder.disabled = !habilitado;
            var modalEl = document.getElementById('modalCheckout');
            if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });
}

/* ---------- Actualizaciones en vivo del carrito (sin recargar) ---------- */
document.addEventListener('fv:ajaxok', function (e) {
    var d = (e && e.detail) || {};
    if (typeof d.cart_count !== 'number') return;

    mostrarAvisos(d);

    if (d.item_eliminado) {
        var filaElim = document.getElementById('item-' + d.item_eliminado);
        if (filaElim && filaElim.parentNode) {
            filaElim.classList.add('fv-fila-sale');
            setTimeout(function () { if (filaElim.parentNode) filaElim.parentNode.removeChild(filaElim); }, 320);
        }
    }

    if (d.item_id && d.item_subtotal) {
        var fila = document.getElementById('item-' + d.item_id);
        var sub = fila && fila.querySelector('[data-subtotal-item="' + d.item_id + '"]');
        if (sub) { sub.textContent = d.item_subtotal; if (window.FV && FV.pulso) FV.pulso(sub); }
        if (fila) {
            var input = fila.querySelector('[name="cantidad"]');
            if (input) {
                if (d.item_cantidad != null) input.value = d.item_cantidad;
                if (d.stock_max != null) input.max = d.stock_max;
                var cantidad = parseInt(input.value, 10) || 1;
                var precio = parseFloat(d.item_subtotal.replace(/[^0-9.]/g, '')) / Math.max(1, cantidad);
                if (precio > 0) fila.dataset.precioItem = String(precio);
                estadoBotones(fila);
            }
        }
    }

    var btn = document.getElementById('btnProcederPago');
    if (btn) {
        var sinMetodos = !document.getElementById('metodoPagoSelect')
            || document.getElementById('metodoPagoSelect').options.length <= 1;
        if (d.vacio || d.cart_items === 0 || sinMetodos) btn.disabled = true;
        else if (document.getElementById('carritoLleno') && !document.getElementById('carritoLleno').classList.contains('d-none')) btn.disabled = false;
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