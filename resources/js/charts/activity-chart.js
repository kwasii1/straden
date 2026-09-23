document.addEventListener('alpine:init', () => {
    Alpine.data('activityChart', (activity) => ({
        chart: null,
        tooltip: { show: false, x: 0, y: 0, label: '', value: 0 },

        init() {
            const ctx = this.$refs.canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 200);
            gradient.addColorStop(0, '#3b82f6');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.05)');

            this.chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: activity.map(d => d.label),
                    datasets: [{
                        data: activity.map(d => d.count),
                        backgroundColor: gradient,
                        hoverBackgroundColor: '#2563eb',
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 42,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 300 },
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: false,
                            external: (context) => this.externalTooltip(context, activity),
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#a1a1aa', font: { size: 10 } },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f4f4f5' },
                            ticks: { color: '#a1a1aa', font: { size: 10 }, stepSize: 1, precision: 0 },
                        },
                    },
                },
            });
        },

        externalTooltip(context, activity) {
            const tooltipModel = context.tooltip;

            if (!tooltipModel || tooltipModel.opacity === 0) {
                this.tooltip.show = false;
                return;
            }

            const index = tooltipModel.dataPoints?.[0]?.dataIndex;
            const day = activity[index];

            this.tooltip = {
                show: true,
                x: tooltipModel.caretX,
                y: tooltipModel.caretY - 12,
                label: day?.full ?? '',
                value: day?.count ?? 0,
            };
        },

        updateData(newActivity) {
            activity = newActivity;
            this.chart.data.labels = newActivity.map(d => d.label);
            this.chart.data.datasets[0].data = newActivity.map(d => d.count);
            this.chart.update();
        },

        destroy() {
            this.chart?.destroy();
        },
    }));
});
