<?php
/* Descarga de entregables: valida que el proyecto pertenezca al cliente
   y que esté en desarrollo o completado. */
require_once __DIR__ . '/../funciones.php';

iniciar_sesion_segura();

$id = (int)($_GET['id'] ?? 0);
$stmt = $GLOBALS['pdo']->prepare('SELECT en.*, pi.usuario_id, pi.estado AS proyecto_estado
                                  FROM entregables en
                                  JOIN proyectos_inicio pi ON pi.id = en.proyecto_id
                                  WHERE en.id = ?');
$stmt->execute([$id]);
$ent = $stmt->fetch();

$esAdmin = esta_admin();
if (!$ent || (!$esAdmin && (int)$ent['usuario_id'] !== (int)($_SESSION['usuario_id'] ?? 0))) {
    http_response_code(404);
    echo 'Entregable no encontrado.';
    exit;
}

if (!$esAdmin && !in_array($ent['proyecto_estado'], ['en_desarrollo', 'completado'], true)) {
    redirigir('procesos.php');
}

$ruta = realpath(__DIR__ . '/../' . $ent['archivo']);
$raizUploads = realpath(UPLOADS_DIR);
if (!$ruta || !$raizUploads || !str_starts_with($ruta, $raizUploads) || !is_file($ruta)) {
    http_response_code(404);
    echo 'El archivo ya no existe en el servidor.';
    exit;
}

$ext = strtolower(pathinfo($ent['archivo'], PATHINFO_EXTENSION));
$tipos = [
    'pdf'   => 'application/pdf',
    'zip'   => 'application/zip',
    'rar'   => 'application/vnd.rar',
    '7z'    => 'application/x-7z-compressed',
    'doc'   => 'application/msword',
    'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'   => 'application/vnd.ms-excel',
    'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'   => 'application/vnd.ms-powerpoint',
    'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'png'   => 'image/png',
    'webp'  => 'image/webp',
    'txt'   => 'text/plain',
    'csv'   => 'text/csv',
    'sql'   => 'application/sql',
];
$tipoMime = $tipos[$ext] ?? 'application/octet-stream';

$nombreBase = trim($ent['titulo']) !== '' ? $ent['titulo'] : 'entregable-' . $id;
$nombreBase = preg_replace('/[^\pL\pN_\-\. ]/u', '_', $nombreBase);
$nombreDescarga = $nombreBase . '.' . $ext;

header('Content-Type: ' . $tipoMime);
header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
exit;