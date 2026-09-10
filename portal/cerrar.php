<?php
/* Cerrar sesión del portal de clientes */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();
session_unset();
session_destroy();
header('Location: index.php');
exit;