<?php
/* FV DIGITAL - Carta de agradecimiento del proyecto entregado.
   Genera una carta PDF (jsPDF) y vista previa imprimible con los detalles
   de la entrega y un mensaje de agradecimiento. Puede abrirla el cliente
   desde su portal o el administrador desde el panel. */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

date_default_timezone_set('America/Hermosillo');
$proyectoId = (int)($_GET['proyecto'] ?? 0);

$esAdmin = esta_admin();
if (!$esAdmin && !esta_logueado()) {
    header('Location: ' . url_sitio('portal/login.php'));
    exit;
}
$back = $esAdmin && !esta_logueado() ? '../admin/procesos.php' : 'procesos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta de agradecimiento — <?= e(SITE_NOMBRE) ?></title>
    <base href="<?= e(url_sitio('portal/')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/estilos.css?v=20260910">
    <link rel="stylesheet" href="../assets/css/portal.css?v=20260910">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body class="portal-body">

<!-- Barra ligera (solo acciones, no menú) -->
<nav class="navbar navbar-light bg-white border-bottom sticky-top no-print">
    <div class="container d-flex justify-content-between align-items-center py-2">
        <a href="<?= e($back) ?>" class="btn btn-sm btn-outline-fv"><i class="bi bi-arrow-left me-1"></i>Volver a procesos</a>
        <span class="fw-bold d-none d-sm-inline"><?= e(SITE_NOMBRE) ?> · Carta</span>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-fv" href="carta_pdf.php?proyecto=<?= (int)$proyectoId ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Descargar PDF</a>
            <button class="btn btn-sm btn-outline-fv" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
        </div>
    </div>
</nav>

<main class="portal-main">
    <div class="container py-4" id="impresora">
        <div class="d-flex justify-content-between align-items-center mb-3 gap-2 no-print">
            <div>
                <h4 class="mb-0">Carta de agradecimiento</h4>
                <p class="text-muted mb-0 small">Un reconocimiento especial por la entrega de tu proyecto.</p>
            </div>
            <a href="<?= e($back) ?>" class="btn btn-outline-fv btn-sm"><i class="bi bi-arrow-left me-1"></i>Mis procesos</a>
        </div>

        <?php if ($proyectoId <= 0): ?>
            <div class="card portal-card border-0 shadow-sm p-5 text-center">
                <i class="bi bi-envelope-heart d-block fs-1 text-primary mb-3"></i>
                <h5>Falta el proyecto</h5>
                <p class="text-muted mb-0">Regresa a mis procesos y usa el botón "Carta de agradecimiento" de un proyecto entregado.</p>
            </div>
        <?php else: ?>
            <div class="card portal-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
                        <a class="btn btn-fv" href="carta_pdf.php?proyecto=<?= (int)$proyectoId ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Descargar carta (PDF)</a>
                        <button class="btn btn-outline-fv" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                    </div>

                    <div id="cartaPreview">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border text-primary mb-2" role="status"></div>
                            <div>Preparando tu carta…</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-fv-light small mt-3 mb-0 no-print">
                <i class="bi bi-person-heart me-1"></i>Gracias por confiar en <b><?= e(SITE_NOMBRE) ?></b>. Esta carta es nuestra forma de reconocer el éxito de tu proyecto y agradecerte por compartirlo con nosotros.
            </div>
        <?php endif; ?>
    </div>
</main>

<script>var SITE_NOMBRE = <?= json_encode(SITE_NOMBRE, JSON_UNESCAPED_UNICODE) ?>;</script>
<script>var SITE_LOGO = <?= json_encode(logo_sitio()) ?>;</script>
<script>
var PET = <?= json_encode(['proyecto_id' => $proyectoId], JSON_UNESCAPED_UNICODE) ?>;

function fmtMXN(n) {
    return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MXN';
}

var DATOS = null;

function cargarCarta() {
    if (PET.proyecto_id <= 0) return;
    fetch('carta_api.php?proyecto_id=' + PET.proyecto_id, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) {
                document.getElementById('cartaPreview').innerHTML =
                    '<div class="text-center text-muted py-5"><i class="bi bi-exclamation-triangle d-block fs-1 mb-2"></i>'
                    + esc(d.mensaje || 'No se pudo preparar la carta.') + '</div>';
                return;
            }
            DATOS = d;
            document.getElementById('cartaPreview').innerHTML = plantillaHTML(d);
        })
        .catch(function () {
            document.getElementById('cartaPreview').innerHTML =
                '<div class="text-center text-muted py-5"><i class="bi bi-exclamation-triangle d-block fs-1 mb-2"></i>No se pudo preparar la carta.</div>';
        });
}

function esc(t) {
    return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function plantillaHTML(d) {
    var pr = d.proyecto, cl = d.cliente;
    var entregables = (d.entregables || []);
    var lis = entregables.length
        ? entregables.map(function (e) { return '<li><b>' + esc(e.titulo) + '</b>' + (e.notas ? ' <span class="text-muted">(' + esc(e.notas) + ')</span>' : '') + '</li>'; }).join('')
        : '<li class="text-muted">Entregables incluidos en tu entrega.</li>';

    return '<div id="hojaCarta" class="carta-hoja">'
        + '<div class="carta-encabezado">' + (SITE_LOGO ? '<img src="' + SITE_LOGO + '" alt="" class="carta-logo">' : '') + '<b>' + SITE_NOMBRE + '</b><span>Portal de clientes</span></div>'
        + '<div class="carta-cuerpo">'
        + '<div class="carta-titulo">Carta de agradecimiento</div>'
        + '<div class="carta-fecha">Sonora, ' + new Date().toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) + '</div>'
        + '<p class="carta-saludo">Apreciado/a <b>' + esc(cl.nombre || 'Cliente') + '</b>:</p>'
        + '<p>' + esc(d.texto) + '</p>'
        + '<p>Tenemos el gusto de confirmar que tu proyecto fue <b>entregado y completado con éxito</b>. Estos son los detalles de tu entrega:</p>'
        + '<div class="carta-detalle">'
        + '<div><span>Proyecto:</span><b>' + esc(pr.servicio) + '</b></div>'
        + '<div><span>Folio:</span><b>' + esc(pr.folio) + '</b></div>'
        + '<div><span>Iniciado:</span><b>' + esc(pr.iniciado) + '</b></div>'
        + '<div><span>Entregado:</span><b>' + esc(pr.finalizado) + '</b></div>'
        + '</div>'
        + (pr.descripcion ? '<p class="carta-descripcion"><b>Tu proyecto:</b> ' + esc(pr.descripcion) + '</p>' : '')
        + (entregables.length ? '<p><b>Lo que recibiste:</b></p><ul class="carta-lista">' + lis + '</ul>' : '')
        + '<p>' + esc(d.cierre) + '</p>'
        + '<div class="carta-firma"><div>Con todo nuestro agradecimiento,</div><div class="carta-firma-nombre">El equipo de ' + SITE_NOMBRE + '</div></div>'
        + '</div>'
        + '<div class="carta-pie">' + SITE_NOMBRE + ' · Soluciones Digitales y Desarrollo · Con gusto seguiremos acompañándote.</div>'
        + '</div>';
}

cargarCarta();
</script>
</body></html>