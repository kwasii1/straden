document.addEventListener('alpine:init', () => {
    Alpine.data('runIterationsChart', (payload) => {
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

                const { series, fill, ink } = window.StradenCharts;

                const color = series[0];

                chart = new Chart(canvas.getContext('2d'), {
                    type: 'line',

                    data: {
                        labels: payload.labels,

                        datasets: [
                            {
                                label: 'Iterations',
                                data: payload.values,
                                borderColor: color,
                                backgroundColor: fill(color),
                                pointBackgroundColor: color,
                                pointHoverBorderColor: ink.surface,
                                pointHoverBorderWidth: 2,
                                fill: true,
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
                                border: { display: false },
                                ticks: {
                                    maxTicksLimit: 5,
                                    precision: 0,
                                },
                            },
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { maxTicksLimit: 8 },
                            },
                        },

                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: false,
                                external: (context) => this.externalTooltip(context),
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
                    y: tooltipModel.caretY,
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
