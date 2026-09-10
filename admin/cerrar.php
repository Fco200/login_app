<?php
/* Cerrar sesion del panel (solo admin; no afecta la sesion de cliente) */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();
unset($_SESSION['admin_id'], $_SESSION['admin_nombre'], $_SESSION['admin_rol']);
header('Location: login.php');
exit;