/*
 * Charts (Chart.js 4) for the admin dashboard and reports.
 * <canvas data-chart='{"type":"bar","labels":[...],"datasets":[{"label":"Sales","data":[...],"kind":"bar"}],"money":true}'></canvas>
 * kind: bar | line (mixed charts); type "doughnut" for status charts. Uses the brand colours of the admin theme.
 */
(function () {
    'use strict';
    if (typeof Chart === 'undefined') { return; }
    var css = getComputedStyle(document.documentElement);
    var brand = (css.getPropertyValue('--brand') || '#0e7673').trim() || '#0e7673';
    var palette = [brand, '#f0663a', '#2563eb', '#16a34a', '#f59e0b', '#7c3aed', '#dc2626', '#0891b2', '#64748b', '#db2777'];
    var isBn = (document.documentElement.lang || '').indexOf('bn') === 0;
    var BN = '০১২৩৪৫৬৭৮৯';
    var digits = function (s) { s = String(s); return isBn ? s.replace(/[0-9]/g, function (d) { return BN[d]; }) : s; };
    var fmt = function (v, money) {
        var n = Number(v) || 0;
        var s = n.toLocaleString('en-US', { maximumFractionDigits: money ? 0 : 1 });
        return digits((money ? '৳' : '') + s);
    };
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6b7a7a';

    document.querySelectorAll('canvas[data-chart]').forEach(function (el) {
        var cfg;
        try { cfg = JSON.parse(el.getAttribute('data-chart')); } catch (e) { return; }
        var doughnut = cfg.type === 'doughnut';
        var datasets = (cfg.datasets || []).map(function (d, i) {
            var c = doughnut ? palette : palette[i % palette.length];
            var line = d.kind === 'line';
            return {
                type: doughnut ? 'doughnut' : (line ? 'line' : 'bar'),
                label: d.label,
                data: d.data,
                backgroundColor: doughnut ? palette : (line ? 'transparent' : c + 'cc'),
                borderColor: doughnut ? '#fff' : c,
                borderWidth: line ? 2 : (doughnut ? 2 : 0),
                borderRadius: line || doughnut ? 0 : 4,
                tension: 0.3,
                pointRadius: line ? 2 : 0,
                yAxisID: !doughnut && line && i > 0 ? 'y2' : 'y'
            };
        });
        var hasY2 = datasets.some(function (d) { return d.yAxisID === 'y2'; });
        new Chart(el, {
            type: doughnut ? 'doughnut' : 'bar',
            data: { labels: cfg.labels || [], datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: doughnut || datasets.length > 1, position: doughnut ? 'right' : 'top', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var money = cfg.money && (doughnut ? false : ctx.dataset.yAxisID === 'y');
                                var v = doughnut ? ctx.parsed : ctx.parsed.y;
                                return ' ' + ctx.dataset.label + ': ' + fmt(v, money);
                            }
                        }
                    }
                },
                scales: doughnut ? {} : {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, callback: function (v) { return digits(this.getLabelForValue(v)); } } },
                    y: { beginAtZero: true, ticks: { callback: function (v) { return fmt(v, !!cfg.money); } } },
                    y2: { display: hasY2, beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0, callback: function (v) { return digits(v); } } }
                },
                cutout: doughnut ? '62%' : undefined
            }
        });
    });
})();
