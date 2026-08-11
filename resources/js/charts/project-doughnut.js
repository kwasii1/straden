document.addEventListener("alpine:init", () => {
    Alpine.data("projectDoughnut", (rows) => ({
        chart: null,

        init() {
            const hexMap = {
                "bg-blue-500": "#3b82f6",
                "bg-violet-500": "#8b5cf6",
                "bg-amber-500": "#f59e0b",
                "bg-emerald-500": "#10b981",
                "bg-rose-500": "#f43f5e",
                "bg-zinc-400": "#a1a1aa",
            };

            this.chart = new Chart(this.$refs.canvas.getContext("2d"), {
                type: "doughnut",
                data: {
                    labels: rows.map((r) => r.name),
                    datasets: [
                        {
                            data: rows.map((r) => r.count),
                            backgroundColor: rows.map(
                                (r) => hexMap[r.color] ?? "#a1a1aa",
                            ),
                            borderWidth: 3,
                            borderColor: "#ffffff",
                            borderRadius: 6,
                            spacing: 3,
                            hoverOffset: 6,
                        },
                    ],
                },
                options: {
                    cutout: "72%",
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: false,
                            external: (context) =>
                                this.externalTooltip(context, rows, hexMap),
                        },
                    },
                },
            });
        },

        externalTooltip(context, rows, hexMap) {
            const tooltipModel = context.tooltip;

            if (!tooltipModel || tooltipModel.opacity === 0) {
                this.tooltip.show = false;
                return;
            }

            const index = tooltipModel.dataPoints?.[0]?.dataIndex;
            const row = rows[index];

            this.tooltip = {
                show: true,
                x: tooltipModel.caretX,
                y: tooltipModel.caretY - 12,
                label: row?.name ?? "",
                value: `${row?.count ?? 0} · ${row?.percent ?? 0}%`,
                color: hexMap[row?.color] ?? "#a1a1aa",
            };
        },

        updateData(newRows) {
            rows = newRows;
            this.chart.data.labels = newRows.map((r) => r.name);
            this.chart.data.datasets[0].data = newRows.map((r) => r.count);
            this.chart.update();
        },

        destroy() {
            this.chart?.destroy();
        },
    }));
});
