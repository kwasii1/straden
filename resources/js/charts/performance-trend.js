document.addEventListener("alpine:init", () => {
    Alpine.data("performanceTrend", (payload) => {
        // IMPORTANT:
        // Keep Chart.js OUTSIDE Alpine's reactive/proxy system.
        let chart = null;

        return {
            visible: {
                p95: true,
                p99: true,
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

                const ctx = canvas.getContext("2d");

                const gP95 = ctx.createLinearGradient(0, 0, 0, 240);
                gP95.addColorStop(0, "rgba(2, 132, 199, 0.2)");
                gP95.addColorStop(1, "rgba(2, 132, 199, 0.0)");

                const gP99 = ctx.createLinearGradient(0, 0, 0, 240);
                gP99.addColorStop(0, "rgba(244, 63, 94, 0.15)");
                gP99.addColorStop(1, "rgba(244, 63, 94, 0.0)");

                chart = new Chart(ctx, {
                    type: "line",

                    data: {
                        labels: payload.labels,

                        datasets: [
                            {
                                key: "p95",
                                label: "p95 (ms)",
                                data: payload.p95,
                                borderColor: "#0284c7",
                                backgroundColor: gP95,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 0,
                                pointHoverRadius: 5,
                            },
                            {
                                key: "p99",
                                label: "p99 (ms)",
                                data: payload.p99,
                                borderColor: "#f43f5e",
                                backgroundColor: gP99,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.35,
                                pointRadius: 0,
                                pointHoverRadius: 5,
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
                                grid: {
                                    color: "rgba(161, 161, 170, 0.1)",
                                },
                                ticks: {
                                    color: "#a1a1aa",
                                    font: {
                                        size: 11,
                                    },
                                    stepSize: 10000,
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
                                ticks: {
                                    color: "#a1a1aa",
                                    font: {
                                        size: 11,
                                    },
                                    autoSkip: false,
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
                    y: tooltipModel.caretY - 12,
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
