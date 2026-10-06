/* FV DIGITAL - Portal de clientes
   - Chat en tiempo real (Server-Sent Events): lo que escribes llega al
     instante al otro lado y viceversa, sin recargar la página.
   - Respaldo: si el navegador no soporta EventSource cae a polling rápido.
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

    /* ============================================================
       Chat del cliente (portal/mensajes.php)
       ============================================================ */
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
            const firma = JSON.stringify(lista || []);
            if (chatCaja.dataset.firma === firma) return;
            chatCaja.dataset.firma = firma;

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

        if (window.EventSource) {
            try {
                const fuente = new EventSource('chat_stream.php');
                fuente.addEventListener('chat', function (ev) {
                    try {
                        const d = JSON.parse(ev.data);
                        FV.chat.token = d.csrf || FV.chat.token;
                        pintarMensajes(d.mensajes || []);
                    } catch (e) { }
                });
            } catch (e) {
                setInterval(refrescarChat, 4000);
            }
        } else {
            setInterval(refrescarChat, 4000);
        }
    }

    /* ============================================================
       Chat del administrador (admin/mensajes_portal.php)
       ============================================================ */
    const liveAdmin = document.getElementById('adminChatLive');
    if (liveAdmin) {
        const usuarioId = parseInt(liveAdmin.getAttribute('data-usuario') || '0', 10) || 0;
        const caja = document.getElementById('chatAdminCaja');

        function pintarChatAdmin(lista) {
            if (!caja) return;
            const firma = JSON.stringify(lista || []);
            if (caja.dataset.firma === firma) return;
            caja.dataset.firma = firma;

            if (!lista || !lista.length) {
                caja.innerHTML = '<div class="text-center text-muted py-4">Aún no hay mensajes en esta conversación.</div>';
                return;
            }
            const frag = document.createDocumentFragment();
            lista.forEach(function (m) {
                const esMio = m.remitente === 'negocio';
                const div = document.createElement('div');
                div.className = 'burbuja ' + (esMio ? 'mia' : 'suya');
                div.textContent = m.mensaje;
                const s = document.createElement('small');
                s.className = 'd-block text-muted mt-1';
                s.textContent = (m.fecha || '') + ' · ' + (esMio ? 'Tú (FV Digital)' : 'Cliente');
                div.appendChild(s);
                frag.appendChild(div);
            });
            caja.innerHTML = '';
            caja.appendChild(frag);
            caja.scrollTop = caja.scrollHeight;
        }

        function pintarConversaciones(lista) {
            lista = lista || [];
            const total = document.getElementById('convTotal');
            if (total) total.textContent = 'Conversaciones (' + lista.length + ')';

            const cont = document.getElementById('listaConversaciones');
            let grupo = cont ? cont.querySelector('.list-group') : null;
            if (cont && !grupo && lista.length) {
                cont.innerHTML = '';
                grupo = document.createElement('div');
                grupo.className = 'list-group list-group-flush';
                cont.appendChild(grupo);
            }

            let pendientes = 0;
            lista.forEach(function (c) {
                const id = parseInt(c.usuario_id, 10) || 0;
                const n = Math.max(0, parseInt(c.pendientes, 10) || 0);
                pendientes += n;

                let enlace = cont ? cont.querySelector('a[data-conv="' + id + '"]') : null;
                if (!enlace) {
                    if (!grupo) return;
                    enlace = document.createElement('a');
                    enlace.href = 'mensajes_portal.php?usuario_id=' + id;
                    enlace.className = 'list-group-item list-group-item-action';
                    enlace.setAttribute('data-conv', String(id));
                    enlace.innerHTML = '<div class="d-flex justify-content-between align-items-center"><b class="small"></b></div>'
                        + '<small class="d-block text-muted js-conv-email"></small>'
                        + '<small class="d-block text-muted js-conv-preview"></small>';
                    grupo.insertBefore(enlace, grupo.firstChild);
                }

                let badge = enlace.querySelector('.badge');
                if (n > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'badge text-bg-danger';
                        enlace.querySelector('.d-flex').appendChild(badge);
                    }
                    badge.textContent = String(n);
                    badge.style.display = '';
                } else if (badge) {
                    badge.style.display = 'none';
                }

                const nombre = enlace.querySelector('b.small');
                if (nombre) nombre.textContent = c.nombre || '';
                const email = enlace.querySelector('.js-conv-email');
                if (email) email.textContent = c.email || '';
                const preview = enlace.querySelector('.js-conv-preview');
                if (preview) preview.textContent = (c.ultimo_mensaje || '').slice(0, 45);
            });

            document.querySelectorAll('.js-badge-chat').forEach(function (b) {
                b.textContent = String(pendientes);
                b.style.display = pendientes > 0 ? '' : 'none';
            });
        }

        function refrescarAdmin() {
            fetch('chat_stream.php?json=1&usuario_id=' + usuarioId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.ok) {
                    pintarChatAdmin(d.mensajes);
                    pintarConversaciones(d.conversaciones);
                }
            }).catch(function () { });
        }

        if (window.EventSource) {
            try {
                const fuente = new EventSource('chat_stream.php?usuario_id=' + usuarioId);
                fuente.addEventListener('chat', function (ev) {
                    try {
                        const d = JSON.parse(ev.data);
                        pintarChatAdmin(d.mensajes);
                    } catch (e) { }
                });
                fuente.addEventListener('conversaciones', function (ev) {
                    try {
                        const d = JSON.parse(ev.data);
                        pintarConversaciones(d.conversaciones);
                    } catch (e) { }
                });
            } catch (e) {
                refrescarAdmin();
                setInterval(refrescarAdmin, 5000);
            }
        } else {
            refrescarAdmin();
            setInterval(refrescarAdmin, 5000);
        }
    }

})(window.FV);
