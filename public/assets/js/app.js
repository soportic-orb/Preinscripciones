/* IEM Preinscripciones — JS ligero (vanilla, sin dependencias de build). */
(function () {
    'use strict';

    // Auto-cierre de toasts tras unos segundos.
    function initToasts() {
        var toasts = document.querySelectorAll('.toast');
        toasts.forEach(function (toast) {
            var closeBtn = toast.querySelector('button');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () { dismiss(toast); });
            }
            setTimeout(function () { dismiss(toast); }, 5000);
        });
    }

    function dismiss(toast) {
        toast.style.transition = 'opacity .2s ease';
        toast.style.opacity = '0';
        setTimeout(function () { toast.remove(); }, 200);
    }

    // API mínima para crear toasts desde JS si hiciera falta.
    window.IEM = window.IEM || {};
    window.IEM.toast = function (type, message) {
        var container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.innerHTML = '<span>' + message + '</span><button aria-label="Cerrar">&times;</button>';
        el.querySelector('button').addEventListener('click', function () { dismiss(el); });
        container.appendChild(el);
        setTimeout(function () { dismiss(el); }, 5000);
    };

    document.addEventListener('DOMContentLoaded', initToasts);
})();
