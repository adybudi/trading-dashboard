@props(['data' => []])

<div 
    x-data="{
        chart: null,
        data: @js($data),
        initChart() {
            const isDark = document.documentElement.classList.contains('dark');
            const dates = this.data.map(d => d.date);
            const balances = this.data.map(d => d.balance);

            const options = {
                series: [{
                    name: 'Account Equity ($)',
                    data: balances
                }],
                chart: {
                    type: 'area',
                    height: 280,
                    toolbar: { show: false },
                    zoom: { enabled: true },
                    background: 'transparent',
                    fontFamily: 'JetBrains Mono, monospace'
                },
                colors: ['#3B82F6'],
                dataLabels: { enabled: false },
                stroke: {
                    curve: 'smooth',
                    width: 2.5
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: isDark ? 0.35 : 0.25,
                        opacityTo: 0.02,
                        stops: [0, 95, 100]
                    }
                },
                grid: {
                    borderColor: isDark ? '#1A222D' : '#E2E8F0',
                    strokeDashArray: 3,
                    xaxis: { lines: { show: false } },
                    yaxis: { lines: { show: true } }
                },
                xaxis: {
                    categories: dates,
                    labels: {
                        style: {
                            colors: isDark ? '#64748B' : '#94A3B8',
                            fontSize: '10px'
                        },
                        rotate: -20,
                        hideOverlappingLabels: true
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: isDark ? '#64748B' : '#94A3B8',
                            fontSize: '10px'
                        },
                        formatter: (val) => '$' + Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
                    }
                },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    x: { show: true },
                    y: {
                        formatter: (val) => '$' + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    }
                }
            };

            if (this.chart) {
                this.chart.destroy();
            }

            this.chart = new window.ApexCharts(this.$refs.chartContainer, options);
            this.chart.render();
        }
    }"
    x-init="
        $nextTick(() => initChart());
        window.addEventListener('theme-changed', () => {
            initChart();
        });
        window.addEventListener('trade-updated', () => location.reload());
        window.addEventListener('trade-created', () => location.reload());
    "
    class="w-full"
>
    <div x-ref="chartContainer" class="w-full"></div>
</div>
