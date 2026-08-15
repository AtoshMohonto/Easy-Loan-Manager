document.addEventListener('DOMContentLoaded', function () {
    // Mobile sidebar toggle
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('appSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // Generic destructive-action confirm
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Transaction type / person type dependent form helpers
    document.querySelectorAll('[data-amount-input]').forEach(function (input) {
        var preview = document.querySelector(input.getAttribute('data-words-target'));
        if (!preview) return;
        input.addEventListener('input', function () {
            preview.textContent = input.value ? '' : '';
        });
    });

    // Print trigger
    document.querySelectorAll('.btn-print').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    });
});
