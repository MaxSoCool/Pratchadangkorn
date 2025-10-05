document.addEventListener('DOMContentLoaded', function() {
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    let myDashboardChart = null; // Changed name for clarity

    function loadDashboardChart() {
        const predefinedRange = getUrlParameter('predefined_range');
        const specificYear = getUrlParameter('specific_year');
        const specificMonth = getUrlParameter('specific_month');
        const specificDay = getUrlParameter('specific_day');
        const faDeIdGlobal = getUrlParameter('fa_de_id_global'); // Global filter
        const chartSortMode = getUrlParameter('chart_sort_mode') || 'faculty_overview'; // New chart sorting mode
        const drilldownType = getUrlParameter('drilldown_type'); // Kept for consistency, but drilldown is off
        const drilldownId = getUrlParameter('drilldown_id');     // Kept for consistency, but drilldown is off

        let params = new URLSearchParams();
        if (predefinedRange) params.append('predefined_range', predefinedRange);
        if (specificYear) params.append('specific_year', specificYear);
        if (specificMonth) params.append('specific_month', specificMonth);
        if (specificDay) params.append('specific_day', specificDay);
        if (faDeIdGlobal) params.append('fa_de_id_global', faDeIdGlobal);
        params.append('chart_sort_mode', chartSortMode); // Always send chart_sort_mode
        if (drilldownType) params.append('drilldown_type', drilldownType); // Still pass if in URL
        if (drilldownId) params.append('drilldown_id', drilldownId);       // Still pass if in URL

        fetch('./api/chart.php?' + params.toString())
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                const chartCanvas = document.getElementById('dashboardChartCanvas');
                const chartCanvasParent = chartCanvas ? chartCanvas.parentNode : null;
                const chartTitleElement = document.getElementById('chartTitle');

                if (data.error) {
                    console.error("Error fetching chart data:", data.error);
                    if (chartCanvasParent) {
                        if (myDashboardChart) { myDashboardChart.destroy(); }
                        chartCanvasParent.innerHTML = '<div class="alert alert-danger mb-0">ไม่สามารถโหลดข้อมูลกราฟได้: ' + data.error + '</div>';
                        if (chartTitleElement) chartTitleElement.textContent = 'ไม่สามารถโหลดข้อมูลกราฟได้';
                    }
                    return;
                }

                // Update chart title and add filter indicator if present
                if (chartTitleElement) {
                    chartTitleElement.innerHTML = data.chart_title + 
                        (data.extra_title_line ? '<br><small class="text-muted">' + data.extra_title_line + '</small>' : '');
                }

                if (!chartCanvas) {
                    return; // Exit if canvas not found
                }
                
                // If no data, destroy chart and show message
                const hasNoData = data.labels.length === 0 || 
                                  data.datasets.length === 0 || 
                                  data.datasets.every(dataset => dataset.data.every(val => val === 0));

                if (hasNoData) {
                    if (myDashboardChart) {
                        myDashboardChart.destroy();
                    }
                    if (chartCanvasParent) {
                        let existingAlert = chartCanvasParent.querySelector('.alert');
                        if (!existingAlert) {
                            existingAlert = document.createElement('div');
                            existingAlert.className = 'alert alert-info text-center py-3 mb-0';
                            chartCanvasParent.appendChild(existingAlert);
                        }
                        existingAlert.textContent = 'ไม่พบข้อมูลสำหรับเงื่อนไขที่เลือก';
                        chartCanvas.style.display = 'none'; // Hide the canvas
                    }
                    return;
                } else {
                    // If data exists, ensure canvas is visible and any "no data" alert is removed
                    if (chartCanvas) {
                        chartCanvas.style.display = 'block';
                    }
                    const existingAlert = chartCanvasParent ? chartCanvasParent.querySelector('.alert') : null;
                    if (existingAlert) {
                        existingAlert.remove();
                    }
                }

                if (myDashboardChart) {
                    myDashboardChart.destroy();
                }

                const ctx = document.getElementById('dashboardChartCanvas');

                let chartType = 'bar'; // Always a bar chart for these modes

                let chartOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: false, // For grouped bars, x-axis labels are not stacked
                            title: {
                                display: true,
                                text: ''
                            }
                        },
                        y: {
                            stacked: false, // For grouped bars, y-axis values are not stacked
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'จำนวน'
                            },
                            ticks: {
                                callback: function(value) {
                                    if (Number.isInteger(value)) {
                                        return value;
                                    }
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false, // Show tooltips for all bars at that index
                            callbacks: {
                                title: function(tooltipItems) {
                                    return tooltipItems[0].label;
                                },
                                label: function(tooltipItem) {
                                    return tooltipItem.dataset.label + ': ' + tooltipItem.formattedValue;
                                },
                                afterBody: function(tooltipItems) {
                                    // Add extra info for top_facilities and top_equipments
                                    if ((data.chart_mode === 'top_facilities' || data.chart_mode === 'top_equipments') && data.extra_info && tooltipItems.length > 0) {
                                        const itemIndex = tooltipItems[0].dataIndex;
                                        const itemInfo = data.extra_info[itemIndex];
                                        if (itemInfo) {
                                            if (data.chart_mode === 'top_facilities') {
                                                return ['อาคาร: ' + itemInfo.building_name];
                                            } else if (data.chart_mode === 'top_equipments') {
                                                return ['หน่วยวัด: ' + itemInfo.measure];
                                            }
                                        }
                                    }
                                    return null;
                                }
                            }
                        }
                    },
                    // No onClick handler as per the latest requirement (no drilldown)
                };

                // Adjust options based on chart mode
                if (data.chart_mode === 'faculty_overview') {
                    chartOptions.scales.x.title.text = 'คณะ/หน่วยงาน';
                    chartOptions.scales.x.stacked = false;
                    chartOptions.scales.y.stacked = false;
                } else if (data.chart_mode === 'top_facilities' || data.chart_mode === 'top_equipments') {
                    chartOptions.scales.x.title.text = data.chart_mode === 'top_facilities' ? 'สถานที่' : 'อุปกรณ์';
                    // For Grouped Bar Chart, both x and y are NOT stacked
                    chartOptions.scales.x.stacked = false; 
                    chartOptions.scales.y.stacked = false; 
                } else if (data.chart_mode === 'drilldown_facility_by_faculty' || data.chart_mode === 'drilldown_equipment_by_faculty') {
                    // This mode should not be reached by UI clicks, but if accessed directly, it's a simple bar chart
                    chartOptions.scales.x.title.text = 'คณะ/หน่วยงาน';
                    chartOptions.scales.x.stacked = false;
                    chartOptions.scales.y.stacked = false;
                } else if (data.chart_mode === 'faculty_drilldown') {
                    // This mode should not be reached by UI clicks
                    chartOptions.scales.x.title.text = 'ประเภท';
                    chartOptions.scales.x.stacked = false;
                    chartOptions.scales.y.stacked = false;
                }

                myDashboardChart = new Chart(ctx, {
                    type: chartType,
                    data: {
                        labels: data.labels,
                        datasets: data.datasets
                    },
                    options: chartOptions
                });
            })
            .catch(error => {
                console.error('Error loading chart:', error);
                const chartCanvas = document.getElementById('dashboardChartCanvas');
                const chartCanvasParent = chartCanvas ? chartCanvas.parentNode : null;
                if (chartCanvasParent) {
                    const chartTitleElement = document.getElementById('chartTitle');
                    if (chartTitleElement) {
                        chartTitleElement.textContent = 'ไม่สามารถโหลดข้อมูลกราฟได้';
                    }
                    if (myDashboardChart) { myDashboardChart.destroy(); }
                    chartCanvasParent.innerHTML = '<div class="alert alert-danger mb-0">ไม่สามารถโหลดข้อมูลกราฟได้ โปรดลองอีกครั้งภายหลัง</div>';
                }
            });
    }

    // Call loadDashboardChart initially if on dashboard tab
    if (getUrlParameter('main_tab') === 'dashboard_admin' || getUrlParameter('main_tab') === '') {
        loadDashboardChart();
    }
});