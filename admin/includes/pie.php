<?php
/* Pie del panel administrativo: mantiene la sesión activa y avisa antes de expirar. */
$sesionSeg = (int)sesion_restante_seg();
?>
        </div>
    </main>
</div>

<div class="modal fade" id="modalSesion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-hourglass-split me-2 text-warning"></i>Tu sesión está por expirar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                Por inactividad tu sesión terminará en breve. Puedes continuar trabajando si ya estás activo: el panel se renueva solo con tu actividad.
            </div>
            <div class="modal-footer">
                <a href="cerrar.php" class="btn btn-outline-danger">Cerrar sesión</a>
                <button type="button" class="btn btn-fv" onclick="FVsesion.continuar()"><i class="bi bi-arrow-clockwise me-1"></i>Continuar sesión</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/avisos.js"></script>
<script src="../../assets/js/main.js"></script>
<script src="../../assets/js/portal.js"></script>
<script>var FVMarca = <?= json_encode(['nombre' => dato_sitio('nombre', SITE_NOMBRE), 'eslogan' => dato_sitio('eslogan', SITE_ESLOGAN), 'logo' => logo_sitio(), 'telefono' => dato_sitio('telefono', SITE_TELEFONO), 'email' => dato_sitio('email', SITE_EMAIL), 'direccion' => dato_sitio('direccion', SITE_DIRECCION), 'razon_social' => dato_sitio('razon_social', ''), 'rfc' => dato_sitio('rfc_emisor', '')], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="../../assets/js/factura-pdf.js"></script>
<script>
window.FVsesion = {
    seg: <?= $sesionSeg ?>,
    avisado: false,
    modal: null,
    fmt: function (s) {
        s = Math.max(0, s | 0);
        var m = Math.floor(s / 60), ss = s % 60;
        return (m < 10 ? '0' : '') + m + ':' + (ss < 10 ? '0' : '') + ss;
    },
    pintar: function () {
        var el = document.getElementById('sesionRestante');
        var chip = document.getElementById('chipSesion');
        if (el) el.textContent = this.fmt(this.seg);
        if (chip && this.seg <= 60) chip.classList.add('text-danger', 'fw-bold');
    },
    continuar: function () {
        var self = this;
        fetch('keepalive.php', {
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.ok) {
                self.seg = d.restante;
                self.avisado = false;
                if (self.modal) self.modal.hide();
                self.pintar();
            }
        }).catch(function () {});
    }
};
(function () {
    var S = window.FVsesion;
    S.modal = new bootstrap.Modal(document.getElementById('modalSesion'));
    setInterval(function () {
        S.seg--; S.pintar();
        if (S.seg <= 0) { location.href = 'login.php'; return; }
        if (S.seg <= 60 && !S.avisado) { S.avisado = true; S.modal.show(); }
    }, 1000);
    setInterval(function () { S.continuar(); }, 60000);
})();
</script>
</body>
</html>