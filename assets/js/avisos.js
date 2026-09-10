/* FV DIGITAL - Avisos centrados y formularios AJAX (público + portal + admin)
   - Overlay animado CENTRADO para todas las confirmaciones/errores
   - Convierte los mensajes flash inline ([data-alerta]) en overlay al cargar
   - Intercepta formularios .js-ajax para evitar la URL cruda tras enviar */
window.FV = window.FV || {};

(function (FV) {
    'use strict';

    /* ---------- Utilidades ---------- */
    function $sel(s, c) { return (c || document).querySelector(s); }

    function esc(s) {
        return (s == null ? '' : String(s))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    /* ---------- Overlay de confirmación (centrado) ---------- */
    let overlayActual = null;

    function svgCheck() {
        return '<svg class="fv-check" width="64" height="64" viewBox="0 0 64 64" aria-hidden="true">'
            + '<circle class="circulo" cx="32" cy="32" r="26.5"></circle>'
            + '<path class="palomita" d="M20 33 l8 8 l17 -18"></path>'
            + '</svg>';
    }

    function cerrarOverlay(final) {
        const ov = overlayActual;
        if (!ov) return;
        overlayActual = null;
        ov.el.classList.remove('mostrar');
        setTimeout(function () {
            if (ov.el.parentNode) ov.el.parentNode.removeChild(ov.el);
        }, final ? 0 : 140);
    }

    FV.overlay = function (opts) {
        const o = opts || {};
        const tipos = ['success', 'danger', 'warning', 'info'];
        const tipo = tipos.indexOf(o.tipo) !== -1 ? o.tipo : 'success';
        const destino = o.destino || null;
        const titulo = o.titulo || (tipo === 'danger' ? 'Ocurrió un problema' : '¡Listo!');
        const mensaje = o.mensaje || '';

        cerrarOverlay(true);

        const div = document.createElement('div');
        div.className = 'fv-overlay' + (tipo === 'danger' || tipo === 'warning' ? ' tipo-' + tipo : '');
        div.innerHTML =
            '<div class="fv-overlay-caja" role="alertdialog" aria-modal="true">'
            + svgCheck()
            + '<h4 class="mb-1">' + esc(titulo) + '</h4>'
            + '<p class="mb-3">' + esc(mensaje) + '</p>'
            + (destino
                ? '<a class="btn btn-fv fv-boton-cierra"><i class="bi bi-arrow-right me-1"></i>Continuar</a>'
                : '<button type="button" class="btn btn-fv fv-boton-cierra"><i class="bi bi-check-lg me-1"></i>Entendido</button>')
            + '</div>';
        document.body.appendChild(div);
        overlayActual = { el: div, destino: destino };

        requestAnimationFrame(function () { div.classList.add('mostrar'); });

        const boton = div.querySelector('.fv-boton-cierra');
        boton.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (destino) { FV.irA(destino); } else { cerrarOverlay(); }
        });

        if (destino) {
            setTimeout(function () {
                if (overlayActual && overlayActual.el === div) FV.irA(destino);
            }, 1500);
        }

        const tecla = function (e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', tecla);
                cerrarOverlay();
            }
        };
        document.addEventListener('keydown', tecla);

        return div;
    };

    FV.irA = function (url) {
        cerrarOverlay(true);
        location.href = url;
    };

    /* ---------- Overlay de carga ---------- */
    let cargandoEl = null;

    FV.cargando = function (on) {
        if (on) {
            if (cargandoEl) return;
            cargandoEl = document.createElement('div');
            cargandoEl.className = 'fv-overlay mostrar';
            cargandoEl.innerHTML = '<div class="fv-overlay-caja fv-cargando">'
                + '<div class="spinner-border text-primary"></div>'
                + '<b>Cargando…</b></div>';
            document.body.appendChild(cargandoEl);
        } else if (cargandoEl) {
            const el = cargandoEl;
            cargandoEl = null;
            el.classList.remove('mostrar');
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 120);
        }
    };

    /* ---------- Envío AJAX de formularios .js-ajax ---------- */
    document.addEventListener('submit', function (evt) {
        const form = evt.target && evt.target.closest ? evt.target.closest('form.js-ajax') : null;
        if (!form) return;
        const esChat = form.classList.contains('js-chat');

        evt.preventDefault();

        const boton = form.querySelector('button[type="submit"]');
        const etiquetaOriginal = boton ? boton.innerHTML : null;
        const disabledOriginal = boton ? boton.disabled : false;
        if (boton) {
            boton.disabled = true;
            const textoCargando = boton.getAttribute('data-cargando');
            if (textoCargando) boton.innerHTML = textoCargando;
        }

        FV.cargando(true);

        const fd = new FormData(form);

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (resp) {
            const ct = resp.headers.get('Content-Type') || '';
            if (ct.indexOf('application/json') !== -1) {
                return resp.json().then(function (d) { return { json: d }; });
            }
            return resp.text().then(function () { return { json: null }; });
        }).then(function (r) {
            FV.cargando(false);
            if (boton) { boton.disabled = disabledOriginal; boton.innerHTML = etiquetaOriginal; }

            if (!r.json) { form.submit(); return; }

            const datos = r.json;
            if (!datos.ok) { FV.overlay(datos); return; }

            if (esChat) {
                form.reset();
                if (FV.chat && FV.chat.refrescar) FV.chat.refrescar();
                return;
            }

            form.reset();
            FV.overlay(datos);
        }).catch(function () {
            FV.cargando(false);
            if (boton) { boton.disabled = disabledOriginal; boton.innerHTML = etiquetaOriginal; }
            form.submit();
        });
    });

    /* ---------- Mensaje flash → overlay centrado al cargar la página ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        const alerta = $sel('[data-alerta]');
        if (!alerta) return;
        const mensajeFlash = alerta.textContent.trim();
        const tipoFlash = alerta.getAttribute('data-alerta') || 'success';
        if (alerta.parentNode) alerta.parentNode.removeChild(alerta);
        if (mensajeFlash) {
            setTimeout(function () {
                FV.overlay({
                    titulo: tipoFlash === 'success' ? '¡Listo!' : 'Aviso',
                    mensaje: mensajeFlash,
                    tipo: tipoFlash
                });
            }, 120);
        }
    });

})(window.FV);