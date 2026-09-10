/* FV DIGITAL - Mini-juegos del portal de clientes
   - Memorama (parejas de emojis con tiempo y movimientos)
   - La serpiente (canvas)
   Cada partida guarda el puntaje a juego.php vía AJAX y muestra el overlay
   animado cuando se consigue un nuevo récord. */

window.FV = window.FV || {};
(function (FV) {
    'use strict';

    function $sel(s, c) { return (c || document).querySelector(s); }

    function tokenCsrf() {
        var n = $sel('input[name="csrf"]');
        return n ? n.value : '';
    }

    function guardarPuntaje(juego, puntaje, esRecord) {
        if (!puntaje || puntaje <= 0) return;
        var fd = new FormData();
        fd.append('csrf', tokenCsrf());
        fd.append('juego', juego);
        fd.append('puntaje', String(puntaje));
        fetch('juego.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.ok && esRecord && FV.overlay) {
                FV.overlay({ titulo: d.titulo || '¡Nuevo récord!', mensaje: d.mensaje || '', tipo: 'success' });
            }
        }).catch(function () { });
    }

    /* =========================================================
       MEMORAMA
       ========================================================= */
    var memory = {
        emojis: ['🚀', '💻', '🎨', '⚡', '🎯', '🛠️', '📱', '🌟'],
        grid: null, primera: null, segunda: null,
        bloqueo: false, parejas: 0, parejasMeta: 8,
        movimientos: 0, enJuego: false, crono: null, inicio: 0,

        nuevo: function () { memory.iniciar(); },

        iniciar: function () {
            var grid = $sel('#memGrid');
            if (!grid) return;

            if (memory.crono) { clearInterval(memory.crono); memory.crono = null; }
            memory.primera = null; memory.segunda = null;
            memory.bloqueo = false; memory.parejas = 0; memory.movimientos = 0;
            memory.enJuego = false; memory.inicio = 0;

            $sel('#memMovimientos').textContent = '0';
            $sel('#memTiempo').textContent = '0s';

            // Baraja de parejas
            var deck = memory.emojis.concat(memory.emojis);
            for (var i = deck.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var tmp = deck[i]; deck[i] = deck[j]; deck[j] = tmp;
            }

            grid.innerHTML = '';
            deck.forEach(function (emoji, idx) {
                var card = document.createElement('div');
                card.className = 'mem-card';
                card.dataset.emoji = emoji;
                card.innerHTML =
                    '<div class="cara atras"><i class="bi bi-stars"></i></div>' +
                    '<div class="cara frente">' + escEmoji(emoji) + '</div>';
                card.addEventListener('click', function () { memory.flip(card); });
                grid.appendChild(card);
            });
        },

        flip: function (card) {
            if (memory.bloqueo) return;
            if (card.classList.contains('volteada') || card.classList.contains('encontrada')) return;

            if (!memory.enJuego) {
                memory.enJuego = true;
                memory.inicio = Date.now();
                memory.crono = setInterval(function () {
                    var seg = Math.floor((Date.now() - memory.inicio) / 1000);
                    var el = $sel('#memTiempo');
                    if (el) el.textContent = seg + 's';
                }, 1000);
            }

            card.classList.add('volteada');

            if (!memory.primera) {
                memory.primera = card;
                return;
            }

            memory.segunda = card;
            memory.movimientos++;
            $sel('#memMovimientos').textContent = String(memory.movimientos);

            if (memory.primera.dataset.emoji === memory.segunda.dataset.emoji) {
                memory.primera.classList.add('encontrada');
                memory.segunda.classList.add('encontrada');
                memory.primera = null; memory.segunda = null;
                memory.parejas++;

                if (memory.parejas >= memory.parejasMeta) {
                    memory.terminar();
                }
            } else {
                memory.bloqueo = true;
                var a = memory.primera, b = memory.segunda;
                setTimeout(function () {
                    a.classList.remove('volteada');
                    b.classList.remove('volteada');
                    memory.bloqueo = false;
                    memory.primera = null; memory.segunda = null;
                }, 780);
            }
        },

        terminar: function () {
            if (memory.crono) { clearInterval(memory.crono); memory.crono = null; }
            var seg = Math.floor((Date.now() - memory.inicio) / 1000);
            var puntos = Math.max(0, (memory.parejasMeta * 500) - (memory.movimientos * 10) - seg);

            var recordEl = $sel('#memRecord');
            var anterior = recordEl ? parseInt(recordEl.textContent, 10) || 0 : 0;
            var esRecord = puntos > anterior;

            if (esRecord && recordEl) recordEl.textContent = String(puntos);
            guardarPuntaje('memorama', puntos, esRecord);
        }
    };

    function escEmoji(e) {
        return e;
    }

    /* =========================================================
       LA SERPIENTE
       ========================================================= */
    var snake = {
        cols: 20, rows: 20, cell: 20,
        canvas: null, ctx: null,
        serpiente: null, dir: null, cola: null,
        fruta: null, puntos: 0, enJuego: false, pausado: false,
        timer: null, velocidad: 130, tactil: { x: 0, y: 0 },

        iniciar: function () {
            var canvas = $sel('#snakeCanvas');
            if (!canvas) return;
            snake.canvas = canvas;
            snake.ctx = canvas.getContext('2d');
            if (snake.timer) { clearInterval(snake.timer); snake.timer = null; }

            var cx = Math.floor(snake.cols / 2);
            var cy = Math.floor(snake.rows / 2);
            snake.serpiente = [{ x: cx, y: cy }, { x: cx - 1, y: cy }, { x: cx - 2, y: cy }];
            snake.dir = { x: 1, y: 0 };
            snake.cola = { x: 0, y: 0 };
            snake.puntos = 0;
            snake.enJuego = true;
            snake.pausado = false;
            $sel('#snkPuntos').textContent = '0';

            snake.colocarFruta();
            snake.dibujar();
            snake.timer = setInterval(snake.paso, snake.velocidad);
        },

        pausar: function () {
            if (!snake.enJuego) return;
            snake.pausado = !snake.pausado;
        },

        colocarFruta: function () {
            var libre = false;
            while (!libre) {
                var f = {
                    x: Math.floor(Math.random() * snake.cols),
                    y: Math.floor(Math.random() * snake.rows)
                };
                libre = !snake.serpiente.some(function (s) { return s.x === f.x && s.y === f.y; });
                if (libre) snake.fruta = f;
            }
        },

        paso: function () {
            if (!snake.enJuego || snake.pausado) return;

            var cabeza = { x: snake.serpiente[0].x + snake.dir.x, y: snake.serpiente[0].y + snake.dir.y };

            // Paredes
            if (cabeza.x < 0 || cabeza.y < 0 || cabeza.x >= snake.cols || cabeza.y >= snake.rows) {
                snake.gameOver(); return;
            }
            // Choca consigo misma
            if (snake.serpiente.some(function (s) { return s.x === cabeza.x && s.y === cabeza.y; })) {
                snake.gameOver(); return;
            }

            snake.serpiente.unshift(cabeza);
            snake.cola = snake.serpiente.pop();

            if (cabeza.x === snake.fruta.x && cabeza.y === snake.fruta.y) {
                snake.puntos += 10;
                $sel('#snkPuntos').textContent = String(snake.puntos);
                snake.serpiente.push(snake.cola); // crece
                snake.colocarFruta();
            }

            snake.dibujar();
        },

        dibujar: function () {
            var ctx = snake.ctx;
            ctx.clearRect(0, 0, 400, 400);
            ctx.fillStyle = '#0a1e39';
            ctx.fillRect(0, 0, 400, 400);

            // Fruta
            ctx.fillStyle = '#ff5d8f';
            ctx.beginPath();
            ctx.arc(snake.fruta.x * snake.cell + snake.cell / 2, snake.fruta.y * snake.cell + snake.cell / 2, snake.cell / 2 - 3, 0, Math.PI * 2);
            ctx.fill();

            // Cuerpo
            snake.serpiente.forEach(function (s, i) {
                ctx.fillStyle = i === 0 ? '#16b8f3' : '#0b5ed7';
                ctx.beginPath();
                ctx.roundRect(s.x * snake.cell + 1.5, s.y * snake.cell + 1.5, snake.cell - 3, snake.cell - 3, 5);
                ctx.fill();
            });

            if (snake.pausado) {
                ctx.fillStyle = 'rgba(255,255,255,.85)';
                ctx.font = '600 22px Poppins, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('⏸ Pausa', 200, 200);
            }
        },

        gameOver: function () {
            if (snake.timer) { clearInterval(snake.timer); snake.timer = null; }
            snake.enJuego = false;

            var recordEl = $sel('#snkRecord');
            var anterior = recordEl ? parseInt(recordEl.textContent, 10) || 0 : 0;
            var esRecord = snake.puntos > anterior;
            if (esRecord && recordEl) recordEl.textContent = String(snake.puntos);

            guardarPuntaje('serpiente', snake.puntos, esRecord);
        }
    };

    FV.jugos = { memory: memory, snake: snake };
    FV.juegos = FV.jugos;

    /* ----- Controles de la serpiente ----- */
    document.addEventListener('keydown', function (e) {
        var dir = null;
        switch (e.key) {
            case 'ArrowUp': case 'w': case 'W': dir = { x: 0, y: -1 }; break;
            case 'ArrowDown': case 's': case 'S': dir = { x: 0, y: 1 }; break;
            case 'ArrowLeft': case 'a': case 'A': dir = { x: -1, y: 0 }; break;
            case 'ArrowRight': case 'd': case 'D': dir = { x: 1, y: 0 }; break;
        }
        if (dir) {
            if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) !== -1) e.preventDefault();
            if (snake.enJuego && !snake.pausado && dir.x !== -snake.dir.x && dir.y !== -snake.dir.y) {
                snake.dir = dir;
            }
        }
    });

    /* Deslizar en móvil */
    document.addEventListener('touchstart', function (e) {
        if (e.target.id !== 'snakeCanvas') return;
        snake.tactil = { x: e.touches[0].clientX, y: e.touches[0].clientY };
    }, { passive: true });
    document.addEventListener('touchend', function (e) {
        if (!snake.tactil.x && !snake.tactil.y) return;
        var dx = e.changedTouches[0].clientX - snake.tactil.x;
        var dy = e.changedTouches[0].clientY - snake.tactil.y;
        snake.tactil = { x: 0, y: 0 };
        if (!snake.enJuego || snake.pausado) return;
        var dir = null;
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 24) dir = dx > 0 ? { x: 1, y: 0 } : { x: -1, y: 0 };
        else if (Math.abs(dy) > 24) dir = dy > 0 ? { x: 0, y: 1 } : { x: 0, y: -1 };
        if (dir && dir.x !== -snake.dir.x && dir.y !== -snake.dir.y) snake.dir = dir;
    }, { passive: true });

    /* Arranque automático de los juegos al cargar la página */
    function arrancar() {
        if ($sel('#memGrid')) memory.iniciar();
        if ($sel('#snakeCanvas')) snake.iniciar();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }

})(window.FV);