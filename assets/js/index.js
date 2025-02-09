document.addEventListener('DOMContentLoaded', function() {
    // İlerleme dairelerini oluştur
    const progressCircles = document.querySelectorAll('.progress-circle');
    progressCircles.forEach(circle => {
        const progress = circle.dataset.progress;
        const radius = 24; // SVG daire yarıçapı
        const circumference = 2 * Math.PI * radius;
        const offset = circumference - (progress / 100 * circumference);
        
        // SVG oluştur
        circle.innerHTML = `
            <svg class="progress-ring" width="60" height="60">
                <circle class="progress-ring-circle-bg" 
                        stroke="#e5e7eb" 
                        stroke-width="4" 
                        fill="transparent" 
                        r="${radius}" 
                        cx="30" 
                        cy="30"/>
                <circle class="progress-ring-circle" 
                        stroke="#4f46e5" 
                        stroke-width="4" 
                        fill="transparent" 
                        r="${radius}" 
                        cx="30" 
                        cy="30"
                        style="stroke-dasharray: ${circumference} ${circumference}; 
                               stroke-dashoffset: ${circumference};"/>
            </svg>
            <span class="progress-text">%${progress}</span>
        `;

        // Animasyon
        setTimeout(() => {
            const progressRing = circle.querySelector('.progress-ring-circle');
            progressRing.style.strokeDashoffset = offset;
        }, 100);
    });

    // Eğitim kayıt butonları için event listener
    const registerButtons = document.querySelectorAll('.btn-register');
    registerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const trainingId = this.dataset.trainingId;
            
            // AJAX ile kayıt isteği gönder
            fetch('../api/register_training.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ training_id: trainingId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Başarılı kayıt
                    this.textContent = 'Kayıtlı';
                    this.disabled = true;
                    this.classList.add('registered');
                } else {
                    // Hata durumu
                    alert(data.message || 'Kayıt işlemi başarısız oldu. Lütfen tekrar deneyin.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            });
        });
    });

    // Duyuru kartları için hover efekti
    const announcementCards = document.querySelectorAll('.announcement-card');
    announcementCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('hover');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('hover');
        });
    });
});