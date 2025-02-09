document.addEventListener('DOMContentLoaded', function() {
    // İstatistik sayaçları için animasyon
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        animateCounter(stat, finalValue);
    });

    // İlerleme çubuklarını animasyonlu göster
    const progressBars = document.querySelectorAll('.progress-bar .progress');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });

    // Haftalık aktivite grafiği
    const ctx = document.getElementById('weeklyActivityChart').getContext('2d');
    const dates = Object.keys(activityData);
    const completedLessons = dates.map(date => activityData[date].completed);
    const timeSpent = dates.map(date => Math.round(activityData[date].time_spent / 60)); // Dakikayı saate çevir

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dates.map(date => formatDate(date)),
            datasets: [
                {
                    label: 'Tamamlanan Ders',
                    data: completedLessons,
                    backgroundColor: 'rgba(99, 102, 241, 0.5)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    yAxisID: 'y-lessons'
                },
                {
                    label: 'Çalışma Süresi (Saat)',
                    data: timeSpent,
                    type: 'line',
                    borderColor: 'rgba(234, 88, 12, 1)',
                    backgroundColor: 'rgba(234, 88, 12, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    yAxisID: 'y-time'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                'y-lessons': {
                    type: 'linear',
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Tamamlanan Ders'
                    },
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                },
                'y-time': {
                    type: 'linear',
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Çalışma Süresi (Saat)'
                    },
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
});

// Yardımcı fonksiyonlar
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 50;
    const duration = 1000;
    const stepTime = duration / 50;

    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = Math.round(target);
            clearInterval(timer);
        } else {
            element.textContent = Math.round(current);
        }
    }, stepTime);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short'
    });
}