document.addEventListener('alpine:init', () => {
    Alpine.data('statusDoughnut', (payload) => {
        // Keep the Chart.js instance outside Alpine's reactive state.
        let chart = null;

        const { series, status, ink } = window.StradenCharts;

        // Same order as the payload: passed, failed, running, queued, error.
        const colors = [status.good, status.critical, series[0], status.neutral, status.warning];

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

                if (!canvas || payload.counts.every((c) => c === 0)) {
                    return;
                }

                // Defensive cleanup in case this canvas already has
                // a Chart.js instance attached to it.
                const existing = Chart.getChart(canvas);

                if (existing) {
                    existing.destroy();
                }

                chart = new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',

                    data: {
                        labels: payload.labels,

                        datasets: [
                            {
                                data: payload.counts,
                                backgroundColor: colors,
                                borderWidth: 2,
                                borderColor: ink.surface,
                                borderRadius: 3,
                                hoverOffset: 4,
                            },
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '74%',

                        plugins: {
                            legend: {
                                display: false,
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

                const index = tooltipModel.dataPoints?.[0]?.dataIndex;

                this.tooltip = {
                    show: true,
                    x: tooltipModel.caretX,
                    y: tooltipModel.caretY,
                    label: payload.labels[index] ?? '',
                    rows: [
                        {
                            label: 'Runs',
                            value: payload.counts[index] ?? 0,
                            color: colors[index] ?? status.neutral,
                        },
                    ],
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
