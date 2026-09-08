<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'conexion.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM vehiculos WHERE id = ?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marca       = trim($_POST['marca']);
    $modelo      = trim($_POST['modelo']);
    $anio        = (int)$_POST['anio'];
    $color       = trim($_POST['color']);
    $kilometraje = (int)$_POST['kilometraje'];
    $precio      = (float)$_POST['precio'];
    $estado      = $_POST['estado'];

    try {
        $update = $pdo->prepare("UPDATE vehiculos SET marca = ?, modelo = ?, anio = ?, color = ?, kilometraje = ?, precio = ?, estado = ? WHERE id = ?");
        $update->execute([$marca, $modelo, $anio, $color, $kilometraje, $precio, $estado, $id]);
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Ocurrió un error al actualizar los datos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Vehículo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width: 650px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-3">Editar Vehículo (VIN: <?= htmlspecialchars($auto['vin']) ?>)</h4>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Marca</label>
                        <input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($auto['marca']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Modelo</label>
                        <input type="text" name="modelo" class="form-control" value="<?= htmlspecialchars($auto['modelo']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Año</label>
                        <input type="number" name="anio" class="form-control" value="<?= $auto['anio'] ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Color</label>
                        <input type="text" name="color" class="form-control" value="<?= htmlspecialchars($auto['color']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Kilometraje</label>
                        <input type="number" name="kilometraje" class="form-control" value="<?= $auto['kilometraje'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Precio (MXN)</label>
                        <input type="number" step="0.01" name="precio" class="form-control" value="<?= $auto['precio'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="Disponible" <?= $auto['estado'] === 'Disponible' ? 'selected' : '' ?>>Disponible</option>
                            <option value="Reservado" <?= $auto['estado'] === 'Reservado' ? 'selected' : '' ?>>Reservado</option>
                            <option value="Vendido" <?= $auto['estado'] === 'Vendido' ? 'selected' : '' ?>>Vendido</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="dashboard.php" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>