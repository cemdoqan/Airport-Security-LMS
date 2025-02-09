// X-Ray Simülatör JavaScript
class XRaySimulator {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.canvas = document.createElement('canvas');
        this.context = this.canvas.getContext('2d');
        this.markers = [];
        this.timer = null;
        this.startTime = null;
        this.currentSimulation = null;
        this.imageData = null;
        this.imageFilters = {
            brightness: 0,
            contrast: 1,
            invert: false,
            colorize: false,
            zoom: 1
        };
        
        this.init();
    }

    init() {
        this.setupCanvas();
        this.setupControls();
        this.setupEventListeners();
        this.setupToolbar();
    }

    setupCanvas() {
        this.container.appendChild(this.canvas);
        this.resizeCanvas();
        window.addEventListener('resize', () => this.resizeCanvas());
    }

    setupControls() {
        // Parlaklık kontrolü
        document.getElementById('brightness')?.addEventListener('input', (e) => {
            this.imageFilters.brightness = parseInt(e.target.value);
            this.applyFilters();
        });

        // Kontrast kontrolü
        document.getElementById('contrast')?.addEventListener('input', (e) => {
            this.imageFilters.contrast = parseFloat(e.target.value);
            this.applyFilters();
        });

        // Negatif görüntü
        document.getElementById('invert')?.addEventListener('change', (e) => {
            this.imageFilters.invert = e.target.checked;
            this.applyFilters();
        });

        // Renkli görüntü
        document.getElementById('colorize')?.addEventListener('change', (e) => {
            this.imageFilters.colorize = e.target.checked;
            this.applyFilters();
        });

        // Zoom kontrolleri
        document.getElementById('zoomIn')?.addEventListener('click', () => this.zoom(0.1));
        document.getElementById('zoomOut')?.addEventListener('click', () => this.zoom(-0.1));
    }

    setupEventListeners() {
        this.canvas.addEventListener('click', (e) => this.handleCanvasClick(e));
        
        // Sürükleme işlemleri için
        let isDragging = false;
        let lastX = 0;
        let lastY = 0;

        this.canvas.addEventListener('mousedown', (e) => {
            isDragging = true;
            lastX = e.clientX;
            lastY = e.clientY;
        });

        this.canvas.addEventListener('mousemove', (e) => {
            if (isDragging && this.imageFilters.zoom > 1) {
                const deltaX = e.clientX - lastX;
                const deltaY = e.clientY - lastY;
                this.pan(deltaX, deltaY);
                lastX = e.clientX;
                lastY = e.clientY;
            }
        });

        this.canvas.addEventListener('mouseup', () => {
            isDragging = false;
        });

        this.canvas.addEventListener('mouseleave', () => {
            isDragging = false;
        });
    }

    setupToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'toolbar';
        this.container.insertBefore(toolbar, this.canvas);

        // Tehdit türleri için butonlar
        const threatTypes = [
            { id: 'weapon', label: 'Silah', icon: '🔫' },
            { id: 'explosive', label: 'Patlayıcı', icon: '💣' },
            { id: 'knife', label: 'Bıçak', icon: '🔪' },
            { id: 'liquid', label: 'Sıvı', icon: '💧' },
            { id: 'other', label: 'Diğer', icon: '❓' }
        ];

        threatTypes.forEach(type => {
            const button = document.createElement('button');
            button.className = 'toolbar-button';
            button.dataset.threatType = type.id;
            button.innerHTML = `${type.icon} ${type.label}`;
            button.addEventListener('click', () => this.setActiveThreatType(type.id));
            toolbar.appendChild(button);
        });
    }

    resizeCanvas() {
        const containerWidth = this.container.clientWidth;
        this.canvas.width = containerWidth;
        this.canvas.height = containerWidth * 0.75; // 4:3 oranı
        this.drawImage();
    }

    loadImage(src) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => {
                this.currentImage = image;
                this.originalImageData = this.context.createImageData(image.width, image.height);
                this.context.drawImage(image, 0, 0);
                this.imageData = this.context.getImageData(0, 0, image.width, image.height);
                this.startTimer();
                resolve();
            };
            image.onerror = reject;
            image.src = src;
        });
    }

    startTimer() {
        this.startTime = Date.now();
        this.updateTimer();
        this.timer = setInterval(() => this.updateTimer(), 1000);
    }

    updateTimer() {
        const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        
        const timerDisplay = document.getElementById('timer');
        if (timerDisplay) {
            timerDisplay.textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        // Süre limiti kontrolü
        if (this.currentSimulation && elapsed >= this.currentSimulation.time_limit) {
            this.submitSimulation();
        }
    }

    handleCanvasClick(event) {
        const rect = this.canvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        
        // Marker ekle
        this.addMarker(x, y);
    }

    addMarker(x, y) {
        const activeType = document.querySelector('.toolbar-button.active')?.dataset.threatType || 'other';
        
        this.markers.push({
            x: x / this.canvas.width,  // Normalize coordinates
            y: y / this.canvas.height,
            type: activeType
        });

        this.drawMarkers();
    }

    drawMarkers() {
        this.drawImage(); // Clear and redraw image

        this.markers.forEach(marker => {
            const x = marker.x * this.canvas.width;
            const y = marker.y * this.canvas.height;

            // Draw circle
            this.context.beginPath();
            this.context.arc(x, y, 15, 0, 2 * Math.PI);
            this.context.strokeStyle = this.getMarkerColor(marker.type);
            this.context.lineWidth = 2;
            this.context.stroke();

            // Draw label
            this.context.fillStyle = this.getMarkerColor(marker.type);
            this.context.font = '12px Arial';
            this.context.fillText(this.getMarkerLabel(marker.type), x + 20, y);
        });
    }

    getMarkerColor(type) {
        const colors = {
            'weapon': '#ff0000',
            'explosive': '#ff6600',
            'knife': '#ff3300',
            'liquid': '#0066ff',
            'other': '#666666'
        };
        return colors[type] || '#000000';
    }

    getMarkerLabel(type) {
        const labels = {
            'weapon': 'Silah',
            'explosive': 'Patlayıcı',
            'knife': 'Bıçak',
            'liquid': 'Sıvı',
            'other': 'Diğer'
        };
        return labels[type] || type;
    }

    applyFilters() {
        if (!this.imageData) return;

        const filteredData = new ImageData(
            new Uint8ClampedArray(this.imageData.data),
            this.imageData.width,
            this.imageData.height
        );

        const data = filteredData.data;
        
        for (let i = 0; i < data.length; i += 4) {
            let r = data[i];
            let g = data[i + 1];
            let b = data[i + 2];

            // Parlaklık
            r += this.imageFilters.brightness;
            g += this.imageFilters.brightness;
            b += this.imageFilters.brightness;

            // Kontrast
            const factor = (259 * (this.imageFilters.contrast + 255)) / (255 * (259 - this.imageFilters.contrast));
            r = factor * (r - 128) + 128;
            g = factor * (g - 128) + 128;
            b = factor * (b - 128) + 128;

            // Negatif
            if (this.imageFilters.invert) {
                r = 255 - r;
                g = 255 - g;
                b = 255 - b;
            }

            // Renklendirme
            if (this.imageFilters.colorize) {
                const intensity = (r + g + b) / 3;
                if (intensity > 200) {
                    r = 255;
                    g = 50;
                    b = 50;
                } else if (intensity > 150) {
                    r = 50;
                    g = 255;
                    b = 50;
                }
            }

            data[i] = Math.min(255, Math.max(0, r));
            data[i + 1] = Math.min(255, Math.max(0, g));
            data[i + 2] = Math.min(255, Math.max(0, b));
        }

        this.context.putImageData(filteredData, 0, 0);
        this.drawMarkers();
    }

    zoom(delta) {
        this.imageFilters.zoom = Math.max(1, Math.min(3, this.imageFilters.zoom + delta));
        this.drawImage();
    }

    pan(deltaX, deltaY) {
        // Pan logic here
        this.drawImage();
    }

    drawImage() {
        if (!this.currentImage) return;

        this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        if (this.imageFilters.zoom === 1) {
            this.context.drawImage(this.currentImage, 0, 0, this.canvas.width, this.canvas.height);
        } else {
            // Implement zoomed drawing logic
            const zoomedWidth = this.canvas.width * this.imageFilters.zoom;
            const zoomedHeight = this.canvas.height * this.imageFilters.zoom;
            
            // Center the zoomed image
            const offsetX = (this.canvas.width - zoomedWidth) / 2;
            const offsetY = (this.canvas.height - zoomedHeight) / 2;
            
            this.context.drawImage(this.currentImage, offsetX, offsetY, zoomedWidth, zoomedHeight);
        }

        this.applyFilters();
    }

    submitSimulation() {
        clearInterval(this.timer);
        const timeTaken = Math.floor((Date.now() - this.startTime) / 1000);

        fetch('/api/simulations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                simulation_id: this.currentSimulation.id,
                markers: this.markers,
                time_taken: timeTaken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showResults(data.data);
            } else {
                throw new Error(data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Bir hata oluştu. Lütfen tekrar deneyin.');
        });
    }

    showResults(results) {
        const resultsPanel = document.createElement('div');
        resultsPanel.className = 'results-panel';
        resultsPanel.innerHTML = `
            <h3 class="results-title">Simülasyon Sonuçları</h3>
            
            <div class="score-display">
                <div class="score-value">${results.score}</div>
                <div class="score-label">Puan</div>
            </div>

            <div class="result-item">
                <span class="result-label">Doğru Tespitler:</span>
                <span class="result-value">${results.correct_detections}</span>
            </div>

            <div class="result-item">
                <span class="result-label">Yanlış Tespitler:</span>
                <span class="result-value">${results.false_positives}</span>
            </div>

            <div class="result-item">
                <span class="result-label">Kaçırılan Tehditler:</span>
                <span class="result-value">${results.missed_threats}</span>
            </div>

            <div class="result-item">
                <span class="result-label">Geçen Süre:</span>
                <span class="result-value">${Math.floor(results.time_taken / 60)}:${(results.time_taken % 60).toString().padStart(2, '0')}</span>
            </div>

            <div class="mt-4 text-center">
                <button class="btn btn-primary" onclick="location.reload()">Yeni Simülasyon</button>
            </div>
        `;

        this.container.appendChild(resultsPanel);
        this.canvas.style.pointerEvents = 'none';
    }
}














