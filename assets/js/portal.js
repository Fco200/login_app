/* FV DIGITAL - Portal de clientes
   - Chat con auto-refresco
   - (El overlay de confirmación y el envío AJAX de .js-ajax viven en avisos.js,
     compartido con el sitio público y el panel admin.) */
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

    /* ---------- Chat de mensajes.php (auto-refresco) ---------- */
    const chatCaja = $sel('#chatCaja');
    if (chatCaja) {
        FV.chat = { token: null, refrescar: refrescarChat };

        function formatearFecha(f) {
            if (!f) return '';
            if (f.indexOf('T') !== -1) {
                const fecha = new Date(f);
                if (!isNaN(fecha.getTime())) {
                    return fecha.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit' })
                        + ' ' + fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
                }
            }
            return f;
        }

        function pintarMensajes(lista) {
            chatCaja.innerHTML = '';
            if (!lista || !lista.length) {
                chatCaja.innerHTML = '<div class="text-center text-muted py-4">Aún no hay mensajes. ¡Escríbenos el primero!</div>';
                return;
            }
            const frag = document.createDocumentFragment();
            lista.forEach(function (m) {
                const esMio = m.remitente === 'cliente';
                const div = document.createElement('div');
                div.className = 'burbuja ' + (esMio ? 'mia' : 'suya');
                div.textContent = m.mensaje;
                const s = document.createElement('small');
                s.className = 'd-block mt-1';
                s.textContent = formatearFecha(m.creado_en) + ' · ' + (esMio ? 'Tú' : 'FV Digital');
                div.appendChild(s);
                frag.appendChild(div);
            });
            chatCaja.appendChild(frag);
            chatCaja.scrollTop = chatCaja.scrollHeight;
        }

        function refrescarChat() {
            fetch('mensajes.php', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.ok) {
                    FV.chat.token = d.csrf || FV.chat.token;
                    pintarMensajes(d.mensajes || []);
                }
            }).catch(function () { });
        }

        refrescarChat();
        setInterval(refrescarChat, 15000);
    }

})(window.FV);