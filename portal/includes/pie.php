<?php
/* Pie del Portal de Clientes de FV Digital */
?>
    </div>
</main>

<footer class="portal-footer no-print">
    <div class="container py-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small">&copy; <?= date('Y') ?> <?= e(SITE_NOMBRE) ?> — Portal de clientes.</span>
        <a href="../index.php" class="small text-decoration-none"><i class="bi bi-globe2 me-1"></i>Volver al sitio</a>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/avisos.js"></script>
<script src="../assets/js/main.js"></script>
<script src="../assets/js/portal.js"></script>
<script>var FVMarca = <?= json_encode(['nombre' => dato_sitio('nombre', SITE_NOMBRE), 'eslogan' => dato_sitio('eslogan', SITE_ESLOGAN), 'logo' => logo_sitio(), 'telefono' => dato_sitio('telefono', SITE_TELEFONO), 'email' => dato_sitio('email', SITE_EMAIL), 'direccion' => dato_sitio('direccion', SITE_DIRECCION), 'razon_social' => dato_sitio('razon_social', ''), 'rfc' => dato_sitio('rfc_emisor', '')], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="../assets/js/factura-pdf.js"></script>
<?php if ($seccionPortal === 'juego'): ?>
    <script src="../assets/js/juegos.js"></script>
<?php endif; ?>
</body>
</html>