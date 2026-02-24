    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        &copy; <?= date('Y') ?> <?= APP_NAME ?>. Tutti i diritti riservati.
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        Versione <?= APP_VERSION ?> | 
                        <a href="#" class="text-decoration-none" onclick="showSystemInfo()">
                            <i class="fas fa-info-circle"></i> Info Sistema
                        </a>
                    </small>
                </div>
            </div>
        </div>
    </footer>


    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/navbar.js"></script>
    <script src="assets/js/sidebars.js"></script>
    <script src="assets/js/notifications.js"></script>
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- System Info Modal -->
    <div class="modal fade" id="systemInfoModal" tabindex="-1" aria-labelledby="systemInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="systemInfoModalLabel">
                        <i class="fas fa-info-circle"></i> Informazioni Sistema
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-server"></i> Server</h6>
                            <ul class="list-unstyled">
                                <li><strong>PHP:</strong> <?= PHP_VERSION ?></li>
                                <li><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></li>
                                <li><strong>OS:</strong> <?= php_uname('s') ?> <?= php_uname('r') ?></li>
                                <li><strong>Memory:</strong> <?= ini_get('memory_limit') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-database"></i> Database</h6>
                            <ul class="list-unstyled">
                                <li><strong>Host:</strong> <?= DB_HOST ?></li>
                                <li><strong>Database:</strong> <?= DB_NAME ?></li>
                                <li><strong>Charset:</strong> <?= DB_CHARSET ?></li>
                                <li><strong>Status:</strong> <span id="dbStatus">Verificando...</span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user"></i> Sessione</h6>
                            <ul class="list-unstyled">
                                <li><strong>User ID:</strong> <?= $_SESSION['user_id'] ?? 'Non autenticato' ?></li>
                                <li><strong>Role:</strong> <?= $_SESSION['user_role'] ?? 'N/A' ?></li>
                                <li><strong>Session ID:</strong> <?= session_id() ?></li>
                                <li><strong>Started:</strong> <?= date('Y-m-d H:i:s', $_SESSION['__last_regenerate'] ?? time()) ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-clock"></i> Tempo</h6>
                            <ul class="list-unstyled">
                                <li><strong>Server Time:</strong> <?= date('Y-m-d H:i:s') ?></li>
                                <li><strong>Timezone:</strong> <?= date_default_timezone_get() ?></li>
                                <li><strong>Uptime:</strong> <span id="uptime">Calcolando...</span></li>
                                <li><strong>Load Time:</strong> <span id="loadTime">-</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                    <button type="button" class="btn btn-primary" onclick="refreshSystemInfo()">
                        <i class="fas fa-sync-alt"></i> Aggiorna
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Global JavaScript -->
    <script>
        // Variabili globali
        window.APP_CONFIG = {
            name: '<?= APP_NAME ?>',
            version: '<?= APP_VERSION ?>',
            url: '<?= APP_URL ?>',
            csrfToken: '<?= generateCSRFToken() ?>',
            currentPage: '<?= $current_page ?? 'dashboard' ?>',
            userRole: '<?= $_SESSION['user_role'] ?? '' ?>',
            userId: '<?= $_SESSION['user_id'] ?? '' ?>'
        };

        // Funzione per mostrare info sistema
        function showSystemInfo() {
            const modal = new bootstrap.Modal(document.getElementById('systemInfoModal'));
            modal.show();
            refreshSystemInfo();
        }

        // Funzione per aggiornare info sistema
        function refreshSystemInfo() {
            // Verifica stato database
            fetch('api/system/status.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('dbStatus').textContent = data.database ? 'Connesso' : 'Errore';
                    document.getElementById('dbStatus').className = data.database ? 'text-success' : 'text-danger';
                })
                .catch(() => {
                    document.getElementById('dbStatus').textContent = 'Errore';
                    document.getElementById('dbStatus').className = 'text-danger';
                });

            // Calcola uptime
            const startTime = performance.now();
            document.getElementById('loadTime').textContent = Math.round(performance.now() - startTime) + 'ms';
        }

        // Gestione logout
        function logout() {
            if (confirm('Sei sicuro di voler uscire?')) {
                // Prova prima con l'API
                fetch('api/auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.APP_CONFIG.csrfToken
                    }
                })
                .then(response => {
                    if (response.ok) {
                        window.location.href = 'login.php';
                    } else {
                        // Se c'è un errore HTTP, usa il logout diretto
                        window.location.href = 'logout.php';
                    }
                })
                .catch(error => {
                    // Se c'è un errore di rete, usa il logout diretto
                    window.location.href = 'logout.php';
                });
            }
        }

        // Gestione errori AJAX
        document.addEventListener('DOMContentLoaded', function() {
            // Interceptor per fetch - DISABILITATO TEMPORANEAMENTE
            // const originalFetch = window.fetch;
            // window.fetch = function(...args) {
            //     // Non intercettare le chiamate di logout e API
            //     if (args[0] && (args[0].includes('logout.php') || args[0].includes('api/'))) {
            //         return originalFetch.apply(this, args);
            //     }
            //     
            //     return originalFetch.apply(this, args)
            //         .then(response => {
            //             if (!response.ok) {
            //                 throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            //             }
            //             return response;
            //         })
            //         .catch(error => {
            //             console.error('Fetch error:', error);
            //             showAlert('Errore di connessione', 'danger');
            //             throw error;
            //         });
            // };

            // Gestione errori JavaScript - DISABILITATO TEMPORANEAMENTE
            // window.addEventListener('error', function(e) {
            //     console.error('JavaScript error:', e.error);
            //     showAlert('Errore JavaScript: ' + e.message, 'danger');
            // });

            // Gestione promise rejection - DISABILITATO TEMPORANEAMENTE
            // window.addEventListener('unhandledrejection', function(e) {
            //     console.error('Unhandled promise rejection:', e.reason);
            //     showAlert('Errore: ' + e.reason, 'danger');
            // });
        });

        // Funzione per mostrare alert
        function showAlert(message, type = 'info') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" role="alert" style="z-index: 9999; min-width: 300px; max-width: 500px;">
                    <div style="padding-right: 30px;">
                        ${message}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%);"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto-remove dopo 5 secondi
            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }

        // Funzione per confermare azioni
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }

        // Funzione per formattare date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Funzione per formattare prezzi
        function formatPrice(price) {
            return new Intl.NumberFormat('it-IT', {
                style: 'currency',
                currency: 'EUR'
            }).format(price);
        }

        // Funzione per validare email
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Funzione per validare telefono
        function validatePhone(phone) {
            const re = /^\+?[1-9]\d{1,14}$/;
            return re.test(phone);
        }

        // Funzione per sanitizzare input
        function sanitizeInput(input) {
            const div = document.createElement('div');
            div.textContent = input;
            return div.innerHTML;
        }

        // Funzione per debounce
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Funzione per throttle
        function throttle(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }

        // Inizializzazione globale
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 5000);
            });

            // Tooltip initialization
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Popover initialization
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });

            // Logout links - gestito da sidebars.js per evitare duplicazioni
        });
    </script>
</body>
</html> 