document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('apEngagementChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    // Example dummy data – this should be passed inline via PHP in the dashboard HTML
    const weeklyLabels = ["6d ago", "5d", "4d", "3d", "2d", "1d", "Today"];
    const loginData = window.apWeeklyLogins || [0, 1, 2, 1, 3, 2, 4];
    const uploadData = window.apWeeklyUploads || [1, 0, 2, 1, 2, 3, 1];

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: weeklyLabels,
            datasets: [
                {
                    label: 'Logins',
                    data: loginData,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderWidth: 1
                },
                {
                    label: 'Uploads',
                    data: uploadData,
                    backgroundColor: 'rgba(255, 206, 86, 0.6)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
});
