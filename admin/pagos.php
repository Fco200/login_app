<?php
$titulo = 'Pagos';
$subtitulo = 'Métodos de pago, registro de pagos de clientes y productos';
$seccionAdmin = 'pagos.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    /* ---- Métodos de pago ---- */
    if ($accion === 'guardar_metodo') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            flash('El nombre del método de pago es obligatorio.', 'danger');
            header('Location: pagos.php');
            exit;
        }
        $descripcion = trim($_POST['descripcion'] ?? '');
        $detalles = trim($_POST['detalles_cuenta'] ?? '');
        $instrucciones = trim($_POST['instrucciones'] ?? '');
        $icono = trim($_POST['icono'] ?? 'bi-credit-card');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($id > 0) {
            $pdo->prepare('UPDATE metodos_pago SET nombre=?, descripcion=?, detalles_cuenta=?, instrucciones=?, icono=?, activo=? WHERE id=?')
                ->execute([$nombre, $descripcion, $detalles, $instrucciones, $icono, $activo, $id]);
            flash('Método de pago actualizado.');
        } else {
            $pdo->prepare('INSERT INTO metodos_pago (nombre, descripcion, detalles_cuenta, instrucciones, icono, activo) VALUES (?,?,?,?,?,?)')
                ->execute([$nombre, $descripcion, $detalles, $instrucciones, $icono, $activo]);
            flash('Método de pago creado.');
        }
        header('Location: pagos.php');
        exit;
    }

    if ($accion === 'eliminar_metodo' && isset($_POST['id'])) {
        $pdo->prepare('UPDATE metodos_pago SET activo = 0 WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Método de pago desactivado.', 'warning');
        header('Location: pagos.php');
        exit;
    }

    if ($accion === 'activar_metodo' && isset($_POST['id'])) {
        $pdo->prepare('UPDATE metodos_pago SET activo = 1 WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Método de pago activado.');
        header('Location: pagos.php');
        exit;
    }

    /* ---- Registro de pagos: cambiar estado (con recálculo y bitácora) ---- */
    if ($accion === 'estado_pago') {
        $id = (int)($_POST['id'] ?? 0);
        $estado = in_array($_POST['estado'] ?? '', ['pendiente', 'aprobado', 'rechazado'], true) ? $_POST['estado'] : '';
        $notasAdmin = trim($_POST['notas'] ?? '');
        if ($id <= 0) {
            responder(['ok' => false, 'mensaje' => 'Pago no encontrado.', 'tipo' => 'danger']);
        }
        if ($estado === 'pendiente') {
            responder(['ok' => false, 'mensaje' => 'El pago ya se encuentra pendiente.', 'tipo' => 'info']);
        }
        $r = pago_aprobar_o_rechazar($id, $estado, $notasAdmin);
        responder([
            'ok'          => $r['ok'],
            'mensaje'     => $r['mensaje'],
            'accion'      => 'estado_pago',
            'pago_id'     => $id,
            'estado_pago' => $estado,
            'recalculo'   => $r['recalculo'] ?? null,
            'proyecto_id' => $r['proyecto_id'] ?? null,
            'nota'        => $notasAdmin,
        ]);
    }

    /* ---- Adjuntar comprobante desde el panel ---- */
    if ($accion === 'adjuntar_comprobante' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if (!empty($_FILES['comprobante']['name'])) {
            $res = subir_archivo('comprobante', 'comprobantes', ['jpg', 'jpeg', 'png', 'pdf', 'webp'], 8);
            if ($res['ok']) {
                $pdo->prepare('UPDATE pagos SET comprobante = ? WHERE id = ?')->execute([$res['archivo'], $id]);
                responder([
                    'ok'           => true,
                    'mensaje'      => 'Comprobante adjuntado.',
                    'accion'       => 'comprobante_adjunto',
                    'pago_id'      => $id,
                    'comprobante'  => $res['archivo'],
                ]);
            }
            responder(['ok' => false, 'mensaje' => 'Error al adjuntar: ' . $res['error'], 'tipo' => 'danger']);
        }
        responder(['ok' => false, 'mensaje' => 'No se recibió ningún archivo.', 'tipo' => 'warning']);
    }

    /* ---- Eliminar pago ---- */
    if ($accion === 'eliminar_pago' && isset($_POST['id'])) {
        $stmtP = $pdo->prepare('SELECT comprobante, usuario_id, monto FROM pagos WHERE id = ?');
        $stmtP->execute([(int)$_POST['id']]);
        $pago = $stmtP->fetch();
        if ($pago) {
            eliminar_archivo($pago['comprobante']);
            $pdo->prepare('DELETE FROM pagos WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('Pago eliminado.', 'warning');
        }
        header('Location: pagos.php');
        exit;
    }

    /* ---- Productos: guardar / eliminar ---- */
    if ($accion === 'guardar_producto') {
        $id = (int)($_POST['id'] ?? 0);
        $tituloP = trim($_POST['titulo'] ?? '');
        if ($tituloP === '') {
            flash('El título del producto es obligatorio.', 'danger');
            header('Location: pagos.php');
            exit;
        }
        $slugP = slugify($tituloP) ?: ('producto-' . date('YmdHis'));
        $descripcionP = trim($_POST['descripcion'] ?? '');
        $precioP = (float)($_POST['precio'] ?? 0);
        $categoriaP = trim($_POST['categoria'] ?? '');
        $stockP = (int)($_POST['stock'] ?? 0);
        $activoP = isset($_POST['activo']) ? 1 : 0;
        $imagenP = null;

        if ($id > 0) {
            $stmtOld = $pdo->prepare('SELECT imagen FROM productos WHERE id = ?');
            $stmtOld->execute([$id]);
            $old = $stmtOld->fetch();
            if ($old) $imagenP = $old['imagen'];
        }

        if (!empty($_FILES['imagen']['name'])) {
            $res = subir_archivo('imagen', 'productos', ['jpg', 'jpeg', 'png', 'webp'], 4);
            if ($res['ok']) {
                if ($imagenP) eliminar_archivo($imagenP);
                $imagenP = $res['archivo'];
            } else {
                flash('Error con la imagen: ' . $res['error'], 'danger');
                header('Location: pagos.php');
                exit;
            }
        }

        if ($id > 0) {
            $pdo->prepare('UPDATE productos SET titulo=?, slug=?, descripcion=?, precio=?, imagen=?, categoria=?, stock=?, activo=? WHERE id=?')
                ->execute([$tituloP, $slugP, $descripcionP, $precioP, $imagenP, $categoriaP, $stockP, $activoP, $id]);
            flash('Producto actualizado.');
        } else {
            $pdo->prepare('INSERT INTO productos (titulo, slug, descripcion, precio, imagen, categoria, stock, activo) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$tituloP, $slugP, $descripcionP, $precioP, $imagenP, $categoriaP, $stockP, $activoP]);
            flash('Producto creado.');
        }
        header('Location: pagos.php');
        exit;
    }

    if ($accion === 'eliminar_producto' && isset($_POST['id'])) {
        $stmtP = $pdo->prepare('SELECT imagen FROM productos WHERE id = ?');
        $stmtP->execute([(int)$_POST['id']]);
        $prod = $stmtP->fetch();
        if ($prod) eliminar_archivo($prod['imagen']);
        $pdo->prepare('DELETE FROM carrito WHERE producto_id = ?')->execute([(int)$_POST['id']]);
        $pdo->prepare('DELETE FROM productos WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Producto eliminado.', 'warning');
        header('Location: pagos.php');
        exit;
    }
}

/* ---------- Datos ---------- */
$metodos = $pdo->query('SELECT * FROM metodos_pago ORDER BY id ASC')->fetchAll();
$productos = $pdo->query('SELECT * FROM productos ORDER BY id DESC')->fetchAll();
$pagos = $pdo->query('SELECT p.*, mp.nombre AS metodo_nombre, u.nombre AS cliente_nombre, u.email AS cliente_email,
                             s.tipo_servicio,
                             pi.id AS proyecto_id, pi.total_proyecto, pi.pagado_total, pi.saldo_restante, pi.estado AS proyecto_estado
                      FROM pagos p
                      LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
                      LEFT JOIN usuarios u ON p.usuario_id = u.id
                      LEFT JOIN solicitudes s ON p.solicitud_id = s.id
                      LEFT JOIN proyectos_inicio pi ON pi.id = p.proyecto_id
                      ORDER BY p.creado_en DESC')->fetchAll();

$filtroEstado = $_GET['estado'] ?? '';
if (!in_array($filtroEstado, ['pendiente', 'aprobado', 'rechazado'], true)) {
    $filtroEstado = '';
}
$filtroCli = (int)($_GET['cliente'] ?? 0);
$filtroFecha = $_GET['fecha'] ?? '';
$pagosFiltrados = $pagos;
if ($filtroEstado !== '') {
    $pagosFiltrados = array_filter($pagosFiltrados, fn($p) => $p['estado'] === $filtroEstado);
}
if ($filtroCli > 0) {
    $pagosFiltrados = array_filter($pagosFiltrados, fn($p) => (int)$p['usuario_id'] === $filtroCli);
}
if ($filtroFecha !== '') {
    $pagosFiltrados = array_filter($pagosFiltrados, fn($p) => str_starts_with($p['creado_en'], $filtroFecha));
}

$clientes = $pdo->query("SELECT DISTINCT u.id, u.nombre, u.email FROM pagos p JOIN usuarios u ON p.usuario_id = u.id ORDER BY u.nombre ASC")->fetchAll();

$estadoBadge = [
    'pendiente' => 'bg-warning text-dark',
    'aprobado'  => 'bg-success',
    'rechazado' => 'bg-danger',
];
?>
<ul class="nav nav-tabs gap-1 mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= !isset($_GET['tab']) ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabPagos" type="button" role="tab">Registro de pagos</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= ($_GET['tab'] ?? '') === 'metodos' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabMetodos" type="button" role="tab">Métodos de pago</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= ($_GET['tab'] ?? '') === 'productos' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabProductos" type="button" role="tab">Productos</button>
    </li>
</ul>

<div class="tab-content">
    <!-- ===================== REGISTRO DE PAGOS ===================== -->
    <div class="tab-pane fade<?= !isset($_GET['tab']) ? ' show active' : '' ?>" id="tabPagos" role="tabpanel">
        <form method="GET" action="pagos.php" class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3 d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label small fw-semibold mb-1">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="aprobado" <?= $filtroEstado === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                        <option value="rechazado" <?= $filtroEstado === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-semibold mb-1">Cliente</label>
                    <select name="cliente" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $filtroCli === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-semibold mb-1">Fecha</label>
                    <input type="date" name="fecha" class="form-control form-control-sm" value="<?= e($filtroFecha) ?>">
                </div>
                <button class="btn btn-sm btn-fv"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="pagos.php" class="btn btn-sm btn-outline-fv"><i class="bi bi-x-lg me-1"></i>Limpiar</a>
            </div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover tabla-admin mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Fecha</th><th>Cliente</th><th>Concepto</th><th>Método</th><th>Tipo</th><th>Monto</th><th>Estado</th><th>Comprobante</th><th class="text-end pe-3">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$pagosFiltrados): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No hay pagos en esta vista.</td></tr>
                        <?php else: foreach ($pagosFiltrados as $p): ?>
                            <tr>
                                <td class="ps-3 small text-muted"><?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?></td>
                                <td>
                                    <b><?= e($p['cliente_nombre'] ?: 'Cliente #' . (int)$p['usuario_id']) ?></b>
                                    <br><small class="text-muted"><?= e($p['cliente_email'] ?: '—') ?></small>
                                </td>
                                <td class="small"><?= e($p['tipo_servicio'] ?: 'Compra de productos') ?></td>
                                <td class="small"><?= e($p['metodo_nombre'] ?: '—') ?></td>
                                <td class="small"><span class="text-capitalize"><?= e($p['tipo_pago']) ?></span></td>
                                <td class="fw-semibold">$<?= number_format((float)$p['monto'], 0) ?> MXN</td>
                                <td>
                                    <span class="badge badge-estado text-uppercase text-bg-<?= $p['estado'] === 'aprobado' ? 'success' : ($p['estado'] === 'rechazado' ? 'danger' : 'warning') ?>" style="color:#fff;" data-estado-pago="<?= (int)$p['id'] ?>">
                                        <?= e($p['estado']) ?>
                                    </span>
                                    <?php if ($p['notas']): ?>
                                        <br><small class="text-muted" data-nota-pago="<?= (int)$p['id'] ?>"><?= e(mb_strimwidth($p['notas'], 0, 40, '…')) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted d-none" data-nota-pago="<?= (int)$p['id'] ?>"></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['comprobante']): ?>
                                        <a href="../<?= e($p['comprobante']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver comprobante"><i class="bi bi-file-earmark-arrow-down"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted small" data-comprobante="<?= (int)$p['id'] ?>">—</span>
                                    <?php endif; ?>
                                    <span data-recibo-pago="<?= (int)$p['id'] ?>">
                                        <?php if ($p['estado'] === 'aprobado'): ?>
                                            <a href="../portal/recibo.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Recibo / factura"><i class="bi bi-receipt"></i></a>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detallePago<?= (int)$p['id'] ?>" title="Gestionar"><i class="bi bi-gear"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== MÉTODOS DE PAGO ===================== -->
    <div class="tab-pane fade<?= ($_GET['tab'] ?? '') === 'metodos' ? ' show active' : '' ?>" id="tabMetodos" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">Los métodos activos son visibles para tus clientes al momento de pagar.</p>
            <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalMetodo" onclick="limpiarMetodo(false)"><i class="bi bi-plus-lg me-1"></i>Nuevo método</button>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover tabla-admin mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Método</th><th>Detalles de cuenta</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metodos as $m): ?>
                            <tr>
                                <td class="ps-3"><i class="bi <?= e($m['icono'] ?: 'bi-credit-card') ?> me-2 text-primary"></i><b><?= e($m['nombre']) ?></b>
                                    <?php if ($m['descripcion']): ?><br><small class="text-muted"><?= e(mb_strimwidth($m['descripcion'], 0, 70, '…')) ?></small><?php endif; ?>
                                </td>
                                <td class="small"><?= e($m['detalles_cuenta'] ?: '—') ?></td>
                                <td>
                                    <?php if ($m['activo']): ?>
                                        <span class="badge badge-estado text-bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-estado text-bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarMetodo(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                                    <?php if ($m['activo']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Desactivar este método de pago?')">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="accion" value="eliminar_metodo">
                                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Desactivar"><i class="bi bi-eye-slash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="accion" value="activar_metodo">
                                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                            <button class="btn btn-sm btn-outline-success" title="Activar"><i class="bi bi-eye"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== PRODUCTOS ===================== -->
    <div class="tab-pane fade<?= ($_GET['tab'] ?? '') === 'productos' ? ' show active' : '' ?>" id="tabProductos" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">Productos que tus clientes pueden agregar al carrito y pagar.</p>
            <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="limpiarProducto(false)"><i class="bi bi-plus-lg me-1"></i>Nuevo producto</button>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover tabla-admin mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $pr): ?>
                            <tr>
                                <td class="ps-3">
                                    <?php if ($pr['imagen'] && file_exists(__DIR__ . '/../' . $pr['imagen'])): ?>
                                        <img src="../<?= e($pr['imagen']) ?>" class="miniatura me-2" alt="">
                                    <?php else: ?>
                                        <span class="miniatura d-inline-flex align-items-center justify-content-center me-2"><i class="bi bi-box-seam text-primary"></i></span>
                                    <?php endif; ?>
                                    <b><?= e($pr['titulo']) ?></b>
                                </td>
                                <td class="small"><?= e($pr['categoria'] ?: '—') ?></td>
                                <td class="fw-semibold">$<?= number_format((float)$pr['precio'], 0) ?></td>
                                <td><?= (int)$pr['stock'] > 0 ? (int)$pr['stock'] : '<span class="badge text-bg-warning">Sin stock</span>' ?></td>
                                <td><?= $pr['activo'] ? '<span class="badge badge-estado text-bg-success">Activo</span>' : '<span class="badge badge-estado text-bg-secondary">Oculto</span>' ?></td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarProducto(<?= htmlspecialchars(json_encode($pr), ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este producto?')">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar_producto">
                                        <input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal: método de pago ===== -->
<div class="modal fade" id="modalMetodo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="pagos.php">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar_metodo">
                <input type="hidden" name="id" id="m_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloMetodo">Nuevo método de pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Nombre *</label>
                        <input type="text" name="nombre" id="m_nombre" class="form-control" required placeholder="Transferencia bancaria">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Icono (Bootstrap Icons)</label>
                        <input type="text" name="icono" id="m_icono" class="form-control" value="bi-credit-card" placeholder="bi-bank">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Descripción</label>
                        <textarea name="descripcion" id="m_descripcion" class="form-control" rows="2" placeholder="Pago rápido y seguro a través de..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Detalles de cuenta / CLABE</label>
                        <input type="text" name="detalles_cuenta" id="m_detalles" class="form-control" placeholder="CLABE: ... Cuenta: ... Banco: ...">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Instrucciones</label>
                        <textarea name="instrucciones" id="m_instrucciones" class="form-control" rows="3" placeholder="Pasos para completar el pago..."></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="m_activo" value="1" checked>
                            <label class="form-check-label small" for="m_activo">Visible para los clientes</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fv">Guardar método</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Modal: producto ===== -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="pagos.php" enctype="multipart/form-data">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar_producto">
                <input type="hidden" name="id" id="p_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloProducto">Nuevo producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Título *</label>
                        <input type="text" name="titulo" id="p_titulo" class="form-control" required placeholder="Plantilla web premium">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Precio (MXN) *</label>
                        <input type="number" name="precio" id="p_precio" class="form-control" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Categoría</label>
                        <input type="text" name="categoria" id="p_categoria" class="form-control" placeholder="Plantillas">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Stock</label>
                        <input type="number" name="stock" id="p_stock" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-3 d-flex align-items-end pb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="p_activo" value="1" checked>
                            <label class="form-check-label small" for="p_activo">Visible</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Descripción</label>
                        <textarea name="descripcion" id="p_descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Imagen del producto</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                        <small class="text-muted">JPG, PNG o WEBP hasta 4 MB.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fv">Guardar producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($pagosFiltrados as $p): ?>
    <!-- Modal detalle / gestión de pago -->
    <div class="modal fade" id="detallePago<?= (int)$p['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-credit-card me-2 text-primary"></i>Pago #<?= (int)$p['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 small mb-3">
                        <div class="col-6"><span class="text-muted d-block">Cliente</span><b><?= e($p['cliente_nombre'] ?: '—') ?></b></div>
                        <div class="col-6"><span class="text-muted d-block">Concepto</span><b><?= e($p['tipo_servicio'] ?: 'Productos') ?></b></div>
                        <div class="col-6"><span class="text-muted d-block">Método</span><b><?= e($p['metodo_nombre'] ?: '—') ?></b></div>
                        <div class="col-6"><span class="text-muted d-block">Monto</span><b class="text-primary">$<?= number_format((float)$p['monto'], 0) ?> MXN</b></div>
                        <div class="col-6"><span class="text-muted d-block">Tipo</span><b class="text-capitalize"><?= e($p['tipo_pago']) ?></b></div>
                        <div class="col-6"><span class="text-muted d-block">Fecha</span><b><?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?></b></div>
                        <?php if ($p['comprobante']): ?>
                            <div class="col-12">
                                <span class="text-muted d-block">Comprobante</span>
                                <a href="../<?= e($p['comprobante']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1"><i class="bi bi-file-earmark-arrow-down me-1"></i>Ver comprobante</a>
                            </div>
                        <?php endif; ?>
                        <div class="col-12"><span class="text-muted d-block">Notas</span><p class="mb-0"><?= e($p['notas'] ?: '—') ?></p></div>
                    </div>

                    <?php if (!empty($p['proyecto_id'])): ?>
                        <div class="border rounded bg-light p-2 small mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <b><i class="bi bi-code-slash me-1 text-primary"></i>Proyecto #<?= (int)$p['proyecto_id'] ?></b>
                                <?php if (($p['proyecto_estado'] ?? '') === 'liquidado'): ?>
                                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Liquidado</span>
                                <?php endif; ?>
                            </div>
                            <div class="row g-1 text-muted">
                                <div class="col-4">Total: <b>$<?= number_format((float)$p['total_proyecto'], 0) ?> MXN</b></div>
                                <div class="col-4">Pagado: <b class="text-success">$<?= number_format((float)$p['pagado_total'], 0) ?> MXN</b></div>
                                <div class="col-4">Saldo: <b class="text-danger">$<?= number_format((float)$p['saldo_restante'], 0) ?> MXN</b></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="border-top pt-3 js-ajax">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="estado_pago">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <label class="form-label small fw-semibold">Cambiar estado</label>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select name="estado" id="selPagoEstado-<?= (int)$p['id'] ?>" class="form-select form-select-sm" style="max-width:180px;">
                                <option value="pendiente" <?= $p['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="aprobado" <?= $p['estado'] === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                                <option value="rechazado" <?= $p['estado'] === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                            </select>
                            <input type="text" name="notas" id="inpPagoNotas-<?= (int)$p['id'] ?>" class="form-control form-control-sm flex-grow-1" placeholder="Nota (opcional)" value="<?= e($p['notas']) ?>">
                            <button class="btn btn-sm btn-fv"><i class="bi bi-check-lg me-1"></i>Actualizar</button>
                        </div>
                    </form>

                    <form method="POST" enctype="multipart/form-data" class="border-top pt-3 mt-3 js-ajax">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="adjuntar_comprobante">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <label class="form-label small fw-semibold">Adjuntar comprobante de pago</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="file" name="comprobante" class="form-control form-control-sm" accept="image/*,.pdf" required>
                            <button class="btn btn-sm btn-outline-primary flex-shrink-0"><i class="bi bi-paperclip me-1"></i>Cargar</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <form method="POST" onsubmit="return confirm('¿Eliminar este pago?')">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="eliminar_pago">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.addEventListener('fv:ajaxok', function (e) {
    var d = (e && e.detail) || {};
    if (d.accion === 'estado_pago') {
        var id = d.pago_id;
        var badge = document.querySelector('[data-estado-pago="' + id + '"]');
        if (badge) {
            badge.textContent = d.estado_pago || badge.textContent;
            badge.className = 'badge badge-estado text-uppercase text-bg-' + (d.estado_pago === 'aprobado' ? 'success' : (d.estado_pago === 'rechazado' ? 'danger' : 'warning'));
        }
        var nota = document.querySelector('[data-nota-pago="' + id + '"]');
        if (nota) {
            if (d.nota) {
                nota.textContent = d.nota.slice(0, 40) + (d.nota.length > 40 ? '…' : '');
                nota.classList.remove('d-none');
            } else {
                nota.textContent = '';
                nota.classList.add('d-none');
            }
        }
        var sel = document.getElementById('selPagoEstado-' + id);
        if (sel) sel.value = d.estado_pago;
        var inp = document.getElementById('inpPagoNotas-' + id);
        if (inp) inp.value = d.nota || '';
        var rec = document.querySelector('[data-recibo-pago="' + id + '"]');
        if (rec) {
            rec.innerHTML = d.estado_pago === 'aprobado'
                ? '<a href="../portal/recibo.php?id=' + id + '" target="_blank" class="btn btn-sm btn-outline-success" title="Recibo / factura"><i class="bi bi-receipt"></i></a>'
                : '';
        }
    }
    if (d.accion === 'comprobante_adjunto') {
        var celda = document.querySelector('[data-comprobante="' + d.pago_id + '"]');
        if (celda) {
            celda.innerHTML = '<a href="../' + d.comprobante + '" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver comprobante"><i class="bi bi-file-earmark-arrow-down"></i></a>';
        }
    }
});
function limpiarMetodo(mostrar) {
    document.getElementById('m_id').value = 0;
    document.getElementById('m_nombre').value = '';
    document.getElementById('m_icono').value = 'bi-credit-card';
    document.getElementById('m_descripcion').value = '';
    document.getElementById('m_detalles').value = '';
    document.getElementById('m_instrucciones').value = '';
    document.getElementById('m_activo').checked = true;
    document.getElementById('tituloMetodo').textContent = 'Nuevo método de pago';
}
function editarMetodo(m) {
    document.getElementById('m_id').value = m.id;
    document.getElementById('m_nombre').value = m.nombre;
    document.getElementById('m_icono').value = m.icono;
    document.getElementById('m_descripcion').value = m.descripcion || '';
    document.getElementById('m_detalles').value = m.detalles_cuenta || '';
    document.getElementById('m_instrucciones').value = m.instrucciones || '';
    document.getElementById('m_activo').checked = m.activo == 1;
    document.getElementById('tituloMetodo').textContent = 'Editar método de pago';
    new bootstrap.Modal(document.getElementById('modalMetodo')).show();
}
function limpiarProducto(mostrar) {
    document.getElementById('p_id').value = 0;
    document.getElementById('p_titulo').value = '';
    document.getElementById('p_precio').value = '';
    document.getElementById('p_categoria').value = '';
    document.getElementById('p_stock').value = 0;
    document.getElementById('p_descripcion').value = '';
    document.getElementById('p_activo').checked = true;
    document.getElementById('tituloProducto').textContent = 'Nuevo producto';
}
function editarProducto(pr) {
    document.getElementById('p_id').value = pr.id;
    document.getElementById('p_titulo').value = pr.titulo;
    document.getElementById('p_precio').value = pr.precio;
    document.getElementById('p_categoria').value = pr.categoria || '';
    document.getElementById('p_stock').value = pr.stock || 0;
    document.getElementById('p_descripcion').value = pr.descripcion || '';
    document.getElementById('p_activo').checked = pr.activo == 1;
    document.getElementById('tituloProducto').textContent = 'Editar producto';
    new bootstrap.Modal(document.getElementById('modalProducto')).show();
}
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>