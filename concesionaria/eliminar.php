<?php
session_start();
require_once '../conexion.php';

// Solo administradores pueden eliminar registros
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM vehiculos WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: dashboard.php');
exit;