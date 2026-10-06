<?php
/* ============================================================
   FV DIGITAL - Soluciones Digitales y Desarrollo
   Configuración general del sitio (valores por defecto).
   Los valores editables desde el panel se guardan en la tabla
   `datos_sitio` y sobreescriben a estos al renderizar.
   ============================================================ */

date_default_timezone_set('America/Hermosillo');

define('SITE_NOMBRE', 'FV DIGITAL');
define('SITE_ESLOGAN', 'Soluciones Digitales y Desarrollo');
define('SITE_TELEFONO', '662 537 4491');
define('SITE_WHATSAPP', '526625374491');
define('SITE_EMAIL', 'contacto@fvdigital.com');
define('SITE_DIRECCION', 'Hermosillo, Sonora, México');
define('SITE_HORARIO', 'Lun a Vie 9:00 - 18:00');
define('SITE_FACEBOOK', '');
define('SITE_INSTAGRAM', '');
define('SITE_TIKTOK', '');
define('SITE_HERO_TITULO', 'Impulsamos tu negocio con soluciones digitales a la medida');
define('SITE_HERO_SUBTITULO', 'Desarrollo web, diseño y branding, apps, plantillas y soporte técnico para que tu marca destaque.');
define('SITE_HERO_CTA', 'Cotiza tu proyecto');
define('SITE_ACERCA', 'FV Digital es una marca dedicada a la creación de soluciones digitales y desarrollo de software. Ayudamos a negocios, emprendedores y marcas a crecer con tecnología, diseño y estrategia.');
define('SITE_FOOTER', 'TV Digital — Soluciones Digitales y Desarrollo.');

define('UPLOADS_DIR', __DIR__ . '/assets/uploads');
define('MAX_ARCHIVO_MB', 8);
define('RUTA_ADMIN_LOGIN', 'login.php');
define('SESION_INACTIVIDAD_MIN', 10);