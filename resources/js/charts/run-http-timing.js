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

                const series = [
                    { key: 'blocked', label: 'Blocked', borderColor: '#a78bfa', backgroundColor: 'rgba(167, 139, 250, 0.55)' },
                    { key: 'connecting', label: 'Connecting', borderColor: '#60a5fa', backgroundColor: 'rgba(96, 165, 250, 0.55)' },
                    { key: 'tls', label: 'TLS', borderColor: '#22d3ee', backgroundColor: 'rgba(34, 211, 238, 0.55)' },
                    { key: 'sending', label: 'Sending', borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.55)' },
                    { key: 'waiting', label: 'Waiting', borderColor: '#34d399', backgroundColor: 'rgba(52, 211, 153, 0.55)' },
                    { key: 'receiving', label: 'Receiving', borderColor: '#f87171', backgroundColor: 'rgba(248, 113, 113, 0.55)' },
                ];

                chart = new Chart(canvas.getContext('2d'), {
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
                                grid: { color: 'rgba(161, 161, 170, 0.1)' },
                                ticks: {
                                    color: '#a1a1aa',
                                    font: { size: 11 },
                                    callback: (value) => `${value} ms`,
                                },
                            },
                            x: {
                                stacked: true,
                                grid: { display: false },
                                ticks: { color: '#a1a1aa', font: { size: 11 }, maxTicksLimit: 8 },
                            },
                        },

                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: { padding: 12, usePointStyle: true, pointStyle: 'line', color: '#a1a1aa' },
                            },
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
