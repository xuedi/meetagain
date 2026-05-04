/**
 * Admin Dashboard - Chart.js initialisation
 *
 * Initialises two flavours of Chart.js charts based on canvas attributes:
 *   data-chart        - single-series bar chart (existing tiles)
 *   data-multi-chart  - multi-series line chart with shared X axis (new in 2026-05)
 *
 * Loaded in:  templates/admin/index.html.twig only
 * Depends on: chart.js (Chart)
 */

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
        const data = JSON.parse(canvas.dataset.chart || '[]');
        const color = canvas.dataset.color || 'rgba(54, 162, 235, 0.5)';
        new Chart(canvas, {
            type: 'bar',
            data: {
                datasets: [{ data: data, backgroundColor: color }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
            },
        });
    });

    const isNarrow = window.matchMedia('(max-width: 600px)').matches;

    document.querySelectorAll('canvas[data-multi-chart]').forEach(function (canvas) {
        const payload = JSON.parse(canvas.dataset.multiChart || '{}');
        const datasets = (payload.datasets || []).map(function (ds) {
            return {
                label: ds.label,
                data: ds.data,
                borderColor: ds.borderColor,
                backgroundColor: ds.borderColor,
                tension: 0.25,
                pointRadius: 2,
                fill: false,
            };
        });
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: payload.labels || [],
                datasets: datasets,
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                },
                scales: {
                    x: {
                        ticks: isNarrow
                            ? { callback: function (_, index) {
                                const label = (payload.labels || [])[index] || '';
                                return label.length > 5 ? label.slice(5) : label;
                            } }
                            : {},
                    },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    });
});
