<?php
/* Cerrar sesion del sitio publico / clientes (no afecta la sesion admin) */
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();
unset($_SESSION['usuario_id'], $_SESSION['nombre'], $_SESSION['rol']);
header('Location: index.php');
exit;