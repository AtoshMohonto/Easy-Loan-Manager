// Instant client-side row filter for list tables.
// Usage: <input data-quick-filter="#myTable"> next to a <table id="myTable">.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-quick-filter]').forEach(function (input) {
        var table = document.querySelector(input.getAttribute('data-quick-filter'));
        if (!table) return;
        input.addEventListener('input', function () {
            var term = input.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(term) !== -1 ? '' : 'none';
            });
        });
    });
});
