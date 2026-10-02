document.addEventListener('alpine:init', () => {
    Alpine.data('activityChart', (activity) => ({
        chart: null,
        tooltip: { show: false, x: 0, y: 0, label: '', rows: [] },

        init() {
            const { series } = window.StradenCharts;

            this.chart = new Chart(this.$refs.canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: activity.map(d => d.label),
                    datasets: [{
                        label: 'Runs',
                        data: activity.map(d => d.count),
                        backgroundColor: series[0],
                        hoverBackgroundColor: '#2f49c4',
                        borderRadius: { topLeft: 4, topRight: 4 },
                        borderSkipped: 'bottom',
                        maxBarThickness: 28,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
                            border: { display: false },
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            ticks: { stepSize: 1, precision: 0, maxTicksLimit: 5 },
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

            const day = activity[tooltipModel.dataPoints?.[0]?.dataIndex];

            this.tooltip = {
                show: true,
                x: tooltipModel.caretX,
                y: tooltipModel.caretY,
                label: day?.full ?? '',
                rows: [{ label: 'Runs', value: day?.count ?? 0, color: window.StradenCharts.series[0] }],
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
