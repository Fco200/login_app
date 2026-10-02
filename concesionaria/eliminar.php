<?php
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

// Solo administradores pueden eliminar registros
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$id = trim((string)($_GET['id'] ?? ''));
if (oid($id) !== null) {
    veh_eliminar($id);
}

header('Location: dashboard.php');
exit;