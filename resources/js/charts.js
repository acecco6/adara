import Chart from 'chart.js/auto';

const activeCharts = new Set();

export function initializeCharts() {
    activeCharts.forEach((chart) => {
        if (!chart.canvas.isConnected) {
            chart.destroy();
            activeCharts.delete(chart);
        }
    });
    document.querySelectorAll('canvas[data-adara-chart]').forEach((canvas) => {
        if (Chart.getChart(canvas)) return;

        let labels;
        let values;
        try {
            labels = JSON.parse(canvas.dataset.labels);
            values = JSON.parse(canvas.dataset.values);
        } catch {
            return;
        }

        const doughnut = canvas.dataset.adaraChart === 'doughnut';
        const chart = new Chart(canvas, {
            type: doughnut ? 'doughnut' : 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Ventas netas (ARS)',
                    data: values.map((cents) => doughnut ? Math.max(0, cents / 100) : cents / 100),
                    backgroundColor: doughnut ? ['#8B4969', '#D49B89', '#819E8E', '#E5C9AC', '#927EAB', '#D9B55C'] : '#8B4969',
                    borderRadius: 5,
                    maxBarThickness: 36,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 180 },
                plugins: { legend: { display: doughnut, position: 'bottom' } },
                scales: doughnut ? {} : { y: { ticks: { callback: (value) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(value) } } },
            },
        });
        activeCharts.add(chart);
    });
}

export function destroyCharts() {
    activeCharts.forEach((chart) => chart.destroy());
    activeCharts.clear();
}
