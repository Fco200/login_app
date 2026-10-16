<?php
$titulo = 'Productos';
$subtitulo = 'Administra los productos que tus clientes pueden comprar en el carrito';
$seccionAdmin = 'productos.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $tituloP = trim($_POST['titulo'] ?? '');
        if ($tituloP === '') {
            flash('El título del producto es obligatorio.', 'danger');
            header('Location: productos.php');
            exit;
        }
        $slugP = slugify($tituloP) ?: ('producto-' . date('YmdHis'));
        $descripcionP = trim($_POST['descripcion'] ?? '');
        $precioP = (float)($_POST['precio'] ?? 0);
        $categoriaP = trim($_POST['categoria'] ?? '');
        $referenciaP = trim($_POST['referencia'] ?? '');
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
                header('Location: productos.php');
                exit;
            }
        }

        if ($id > 0) {
            $pdo->prepare('UPDATE productos SET titulo=?, slug=?, descripcion=?, precio=?, imagen=?, categoria=?, referencia=?, stock=?, activo=? WHERE id=?')
                ->execute([$tituloP, $slugP, $descripcionP, $precioP, $imagenP, $categoriaP, $referenciaP !== '' ? $referenciaP : null, $stockP, $activoP, $id]);
            flash('Producto actualizado correctamente.');
        } else {
            $pdo->prepare('INSERT INTO productos (titulo, slug, descripcion, precio, imagen, categoria, referencia, stock, activo) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$tituloP, $slugP, $descripcionP, $precioP, $imagenP, $categoriaP, $referenciaP !== '' ? $referenciaP : null, $stockP, $activoP]);
            flash('Producto creado correctamente.');
        }
        header('Location: productos.php');
        exit;
    }

    if ($accion === 'eliminar' && isset($_POST['id'])) {
        $stmtP = $pdo->prepare('SELECT imagen FROM productos WHERE id = ?');
        $stmtP->execute([(int)$_POST['id']]);
        $prod = $stmtP->fetch();
        if ($prod) eliminar_archivo($prod['imagen']);
        $pdo->prepare('DELETE FROM carrito WHERE producto_id = ?')->execute([(int)$_POST['id']]);
        $pdo->prepare('DELETE FROM productos WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Producto eliminado.', 'warning');
        header('Location: productos.php');
        exit;
    }
}

$productos = $pdo->query('SELECT * FROM productos ORDER BY id DESC')->fetchAll();
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="text-muted mb-0">Total: <b><?= count($productos) ?></b> productos</p>
    </div>
    <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="limpiarProducto(false)"><i class="bi bi-plus-lg me-1"></i>Nuevo producto</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Producto</th><th>Categoría</th><th>Referencia</th><th>Precio</th><th>Stock</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (!$productos): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-box-seam me-1"></i>Aún no hay productos.</td></tr>
                <?php endif; ?>
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
                        <td class="small font-monospace"><?= e($pr['referencia'] ?: '—') ?></td>
                        <td class="fw-semibold">$<?= number_format((float)$pr['precio'], 0) ?></td>
                        <td><?= (int)$pr['stock'] > 0 ? (int)$pr['stock'] : '<span class="badge text-bg-warning">Sin stock</span>' ?></td>
                        <td><?= $pr['activo'] ? '<span class="badge badge-estado text-bg-success">Activo</span>' : '<span class="badge badge-estado text-bg-secondary">Oculto</span>' ?></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarProducto(<?= htmlspecialchars(json_encode($pr), ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este producto?')">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
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

<!-- Modal -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="productos.php" enctype="multipart/form-data">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar">
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
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Referencia / SKU</label>
                        <input type="text" name="referencia" id="p_referencia" class="form-control font-monospace" placeholder="PLANT-001">
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
                        <small class="text-muted">JPG, PNG o WEBP hasta 4 MB. Déjalo vacío para conservar la actual.</small>
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

<?php if ($editar): ?>
<script>
    window.addEventListener('DOMContentLoaded', function () {
        editarProducto(<?= htmlspecialchars(json_encode($editar), ENT_QUOTES) ?>);
    });
</script>
<?php endif; ?>

<script>
function limpiarProducto() {
    document.getElementById('p_id').value = 0;
    document.getElementById('p_titulo').value = '';
    document.getElementById('p_precio').value = '';
    document.getElementById('p_categoria').value = '';
    document.getElementById('p_referencia').value = '';
    document.getElementById('p_stock').value = '0';
    document.getElementById('p_descripcion').value = '';
    document.getElementById('p_activo').checked = true;
    document.getElementById('tituloProducto').textContent = 'Nuevo producto';
}
function editarProducto(p) {
    document.getElementById('p_id').value = p.id;
    document.getElementById('p_titulo').value = p.titulo;
    document.getElementById('p_precio').value = p.precio;
    document.getElementById('p_categoria').value = p.categoria || '';
    document.getElementById('p_referencia').value = p.referencia || '';
    document.getElementById('p_stock').value = p.stock || 0;
    document.getElementById('p_descripcion').value = p.descripcion || '';
    document.getElementById('p_activo').checked = p.activo == 1;
    document.getElementById('tituloProducto').textContent = 'Editar producto';
    new bootstrap.Modal(document.getElementById('modalProducto')).show();
}
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
