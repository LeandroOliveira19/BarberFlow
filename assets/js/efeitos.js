/*
 * Palavra a palavra: divide o texto de cada elemento com data-stagger em <span class="palavra">
 * e junta a classe "visivel" quando o elemento entra no ecrã (uma só vez). O estilo está em assets/css/efeitos.css.
 * Os leitores de ecrã leem a frase inteira (aria-label no elemento, palavras com aria-hidden).
 * Só usa textContent e createElement: texto com aspas ou acentos nunca é interpretado como HTML.
 */
(function () {
    'use strict';

    function dividirEmPalavras(el) {
        var texto = el.textContent.replace(/\s+/g, ' ').trim();
        if (texto === '') return;
        el.setAttribute('aria-label', texto);
        el.textContent = '';
        texto.split(' ').forEach(function (palavra, i) {
            if (i > 0) el.appendChild(document.createTextNode(' '));
            var span = document.createElement('span');
            span.className = 'palavra';
            span.setAttribute('aria-hidden', 'true');
            span.style.setProperty('--i', String(i));
            span.textContent = palavra;
            el.appendChild(span);
        });
    }

    function iniciar() {
        var elementos = document.querySelectorAll('[data-stagger]');
        elementos.forEach(dividirEmPalavras);

        if (!('IntersectionObserver' in window)) {
            elementos.forEach(function (el) { el.classList.add('visivel'); });
            return;
        }
        var observador = new IntersectionObserver(function (entradas, obs) {
            entradas.forEach(function (entrada) {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('visivel');
                    obs.unobserve(entrada.target);
                }
            });
        }, { threshold: 0.3 });
        elementos.forEach(function (el) { observador.observe(el); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
