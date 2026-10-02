document.addEventListener('alpine:init', () => {
    Alpine.data('runResponseCodesChart', (payload) => {
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

                const { status, fill, ink } = window.StradenCharts;

                // Status classes carry meaning: success, redirect, client error, server error.
                const groups = [
                    { key: '2xx', label: '2xx', color: status.good },
                    { key: '3xx', label: '3xx', color: status.neutral },
                    { key: '4xx', label: '4xx', color: status.warning },
                    { key: '5xx', label: '5xx', color: status.critical },
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
                                    precision: 0,
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
