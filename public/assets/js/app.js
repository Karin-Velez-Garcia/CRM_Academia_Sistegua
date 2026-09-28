// Comportamiento básico de la interfaz: menú lateral móvil, modo oscuro y confirmaciones.
(function () {
    'use strict';

    // Menú lateral en celulares
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () { document.body.classList.toggle('sidebar-abierto'); });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', function () { document.body.classList.remove('sidebar-abierto'); });
    });

    // Modo claro / oscuro (se recuerda en este navegador)
    document.querySelectorAll('[data-tema-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var html = document.documentElement;
            var nuevo = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', nuevo);
            try { localStorage.setItem('tema', nuevo); } catch (e) {}
        });
    });

    // Confirmación antes de enviar formularios delicados: <form data-confirmar="¿Eliminar...?">
    var modalEl = document.getElementById('modalConfirmar');
    if (modalEl && window.bootstrap) {
        var modal = new bootstrap.Modal(modalEl);
        var pendiente = null;
        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!form.matches('form[data-confirmar]') || form.dataset.confirmado === '1') return;
            ev.preventDefault();
            pendiente = form;
            modalEl.querySelector('[data-confirmar-texto]').textContent = form.dataset.confirmar;
            modal.show();
        });
        modalEl.querySelector('[data-confirmar-aceptar]').addEventListener('click', function () {
            if (!pendiente) return;
            pendiente.dataset.confirmado = '1';
            modal.hide();
            pendiente.submit();
        });
    }

    // Mostrar / ocultar contraseña
    document.querySelectorAll('[data-ver-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.verPassword);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.querySelector('i').className = input.type === 'password' ? 'ki-outline ki-eye fs-2' : 'ki-outline ki-eye-slash fs-2';
        });
    });

    // Tooltips de Bootstrap
    if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
    }
})();
