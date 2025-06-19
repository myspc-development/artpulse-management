<canvas id="apSignupChart" height="120"></canvas>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('apSignupChart').getContext('2d');

    const labels = <?php
        echo json_encode(array_map(function($i) {
            return date('M Y', strtotime("-$i months"));
        }, range(5, 0)));
    ?>;

    const signupData = <?php echo json_encode(\ArtPulse\Admin\SettingsPage::getMonthlySignupsByLevel()); ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Free',
                    data: signupData.free || [],
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                },
                {
                    label: 'Pro',
                    data: signupData.pro || [],
                    backgroundColor: 'rgba(153, 102, 255, 0.6)'
                },
                {
                    label: 'Org',
                    data: signupData.org || [],
                    backgroundColor: 'rgba(255, 159, 64, 0.6)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Signups by Membership Level'
                },
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>
