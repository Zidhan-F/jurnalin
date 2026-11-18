// Grafik garis dashboard admin. Dimuat dinamis oleh app.js hanya bila halaman punya [data-chart].
// Angka yang sama selalu ada di HTML (tabel di komponen <x-chart>), jadi grafik hanya pelengkap visual.
import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);

const css = getComputedStyle(document.documentElement);
const token = (name) => css.getPropertyValue(`--color-${name}`).trim();
// Token warna berupa hex; canvas tidak selalu mengerti color-mix(), jadi transparansi dibuat lewat rgba.
const withAlpha = (hex, alpha) => {
    const h = hex.replace('#', '');
    const n = parseInt(h.length === 3 ? h.split('').map((c) => c + c).join('') : h, 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
};
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function lineConfig(data) {
    const color = token(data.colors[0] || 'navy');
    return {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: data.label,
                data: data.values,
                borderColor: color,
                backgroundColor: withAlpha(color, 0.14),
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointBackgroundColor: color,
            }],
        },
        options: {
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0, color: token('muted') }, grid: { color: token('border') } },
                x: { ticks: { color: token('muted') }, grid: { display: false } },
            },
        },
    };
}

export async function mountCharts(root) {
    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        if (canvas.dataset.mounted) return;
        let data;
        try {
            data = JSON.parse(canvas.dataset.chart);
        } catch {
            return;
        }
        // Donat sudah digambar dengan CSS di komponen <x-chart>; Chart.js hanya untuk grafik garis.
        if (data.type !== 'line') return;
        const config = lineConfig(data);
        config.options = {
            ...config.options,
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 400 },
            plugins: { legend: { display: false }, tooltip: { enabled: true } },
        };
        new Chart(canvas, config);
        canvas.dataset.mounted = '1';
        // Tabel angka grafik garis cukup untuk pembaca layar setelah grafik tampil.
        canvas.closest('figure')?.classList.add('is-mounted');
    });
}
