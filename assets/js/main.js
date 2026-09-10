/* FV DIGITAL - Scripts públicos */
document.addEventListener('DOMContentLoaded', function () {
    // Animación de aparición al hacer scroll
    const observador = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (entrada) {
            if (entrada.isIntersecting) {
                entrada.target.classList.add('visible');
                observador.unobserve(entrada.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.animar').forEach(function (el) {
        observador.observe(el);
    });

    // Desplegables de navbar se cierran al navegar (móvil)
    const toggler = document.getElementById('menuPublico');
    if (toggler) {
        toggler.querySelectorAll('a.nav-link').forEach(function (enlace) {
            enlace.addEventListener('click', function () {
                const colapso = bootstrap.Collapse.getInstance(toggler);
                if (colapso && toggler.classList.contains('show')) colapso.hide();
            });
        });
    }
});