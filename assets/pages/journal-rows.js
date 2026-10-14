document.querySelectorAll('.jr-row').forEach(function (row) {
    row.addEventListener('click', function (e) { if (!e.target.closest('a')) window.location.href = row.dataset.href; });
});

