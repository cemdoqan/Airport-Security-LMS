document.addEventListener('DOMContentLoaded', function() {
    // Arama ve filtreleme elementlerini seç
    const searchInput = document.getElementById('courseSearch');
    const departmentFilter = document.getElementById('departmentFilter');
    const progressFilter = document.getElementById('progressFilter');
    const courseCards = document.querySelectorAll('.my-courses .course-card');

    // Filtreleme fonksiyonu
    function filterCourses() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedDepartment = departmentFilter.value.toLowerCase();
        const selectedProgress = progressFilter.value;

        courseCards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const description = card.querySelector('.course-description').textContent.toLowerCase();
            const department = card.dataset.department.toLowerCase();
            const progress = card.dataset.progress;

            const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
            const matchesDepartment = !selectedDepartment || department === selectedDepartment;
            const matchesProgress = !selectedProgress || progress === selectedProgress;

            if (matchesSearch && matchesDepartment && matchesProgress) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });

        // "Sonuç bulunamadı" mesajını göster/gizle
        const noResults = document.querySelector('.no-results');
        const visibleCards = document.querySelectorAll('.my-courses .course-card[style="display: flex"]');
        
        if (visibleCards.length === 0) {
            if (!noResults) {
                const message = document.createElement('p');
                message.className = 'no-results';
                message.textContent = 'Aramanızla eşleşen kurs bulunamadı.';
                document.querySelector('.courses-grid').appendChild(message);
            }
        } else {
            if (noResults) {
                noResults.remove();
            }
        }
    }

    // Event listeners
    searchInput.addEventListener('input', filterCourses);
    departmentFilter.addEventListener('change', filterCourses);
    progressFilter.addEventListener('change', filterCourses);

    // Progress bar animasyonu
    const progressBars = document.querySelectorAll('.progress-bar .progress');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });

    // Sıralama fonksiyonu
    function sortCourses(criteria) {
        const coursesGrid = document.querySelector('.courses-grid');
        const cards = Array.from(courseCards);

        cards.sort((a, b) => {
            switch(criteria) {
                case 'progress':
                    return parseFloat(b.dataset.progress) - parseFloat(a.dataset.progress);
                case 'last_accessed':
                    const dateA = new Date(a.querySelector('small').textContent.replace('Son erişim: ', ''));
                    const dateB = new Date(b.querySelector('small').textContent.replace('Son erişim: ', ''));
                    return dateB - dateA;
                case 'title':
                    const titleA = a.querySelector('h3').textContent;
                    const titleB = b.querySelector('h3').textContent;
                    return titleA.localeCompare(titleB);
            }
        });

        cards.forEach(card => coursesGrid.appendChild(card));
    }

    // Kurs kartları için hover efekti
    courseCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            const progressBar = card.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.classList.add('hover');
            }
        });

        card.addEventListener('mouseleave', () => {
            const progressBar = card.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.classList.remove('hover');
            }
        });
    });
});