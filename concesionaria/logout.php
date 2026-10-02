<?php
/* Cerrar sesión del portal de concesionaria */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();
unset($_SESSION['usuario_id'], $_SESSION['nombre'], $_SESSION['rol']);

header('Location: login.php');
exit;