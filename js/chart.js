document.addEventListener('DOMContentLoaded', function() {
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    let myFacultyRequestsChart = null; 

    function loadDashboardChart() {
        const predefinedRange = getUrlParameter('predefined_range');
        const specificYear = getUrlParameter('specific_year');
        const specificMonth = getUrlParameter('specific_month');
        const specificDay = getUrlParameter('specific_day');
        const faDeId = getUrlParameter('fa_de_id'); // Get the selected faculty ID

        let params = new URLSearchParams();
        if (predefinedRange) params.append('predefined_range', predefinedRange);
        if (specificYear) params.append('specific_year', specificYear);
        if (specificMonth) params.append('specific_month', specificMonth);
        if (specificDay) params.append('specific_day', specificDay);
        if (faDeId) params.append('fa_de_id', faDeId); // Add faculty ID to params

        fetch('./api/chart.php?' + params.toString())
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                const chartContainer = document.getElementById('facultyRequestsChart').closest('.card');
                if (data.error) {
                    console.error("Error fetching chart data:", data.error);
                    if (chartContainer) {
                        chartContainer.innerHTML = '<div class="alert alert-danger mb-0">ไม่สามารถโหลดข้อมูลกราฟได้: ' + data.error + '</div>';
                    }
                    return;
                }
                if (data.labels.length === 0) {
                    if (chartContainer) {
                        chartContainer.innerHTML = '<div class="alert alert-info text-center py-3 mb-0">ไม่พบข้อมูลสำหรับเงื่อนไขที่เลือก</div>';
                    }
                    return;
                }

                const ctx = document.getElementById('facultyRequestsChart');
                if (!ctx) {
                    return; // Exit if canvas not found
                }

                if (myFacultyRequestsChart) {
                    myFacultyRequestsChart.destroy();
                }

                myFacultyRequestsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'จำนวนโครงการ',
                            data: data.projects,
                            backgroundColor: '#0d6efd',
                            borderColor: '#0149b6ff',
                            borderWidth: 1
                        }, {
                            label: 'จำนวนคำร้องขอสถานที่',
                            data: data.facilities,
                            backgroundColor: '#198754', 
                            borderColor: '#084a2bff',
                            borderWidth: 1
                        }, {
                            label: 'จำนวนคำร้องขอใช้อุปกรณ์',
                            data: data.equipments,
                            backgroundColor: '#ffc107',
                            borderColor: 'rgba(182, 93, 4, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Allow canvas to resize freely
                        scales: {
                            x: {
                                stacked: true,
                                title: {
                                    display: true,
                                    text: 'คณะ/หน่วยงาน'
                                }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'จำนวน'
                                },
                                ticks: {
                                    // Ensure y-axis labels are integers
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
                                intersect: false
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error loading chart:', error);
                const chartContainer = document.getElementById('facultyRequestsChart').closest('.card');
                if (chartContainer) {
                    chartContainer.innerHTML = '<div class="alert alert-danger mb-0">ไม่สามารถโหลดข้อมูลกราฟได้ โปรดลองอีกครั้งภายหลัง</div>';
                }
            });
    }

    if (getUrlParameter('main_tab') === 'dashboard_admin' || getUrlParameter('main_tab') === '') {
        loadDashboardChart();
    }
});