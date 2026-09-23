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

                const ctx = canvas.getContext('2d');

                const series = [
                    { key: '2xx', label: '2xx', borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.55)' },
                    { key: '3xx', label: '3xx', borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.55)' },
                    { key: '4xx', label: '4xx', borderColor: '#f97316', backgroundColor: 'rgba(249, 115, 22, 0.55)' },
                    { key: '5xx', label: '5xx', borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.55)' },
                ];

                chart = new Chart(ctx, {
                    type: 'line',

                    data: {
                        labels: payload.labels,

                        datasets: series.map((s) => ({
                            label: s.label,
                            data: payload[s.key] ?? [],
                            borderColor: s.borderColor,
                            backgroundColor: s.backgroundColor,
                            borderWidth: 1,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 4,
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
                                stacked: true,
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
