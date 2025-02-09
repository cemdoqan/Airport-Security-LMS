document.addEventListener('DOMContentLoaded', function() {
    // İlerleme çubuğu animasyonu
    const progressBars = document.querySelectorAll('.progress-bar .progress');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });

    // Görev işaretleme işlevi
    const taskCheckboxes = document.querySelectorAll('.task-checkbox');
    taskCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const taskId = this.dataset.taskId;
            if (this.checked) {
                // AJAX ile sunucuya bildir
                fetch('../api/complete_task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ task_id: taskId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Görsel feedback
                        this.closest('.task-item').classList.add('completed');
                    } else {
                        this.checked = false;
                        alert('Görev tamamlanamadı. Lütfen tekrar deneyin.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.checked = false;
                });
            }
        });
    });

    // Haftalık ilerleme grafiği
    fetch('../api/weekly_progress.php')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('weeklyProgressChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Tamamlanan Dersler',
                        data: data.values,
                        backgroundColor: 'rgba(99, 102, 241, 0.5)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });

    // Zaman grafiği
    fetch('../api/time_spent.php')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('timeSpentChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Çalışma Süresi (Saat)',
                        data: data.values,
                        fill: false,
                        borderColor: 'rgba(79, 70, 229, 1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });

    // İstatistik sayaç animasyonu
    const countUpAnimation = (element, target) => {
        let current = 0;
        const increment = target / 50; // 50 adımda hedefe ulaş
        const interval = 20; // 20ms aralıklarla güncelle

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = Math.round(target);
                clearInterval(timer);
            } else {
                element.textContent = Math.round(current);
            }
        }, interval);
    };

    // İstatistik kartlarındaki sayıları animasyonlu göster
    document.querySelectorAll('.stat-card .stat-value').forEach(stat => {
        const target = parseInt(stat.textContent);
        stat.textContent = '0';
        countUpAnimation(stat, target);
    });
});