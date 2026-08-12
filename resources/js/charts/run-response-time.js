document.addEventListener('alpine:init', () => {
    Alpine.data('runResponseTimeChart', (payload) => {
        // Keep Chart.js OUTSIDE Alpine's reactive/proxy system.
        let chart = null;

        return {
            tooltip: {
                show: false,
                x: 0,
                y: 0,
                label: '',
                rows: [],
            },

            init() {
                const canvas = this.$refs.canvas;

                if (!canvas || !payload?.labels?.length) {
                    return;
                }

                const existing = Chart.getChart(canvas);

                if (existing) {
                    existing.destroy();
                }

                const ctx = canvas.getContext('2d');

                const gP95 = ctx.createLinearGradient(0, 0, 0, 240);
                gP95.addColorStop(0, 'rgba(2, 132, 199, 0.18)');
                gP95.addColorStop(1, 'rgba(2, 132, 199, 0.0)');

                const gP99 = ctx.createLinearGradient(0, 0, 0, 240);
                gP99.addColorStop(0, 'rgba(244, 63, 94, 0.14)');
                gP99.addColorStop(1, 'rgba(244, 63, 94, 0.0)');

                chart = new Chart(ctx, {
                    type: 'line',

                    data: {
                        labels: payload.labels,

                        datasets: [
                            {
                                label: 'p95 (ms)',
                                data: payload.p95,
                                borderColor: '#0284c7',
                                backgroundColor: gP95,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                            },
                            {
                                label: 'p99 (ms)',
                                data: payload.p99,
                                borderColor: '#f43f5e',
                                backgroundColor: gP99,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                            },
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,

                        interaction: {
                            intersect: false,
                            mode: 'index',
                        },

                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(161, 161, 170, 0.1)',
                                },
                                ticks: {
                                    color: '#a1a1aa',
                                    font: { size: 11 },
                                    callback: (value) => {
                                        const number = Number(value);

                                        if (number >= 1_000_000) {
                                            return `${number / 1_000_000}M`;
                                        }

                                        if (number >= 1_000) {
                                            return `${number / 1_000}k`;
                                        }

                                        return number;
                                    },
                                },
                            },
                            x: {
                                grid: { display: false },
                                ticks: {
                                    color: '#a1a1aa',
                                    font: { size: 11 },
                                    maxTicksLimit: 8,
                                },
                            },
                        },

                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    padding: 12,
                                    usePointStyle: true,
                                    pointStyle: 'line',
                                    color: '#a1a1aa',
                                },
                            },
                            tooltip: {
                                enabled: false,
                                external: (context) => {
                                    this.externalTooltip(context);
                                },
                            },
                        },
                    },
                });
            },

            externalTooltip(context) {
                const tooltipModel = context.tooltip;

                if (!tooltipModel || tooltipModel.opacity === 0) {
                    this.tooltip.show = false;
                    return;
                }

                const points = tooltipModel.dataPoints ?? [];

                this.tooltip = {
                    show: true,
                    x: tooltipModel.caretX,
                    y: tooltipModel.caretY - 12,
                    label: points[0]?.label ?? '',
                    rows: points.map((p) => ({
                        label: p.dataset.label,
                        value: `${p.formattedValue} ms`,
                        color: p.dataset.borderColor,
                    })),
                };
            },

            destroy() {
                if (chart) {
                    chart.destroy();
                    chart = null;
                }
            },
        };
    });
});
