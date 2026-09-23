document.addEventListener('alpine:init', () => {
    Alpine.data('runChecksChart', (payload) => {
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

                chart = new Chart(canvas.getContext('2d'), {
                    type: 'line',

                    data: {
                        labels: payload.labels,

                        datasets: [
                            {
                                label: 'Passed',
                                data: payload.passed,
                                borderColor: '#10b981',
                                borderWidth: 2,
                                fill: false,
                                tension: 0.35,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                            },
                            {
                                label: 'Failed',
                                data: payload.failed,
                                borderColor: '#f43f5e',
                                borderWidth: 2,
                                fill: false,
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
                                    precision: 0,
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
                        value: String(p.formattedValue),
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