document.addEventListener('DOMContentLoaded', function() {
    // DOM elementlerini seç
    const simulationCards = document.querySelectorAll('.simulation-card');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchSimulation');
    const simulationModal = document.getElementById('simulationModal');
    const tutorialModal = document.getElementById('tutorialModal');

    // Filtreleme fonksiyonu
    function filterSimulations() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value.toLowerCase();
        const selectedStatus = statusFilter.value;

        simulationCards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const description = card.querySelector('.simulation-description').textContent.toLowerCase();
            const category = card.dataset.category.toLowerCase();
            const status = card.dataset.status;

            const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
            const matchesCategory = !selectedCategory || category === selectedCategory;
            const matchesStatus = !selectedStatus || status === selectedStatus;

            if (matchesSearch && matchesCategory && matchesStatus) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });

        // "Sonuç bulunamadı" mesajını göster/gizle
        const noResults = document.querySelector('.no-results');
        const visibleCards = document.querySelectorAll('.simulation-card[style="display: flex"]');
        
        if (visibleCards.length === 0) {
            if (!noResults) {
                const message = document.createElement('p');
                message.className = 'no-results';
                message.textContent = 'Aramanızla eşleşen simülasyon bulunamadı.';
                document.querySelector('.simulations-grid').appendChild(message);
            }
        } else {
            if (noResults) {
                noResults.remove();
            }
        }
    }

    // Event listeners ekle
    categoryFilter.addEventListener('change', filterSimulations);
    statusFilter.addEventListener('change', filterSimulations);
    searchInput.addEventListener('input', filterSimulations);

    // Simülasyon başlatma
    document.querySelectorAll('.start-simulation').forEach(button => {
        button.addEventListener('click', function() {
            const simulationId = this.dataset.simulationId;
            loadSimulation(simulationId);
        });
    });

    // Eğitim videosu görüntüleme
    document.querySelectorAll('.view-tutorial').forEach(button => {
        button.addEventListener('click', function() {
            const simulationId = this.dataset.simulationId;
            loadTutorial(simulationId);
        });
    });

    // Modal kapatma
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });

    // Modal dışına tıklandığında kapat
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });

    // Simülasyon yükleme
    async function loadSimulation(simulationId) {
        try {
            const response = await fetch(`../api/get_simulation.php?id=${simulationId}`);
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('simulationTitle').textContent = data.simulation.title;
                
                // Simülasyon içeriğini yükle
                const container = document.getElementById('simulationContainer');
                container.innerHTML = ''; // Önceki içeriği temizle
                
                // Simülasyon iframe'ini oluştur
                const iframe = document.createElement('iframe');
                iframe.src = data.simulation.url;
                iframe.className = 'simulation-frame';
                container.appendChild(iframe);
                
                // Modalı göster
                simulationModal.style.display = 'block';

                // İlerleme durumunu kaydet
                startSimulationSession(simulationId);
            } else {
                alert(data.message || 'Simülasyon yüklenirken bir hata oluştu.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Simülasyon yüklenirken bir hata oluştu.');
        }
    }

    // Eğitim yükleme
    async function loadTutorial(simulationId) {
        try {
            const response = await fetch(`../api/get_tutorial.php?id=${simulationId}`);
            const data = await response.json();
            
            if (data.success) {
                const container = document.getElementById('tutorialContainer');
                container.innerHTML = ''; // Önceki içeriği temizle
                
                // Video player'ı oluştur
                const video = document.createElement('video');
                video.src = data.tutorial.video_url;
                video.controls = true;
                video.className = 'tutorial-video';
                container.appendChild(video);
                
                // Modalı göster
                tutorialModal.style.display = 'block';
            } else {
                alert(data.message || 'Eğitim videosu yüklenirken bir hata oluştu.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Eğitim videosu yüklenirken bir hata oluştu.');
        }
    }

    // Simülasyon oturumu başlat
    async function startSimulationSession(simulationId) {
        try {
            await fetch('../api/start_simulation_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    simulation_id: simulationId
                })
            });
        } catch (error) {
            console.error('Error:', error);
        }
    }

    // Simülasyon kartları için hover efekti
    simulationCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.classList.add('hover');
        });
        
        card.addEventListener('mouseleave', () => {
            card.classList.remove('hover');
        });
    });
});

// Global olarak XRaySimulator'ı erişilebilir yap
window.XRaySimulator = XRaySimulator;