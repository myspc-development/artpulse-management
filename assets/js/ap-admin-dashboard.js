document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined' || typeof APAdminStats === 'undefined') return;

    const ctx = document.getElementById('ap-signup-chart');
    if (!ctx) return;

    const data = {
        labels: APAdminStats.months,
        datasets: [
            {
                label: 'Pro Signups',
                data: APAdminStats.pro,
                backgroundColor: '#0073aa'
            },
            {
                label: 'Org Signups',
                data: APAdminStats.org,
                backgroundColor: '#46b450'
            },
            {
                label: 'Free Signups',
                data: APAdminStats.free,
                backgroundColor: '#d54e21'
            }
        ]
    };

    const config = {
        type: 'bar',
        data: data,
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: {
                    display: true,
                    text: 'Monthly Member Signups by Level'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    };

    new Chart(ctx, config);
});
