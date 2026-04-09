/**
 * Admin Dashboard — Chart.js Bar Chart Initialisation
 *
 * Initialises Chart.js bar charts for the login activity and pages-not-found
 * widgets on the admin dashboard. Chart data and bar colour are read from
 * data-chart and data-color attributes on each <canvas> element, set in the
 * Twig template.
 *
 * Loaded in:  templates/admin/index.html.twig only
 * Used by:    #loginChart canvas, #notFoundChart canvas
 * Depends on: chart.js (Chart)
 */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
        if (typeof Chart === 'undefined') return;
        const data = JSON.parse(canvas.dataset.chart || '[]');
        const color = canvas.dataset.color || 'rgba(54, 162, 235, 0.5)';
        new Chart(canvas, {
            type: 'bar',
            data: {
                datasets: [{ data: data, backgroundColor: color }]
            },
            options: { plugins: { legend: { display: false } } }
        });
    });
});
