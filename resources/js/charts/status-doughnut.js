document.addEventListener('alpine:init', () => {
    Alpine.data('statusDoughnut', (payload) => {
        // Keep the Chart.js instance outside Alpine's reactive state.
        let chart = null;

        return {
            tooltip: {
                show: false,
                x: 0,
                y: 0,
                label: '',
                value: '',
                color: '',
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

                const colors = [
                    '#10b981',
                    '#f43f5e',
                    '#0284c7',
                    '#64748b',
                    '#f59e0b',
                ];

                chart = new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',

                    data: {
                        labels: payload.labels,

                        datasets: [
                            {
                                data: payload.counts,
                                backgroundColor: colors,
                                borderWidth: 3,
                                borderColor: '#ffffff',
                                borderRadius: 6,
                                spacing: 3,
                                hoverOffset: 6,
                            },
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '76%',

                        plugins: {
                            legend: {
                                display: false,
                            },

                            tooltip: {
                                enabled: false,

                                external: (context) => {
                                    this.externalTooltip(
                                        context,
                                        payload,
                                        colors
                                    );
                                },
                            },
                        },
                    },
                });
            },

            externalTooltip(context, payload, colors) {
                const tooltipModel = context.tooltip;

                if (!tooltipModel || tooltipModel.opacity === 0) {
                    this.tooltip.show = false;
                    return;
                }

                const index = tooltipModel.dataPoints?.[0]?.dataIndex;

                this.tooltip = {
                    show: true,
                    x: tooltipModel.caretX,
                    y: tooltipModel.caretY - 12,
                    label: payload.labels[index] ?? '',
                    value: `${payload.counts[index] ?? 0} runs`,
                    color: colors[index] ?? '#a1a1aa',
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
