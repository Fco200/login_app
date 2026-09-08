<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'conexion.php';

$mensaje = '';
$tipoMensaje = '';

// Procesar alta de nuevo vehículo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $vin         = strtoupper(trim($_POST['vin']));
    $marca       = trim($_POST['marca']);
    $modelo      = trim($_POST['modelo']);
    $anio        = (int)$_POST['anio'];
    $color       = trim($_POST['color']);
    $kilometraje = (int)$_POST['kilometraje'];
    $precio      = (float)$_POST['precio'];
    $estado      = $_POST['estado'];

    try {
        $stmt = $pdo->prepare("INSERT INTO vehiculos (vin, marca, modelo, anio, color, kilometraje, precio, estado, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$vin, $marca, $modelo, $anio, $color, $kilometraje, $precio, $estado, $_SESSION['usuario_id']]);
        $mensaje = "Vehículo agregado al inventario exitosamente.";
        $tipoMensaje = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al registrar vehículo (verifica si el VIN está duplicado).";
        $tipoMensaje = "danger";
    }
}

// Búsqueda y filtrado
$busqueda = trim($_GET['q'] ?? '');
if ($busqueda !== '') {
    $sql = "SELECT * FROM vehiculos WHERE vin LIKE ? OR marca LIKE ? OR modelo LIKE ? ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $term = "%$busqueda%";
    $stmt->execute([$term, $term, $term]);
    $vehiculos = $stmt->fetchAll();
} else {
    $vehiculos = $pdo->query("SELECT * FROM vehiculos ORDER BY id DESC")->fetchAll();
}

// Métricas de inventario
$totalAutos = count($vehiculos);
$disponibles = 0;
$valorTotal = 0;
foreach ($vehiculos as $v) {
    if ($v['estado'] === 'Disponible') $disponibles++;
    $valorTotal += $v['precio'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario Concesionaria - AutoPortal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<!-- Barra de navegación -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-car-front-fill me-2 text-primary"></i>AutoPortal
        </a>
        <div class="d-flex align-items-center text-white">
            <span class="me-3 small">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($_SESSION['nombre']) ?> 
                <span class="badge bg-primary text-uppercase ms-1"><?= htmlspecialchars($_SESSION['rol']) ?></span>
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> Salir
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Tarjetas de Métricas -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary p-3 rounded-3 me-3">
                        <i class="bi bi-truck-flatbed fs-3"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Total en Concesionaria</span>
                        <h4 class="mb-0 fw-bold"><?= $totalAutos ?> unidades</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="bg-success-subtle text-success p-3 rounded-3 me-3">
                        <i class="bi bi-check-circle fs-3"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Disponibles para Venta</span>
                        <h4 class="mb-0 fw-bold"><?= $disponibles ?> unidades</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="bg-warning-subtle text-warning-emphasis p-3 rounded-3 me-3">
                        <i class="bi bi-cash-stack fs-3"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Valor de Inventario</span>
                        <h4 class="mb-0 fw-bold">$<?= number_format($valorTotal, 2) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de acciones y búsqueda -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <form class="d-flex gap-2 w-100 w-md-50" method="GET" action="dashboard.php">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Buscar por marca, modelo o VIN..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <button type="submit" class="btn btn-secondary">Buscar</button>
                <?php if ($busqueda !== ''): ?>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Limpiar</a>
                <?php endif; ?>
            </form>

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
                <i class="bi bi-plus-circle me-1"></i> Nuevo Vehículo
            </button>
        </div>
    </div>

    <!-- Tabla de vehículos -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">VIN / Serie</th>
                            <th>Vehículo</th>
                            <th>Año</th>
                            <th>Color</th>
                            <th>Kilometraje</th>
                            <th>Precio (MXN)</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vehiculos) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No se encontraron vehículos en el inventario.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vehiculos as $auto): ?>
                                <tr>
                                    <td class="ps-3 fw-mono text-muted small"><?= htmlspecialchars($auto['vin']) ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($auto['marca']) ?> <?= htmlspecialchars($auto['modelo']) ?></td>
                                    <td><?= htmlspecialchars($auto['anio']) ?></td>
                                    <td><?= htmlspecialchars($auto['color']) ?></td>
                                    <td><?= number_format($auto['kilometraje']) ?> km</td>
                                    <td class="text-success fw-bold">$<?= number_format($auto['precio'], 2) ?></td>
                                    <td>
                                        <?php 
                                            $badge = match($auto['estado']) {
                                                'Disponible' => 'bg-success',
                                                'Reservado' => 'bg-warning text-dark',
                                                'Vendido' => 'bg-secondary',
                                                default => 'bg-light text-dark'
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?>"><?= $auto['estado'] ?></span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="editar.php?id=<?= $auto['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($_SESSION['rol'] === 'admin'): ?>
                                            <a href="eliminar.php?id=<?= $auto['id'] ?>" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('¿Seguro que deseas dar de baja este vehículo?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear Vehículo -->
<div class="modal fade" id="modalCrear" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="dashboard.php" method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Dar de Alta Vehículo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Número de Serie (VIN)</label>
                        <input type="text" name="vin" class="form-control" required placeholder="Ej. 3VW1K7AJ8EM123456">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Marca</label>
                        <input type="text" name="marca" class="form-control" required placeholder="Ej. Volkswagen">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Modelo</label>
                        <input type="text" name="modelo" class="form-control" required placeholder="Ej. Jetta">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Año</label>
                        <input type="number" name="anio" class="form-control" min="1990" max="2027" value="2023" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Color</label>
                        <input type="text" name="color" class="form-control" required placeholder="Ej. Rojo">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Kilometraje (km)</label>
                        <input type="number" name="kilometraje" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Precio (MXN)</label>
                        <input type="number" step="0.01" name="precio" class="form-control" required placeholder="350000.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Estado Inicial</label>
                        <select name="estado" class="form-select">
                            <option value="Disponible">Disponible</option>
                            <option value="Reservado">Reservado</option>
                            <option value="Vendido">Vendido</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar en Inventario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>