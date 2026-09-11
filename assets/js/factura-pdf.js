/* factura-pdf.js — Marca FV Digital + helpers de PDF compartidos por facturas y carta. */
(function () {
    var M = window.FVMarca = window.FVMarca || {
        nombre: 'FV DIGITAL', eslogan: 'Soluciones Digitales y Desarrollo',
        logo: '', telefono: '', email: '', direccion: ''
    };
    var _logoCache = null;

    function fmtMXN(n) {
        return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MXN';
    }
    if (!window.fmtMXN) window.fmtMXN = fmtMXN;

    function cargarLogoDataUrl() {
        if (_logoCache !== null) return Promise.resolve(_logoCache);
        if (!M.logo) { _logoCache = ''; return Promise.resolve(''); }
        return fetch(M.logo, { cache: 'no-store' })
            .then(function (r) { if (!r.ok) throw new Error('logo'); return r.blob(); })
            .then(function (b) {
                return new Promise(function (res) {
                    var fr = new FileReader();
                    fr.onload = function () { _logoCache = fr.result; res(fr.result); };
                    fr.onerror = function () { _logoCache = ''; res(''); };
                    fr.readAsDataURL(b);
                });
            })
            .catch(function () { _logoCache = ''; return ''; });
    }

    function encabezadoFactura(doc, emisor, folio, fecha, solicitudId, sub) {
        return cargarLogoDataUrl().then(function (logo) {
            doc.setFillColor(7, 28, 61);
            doc.rect(0, 0, 210, 34, 'F');
            var x = 14;
            if (logo) {
                try {
                    var props = doc.getImageProperties(logo);
                    var h = 22, w = Math.min(26, Math.max(12, h * props.width / props.height));
                    doc.addImage(logo, 'PNG', 14, (34 - h) / 2, w, h);
                    x = 14 + w + 8;
                } catch (e) {}
            }
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(18); doc.setFont(undefined, 'bold');
            doc.text(emisor || M.nombre || 'FV Digital', x, 15);
            doc.setFontSize(10); doc.setFont(undefined, 'normal');
            if (M.eslogan) doc.text(M.eslogan, x, 21);
            doc.setFontSize(9); doc.setTextColor(180, 210, 255);
            doc.text(sub || 'Factura de venta', x, 28);

            doc.setTextColor(255, 255, 255);
            doc.setFontSize(13); doc.setFont(undefined, 'bold');
            doc.text('FACTURA ' + (folio || '—'), 196, 16, { align: 'right' });
            doc.setFont(undefined, 'normal'); doc.setFontSize(9); doc.setTextColor(180, 210, 255);
            doc.text('Fecha de emisión: ' + (fecha || '—'), 196, 23, { align: 'right' });
            if (solicitudId) doc.text('Nº de solicitud: ' + solicitudId, 196, 28, { align: 'right' });
            return doc;
        });
    }

    function fvFacturaPDF(d) {
        var f = (d && d.factura) || {};
        var ivarate = f.iva_rate || ((f.subtotal > 0) ? (f.iva / f.subtotal) : 0.16);
        var ivapct = Math.round(ivarate * 100);
        return encabezadoFactura(new window.jspdf.jsPDF('p', 'mm', 'a4'),
            f.emisor, f.folio, f.fecha, f.solicitud_id, 'Factura de venta')
            .then(function (doc) {
                var y = 44;
                doc.setFontSize(10);
                doc.setTextColor(7, 28, 61); doc.setFont(undefined, 'bold');
                doc.text('DATOS DEL CLIENTE', 14, y);
                doc.text('EMISOR', 115, y);
                doc.setDrawColor(7, 28, 61); doc.setLineWidth(0.4);
                doc.line(14, y + 2.5, 196, y + 2.5); doc.setLineWidth(0.2);

                var yC = y + 10;
                var put = function (k, v, xx, xv) {
                    doc.setFont(undefined, 'normal'); doc.setTextColor(110, 125, 150); doc.text(k, xx, yC);
                    doc.setFont(undefined, 'bold'); doc.setTextColor(30, 30, 30);
                    doc.text(String(v == null ? '—' : v), xv, yC, { maxWidth: 90 });
                };
                put('Cliente:', f.cliente_nombre || '—', 14, 52); yC += 6;
                put('RFC:', f.rfc || '—', 14, 52); yC += 6;
                put('Correo:', f.cliente_email || '—', 14, 52); yC += 6;
                put('Dirección:', f.direccion || '—', 14, 52); yC += 7;
                put('Concepto:', f.concepto || 'Desarrollo de proyecto', 14, 52); yC += 8;

                var yE = 54;
                doc.setFont(undefined, 'bold'); doc.setTextColor(30, 30, 30);
                doc.text(f.emisor || M.nombre || '', 115, yE);
                doc.setFont(undefined, 'normal');
                if (M.direccion) { yE += 5; doc.setTextColor(80, 90, 110); doc.text(M.direccion, 115, yE, { maxWidth: 82 }); yE += 5; }
                if (M.telefono) { doc.text('Tel: ' + M.telefono, 115, yE, { maxWidth: 82 }); yE += 5; }
                if (M.email) { doc.text(M.email, 115, yE, { maxWidth: 82 }); yE += 5; }

                var yT = Math.max(yC, yE) + 2;
                doc.setFontSize(9);
                doc.setFillColor(238, 243, 251);
                doc.rect(14, yT - 6, 182, 7, 'F');
                doc.setFont(undefined, 'bold'); doc.setTextColor(30, 30, 30);
                doc.text('Detalle', 16, yT); doc.text('Monto', 196, yT, { align: 'right' });
                doc.setFont(undefined, 'normal'); doc.setTextColor(70, 80, 100);
                yT += 8;
                var pagos = f.pagos || [];
                if (!pagos.length) {
                    doc.text('Pagos aprobados del proyecto', 16, yT); doc.text(fmtMXN(f.subtotal), 196, yT, { align: 'right' }); yT += 6;
                } else {
                    pagos.forEach(function (p) {
                        doc.text('PAG-' + String(p.id || 0).padStart(5, '0') + ' · ' + (p.fecha || '') + ' · ' + (p.tipo || ''), 16, yT);
                        doc.text(fmtMXN(p.monto), 196, yT, { align: 'right' }); yT += 6;
                    });
                }
                doc.setDrawColor(220, 228, 240); doc.line(14, yT + 2, 196, yT + 2);
                yT += 8;
                doc.setFontSize(10); doc.setFont(undefined, 'normal'); doc.setTextColor(30, 30, 30);
                doc.text('Subtotal:', 150, yT); doc.text(fmtMXN(f.subtotal), 196, yT, { align: 'right' });
                doc.text('IVA (' + ivapct + '%):', 150, yT + 6); doc.text(fmtMXN(f.iva), 196, yT + 6, { align: 'right' });
                yT += 12;
                doc.setFillColor(7, 28, 61); doc.rect(150, yT, 46, 9, 'F');
                doc.setTextColor(255, 255, 255); doc.setFont(undefined, 'bold');
                doc.text('TOTAL: ' + fmtMXN(f.total), 173, yT + 6, { align: 'right' });
                doc.setFontSize(8); doc.setTextColor(130, 145, 165);
                doc.text('Folio: ' + (f.folio || '—') + ' · Emitida por ' + (f.emisor || M.nombre) + '.', 14, 280);
                return doc;
            });
    }

    window.fvLogoDataUrl = cargarLogoDataUrl;
    window.fvEncabezadoFactura = encabezadoFactura;
    window.fvFacturaPDF = fvFacturaPDF;
    window.fvDescargarFactura = function (apiUrl, avisoFn) {
        if (typeof window.jspdf === 'undefined') {
            if (window.Swal) Swal.fire({ icon: 'info', title: 'Espera un momento', text: 'El generador de PDF aún se está cargando. Inténtalo de nuevo.' });
            return;
        }
        return fetch(apiUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    if (window.Swal) Swal.fire({ icon: 'warning', title: 'Aviso', text: d.mensaje || 'No se pudo obtener la factura.' });
                    return;
                }
                return fvFacturaPDF(d).then(function (pdf) {
                    pdf.save('factura-' + (d.factura.folio || 'FV') + '.pdf');
                    var txt = typeof avisoFn === 'function' ? avisoFn(d.factura) : (avisoFn || 'La factura se está descargando.');
                    if (window.Swal) Swal.fire({ icon: 'success', title: 'Listo', text: txt, timer: 1800, showConfirmButton: false });
                });
            })
            .catch(function () {
                if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF de la factura.' });
            });
    };
})();