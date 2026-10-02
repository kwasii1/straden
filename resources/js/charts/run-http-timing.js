document.addEventListener('alpine:init', () => {
    Alpine.data('runHttpTimingChart', (payload) => {
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

                // Request phases in fixed categorical order, stacked bottom-up.
                const groups = [
                    { key: 'blocked', label: 'Blocked', color: series[0] },
                    { key: 'connecting', label: 'Connecting', color: series[1] },
                    { key: 'tls', label: 'TLS', color: series[2] },
                    { key: 'sending', label: 'Sending', color: series[3] },
                    { key: 'waiting', label: 'Waiting', color: series[4] },
                    { key: 'receiving', label: 'Receiving', color: series[5] },
                ];

                chart = new Chart(canvas.getContext('2d'), {
                    type: 'line',

                    data: {
                        labels: payload.labels,

                        datasets: groups.map((group, index) => ({
                            label: group.label,
                            data: payload[group.key] ?? [],
                            borderColor: group.color,
                            backgroundColor: fill(group.color),
                            pointBackgroundColor: group.color,
                            pointHoverBorderColor: ink.surface,
                            pointHoverBorderWidth: 2,
                            // Fill each band down to the one below it so stacked areas never overlap.
                            fill: index === 0 ? 'origin' : '-1',
                        })),
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
                                stacked: true,
                                beginAtZero: true,
                                border: { display: false },
                                ticks: {
                                    maxTicksLimit: 5,
                                    callback: (value) => `${value} ms`,
                                },
                            },
                            x: {
                                stacked: true,
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
