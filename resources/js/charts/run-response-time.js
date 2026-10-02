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

                const { series, ink } = window.StradenCharts;

                chart = new Chart(canvas.getContext('2d'), {
                    type: 'line',

                    data: {
                        labels: payload.labels,

                        datasets: [
                            {
                                label: 'p95 (ms)',
                                data: payload.p95,
                                borderColor: series[0],
                                backgroundColor: series[0],
                                pointBackgroundColor: series[0],
                                pointHoverBorderColor: ink.surface,
                                pointHoverBorderWidth: 2,
                                fill: false,
                            },
                            {
                                label: 'p99 (ms)',
                                data: payload.p99,
                                borderColor: series[1],
                                backgroundColor: series[1],
                                pointBackgroundColor: series[1],
                                pointHoverBorderColor: ink.surface,
                                pointHoverBorderWidth: 2,
                                fill: false,
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
                                border: { display: false },
                                ticks: { maxTicksLimit: 8 },
                            },
                        },

                        plugins: {
                            legend: { position: 'bottom', align: 'start' },
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
