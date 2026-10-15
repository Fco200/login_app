<?php
/* Mantiene viva la sesión del cliente mientras navega el portal.
   El simple hecho de llamar a funciones.php actualiza _ultimo_acceso, así que
   el ping periódico evita que la sesión expire por inactividad. */
require_once __DIR__ . '/../funciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!esta_logueado()) {
    echo json_encode(['ok' => false, 'sesion' => false]);
    exit;
}

echo json_encode(['ok' => true, 'sesion' => true, 'restante' => sesion_restante_seg()]);
exit;
