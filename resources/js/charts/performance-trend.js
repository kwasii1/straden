document.addEventListener("alpine:init", () => {
    Alpine.data("performanceTrend", (payload) => {
        // IMPORTANT:
        // Keep Chart.js OUTSIDE Alpine's reactive/proxy system.
        let chart = null;

        const { series, fill } = window.StradenCharts;

        return {
            visible: {
                p95: true,
                p99: true,
            },

            colors: {
                p95: series[0],
                p99: series[1],
            },

            tooltip: {
                show: false,
                x: 0,
                y: 0,
                label: "",
                rows: [],
            },

            init() {
                const canvas = this.$refs.canvas;

                if (!canvas || !payload.labels?.length) {
                    return;
                }

                // Destroy any Chart.js instance already attached
                // to this canvas.
                const existing = Chart.getChart(canvas);

                if (existing) {
                    existing.destroy();
                }

                chart = new Chart(canvas.getContext("2d"), {
                    type: "line",

                    data: {
                        labels: payload.labels,

                        datasets: [
                            {
                                key: "p95",
                                label: "p95",
                                data: payload.p95,
                                borderColor: this.colors.p95,
                                backgroundColor: fill(this.colors.p95, 0.08),
                                fill: true,
                            },
                            {
                                key: "p99",
                                label: "p99",
                                data: payload.p99,
                                borderColor: this.colors.p99,
                                backgroundColor: this.colors.p99,
                                fill: false,
                            },
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,

                        interaction: {
                            intersect: false,
                            mode: "index",
                        },

                        scales: {
                            y: {
                                beginAtZero: true,
                                border: {
                                    display: false,
                                },
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
                                grid: {
                                    display: false,
                                },
                                border: {
                                    display: false,
                                },
                                ticks: {
                                    autoSkipPadding: 12,
                                    maxRotation: 0,
                                    minRotation: 0,

                                    callback: function (value, index) {
                                        const label =
                                            this.getLabelForValue(value);

                                        // Hide consecutive duplicate dates
                                        if (index > 0) {
                                            const previous =
                                                this.getLabelForValue(
                                                    this.getTicks()[index - 1]
                                                        .value,
                                                );

                                            if (label === previous) {
                                                return "";
                                            }
                                        }

                                        return label;
                                    },
                                },
                            },
                        },

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

                const points = tooltipModel.dataPoints ?? [];

                this.tooltip = {
                    show: true,
                    x: tooltipModel.caretX,
                    y: tooltipModel.caretY,
                    label: points[0]?.label ?? "",
                    rows: points.map((p) => ({
                        label: p.dataset.label,
                        value: `${p.formattedValue} ms`,
                        color: p.dataset.borderColor,
                    })),
                };
            },

            toggle(key) {
                if (!chart) {
                    return;
                }

                // Only Alpine state is reactive.
                this.visible[key] = !this.visible[key];

                const index = chart.data.datasets.findIndex(
                    (dataset) => dataset.key === key,
                );

                if (index === -1) {
                    return;
                }

                chart.setDatasetVisibility(index, this.visible[key]);

                chart.update();
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
