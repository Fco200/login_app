<?php
/* ============================================================
   FV DIGITAL - Agregador de repositorios (MongoDB)
   ------------------------------------------------------------
   Basta con requerir este archivo para tener disponibles:
     · la conexión (conexion.php) y la configuración (config.php)
     · la capa DAL (includes/mongo.php)
     · todos los repositorios de dominio
   Las páginas siguen incluyendo unicamente `funciones.php`, que
   carga este archivo.

   El orden importa: primero la DAL, después los repositorios que
   la usan entre sí.
   ============================================================ */

require_once __DIR__ . '/mongo.php';

require_once __DIR__ . '/repos/crud.php';
require_once __DIR__ . '/repos/sitio.php';
require_once __DIR__ . '/repos/usuarios.php';
require_once __DIR__ . '/repos/catalogo.php';
require_once __DIR__ . '/repos/solicitudes.php';
require_once __DIR__ . '/repos/proyectos.php';
require_once __DIR__ . '/repos/proyectos_estado.php';
require_once __DIR__ . '/repos/pagos.php';
require_once __DIR__ . '/repos/facturacion.php';
require_once __DIR__ . '/repos/comunicacion.php';
require_once __DIR__ . '/repos/misc.php';