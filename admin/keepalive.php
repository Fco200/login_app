<?php
/* Mantiene viva la sesión del admin mientras está en el panel.
   El simple hecho de llamar a funciones.php actualiza _ultimo_acceso. */
require_once __DIR__ . '/../funciones.php';
requiere_admin();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'restante' => sesion_restante_seg()]);
exit;