document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('reportChart').getContext('2d');
    let reportChart;

    // Rapor verilerini alma
    function getReportData() {
        const tableRows = document.querySelectorAll('.report-table tbody tr');
        const labels = [];
        const values = [];
        const reportType = document.getElementById('report_type').value;

        tableRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            labels.push(cells[0].textContent);
            
            // Rapor tipine göre hangi veriyi grafiğe ekleyeceğimizi belirleme
            switch(reportType) {
                case 'user_progress':
                    values.push(parseInt(cells[1].textContent)); // Tamamlanan dersler
                    break;
                case 'course_engagement':
                    values.push(parseInt(cells[1].textContent)); // Toplam öğrenci
                    break;
                case 'assessment_results':
                    values.push(parseFloat(cells[2].textContent)); // Ortalama puan
                    break;
            }
        });

        return { labels, values };
    }

    // Grafiği oluşturma
    function createChart() {
        const { labels, values } = getReportData();
        const reportType = document.getElementById('report_type').value;
        
        let chartTitle = '';
        switch(reportType) {
            case 'user_progress':
                chartTitle = 'Kullanıcı Bazlı Tamamlanan Ders Sayısı';
                break;
            case 'course_engagement':
                chartTitle = 'Kurs Bazlı Öğrenci Sayısı';
                break;
            case 'assessment_results':
                chartTitle = 'Değerlendirme Bazlı Ortalama Puanlar';
                break;
        }

        // Eğer zaten bir grafik varsa onu yok et
        if (reportChart) {
            reportChart.destroy();
        }

        // Yeni grafiği oluştur
        reportChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: chartTitle,
                    data: values,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: chartTitle
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Raporu dışa aktarma
    function exportReport() {
        const reportType = document.getElementById('report_type').value;
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        // CSV formatında veriyi oluştur
        let csv = [];
        const headers = Array.from(document.querySelectorAll('.report-table thead th'))
            .map(th => th.textContent);
        csv.push(headers.join(','));

        const rows = document.querySelectorAll('.report-table tbody tr');
        rows.forEach(row => {
            const rowData = Array.from(row.querySelectorAll('td'))
                .map(td => `"${td.textContent.replace(/"/g, '""')}"`);
            csv.push(rowData.join(','));
        });

        // CSV dosyasını indir
        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const fileName = `rapor_${reportType}_${startDate}_${endDate}.csv`;
        
        if (navigator.msSaveBlob) { // IE 10+
            navigator.msSaveBlob(blob, fileName);
        } else {
            link.href = window.URL.createObjectURL(blob);
            link.download = fileName;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    // Event listeners
    createChart(); // İlk yükleme

    document.getElementById('report_type').addEventListener('change', function() {
        this.closest('form').submit();
    });

    document.getElementById('exportReport').addEventListener('click', exportReport);
});