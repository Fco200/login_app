<?php
/* El portal de clientes ahora vive en la carpeta /portal.
   Esta página se mantiene solo por compatibilidad con enlaces antiguos. */
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();
if (!esta_logueado()) {
    header('Location: iniciar-sesion.php');
    exit;
}
header('Location: portal/index.php');
exit;