<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth_middleware.php';

// Verifica autenticazione
requireLogin();

$pageTitle = "Gestione Richieste";
$current_page = "richieste";
include 'includes/header.php';
?>

            <div class="container-fluid">
                <div class="row">
                    <?php include 'includes/sidebar.php'; ?>
                    
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2 pb-2 text-center">
                <i class="fas fa-clipboard-list"></i> Gestione Richieste
            </h1>

            <div class="row g-3 justify-content-center align-items-stretch mb-3">
                <div class="col-6 col-md-auto d-grid text-no-wrap">
                    <button type="button" class="btn btn-action btn-sm p-2 btn-outline-secondary w-100 h-100" id="refreshBtn">
                    <span class="d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-sync-alt"></i>
                        <span>Aggiorna</span>
                    </span>
                    </button>
                </div>

                <div class="col-6 col-md-auto d-grid text-no-wrap">
                    <button type="button" class="btn btn-action btn-sm p-2 btn-outline-primary w-100 h-100" id="notificationPermissionsBtn" title="Richiedi permessi notifiche">
                    <span class="d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-bell"></i>
                        <span>Notifiche</span>
                    </span>
                    </button>
                </div>

                <div class="col-6 col-md-auto d-grid text-no-wrap">
                    <button type="button" class="btn btn-action btn-sm p-2 btn-outline-warning w-100 h-100 d-flex align-items-center justify-content-center" id="audioPermissionsBtn" title="Testa e configura audio">
                    <span class="d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-volume-up"></i>
                        <span>Test Audio</span>
                    </span>
                    </button>
                </div>

                <div class="col-6 col-md-auto d-grid text-no-wrap">
                    <button type="button" class="btn btn-action btn-sm p-2 btn-outline-success w-100 h-100" id="soundToggleBtn" title="Attiva/Disattiva suono notifiche">
                    <span class="d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-volume-up" id="soundIcon"></i>
                        <span id="soundLabel">Suono On</span>
                    </span>
                    </button>
                </div>
                </div>

                <!-- Filtri -->
                <div class="row mb-4 filter-bar">
                <div class="col-12">
                    <div class="card">
                    <div class="card-body">
                        <div class="row gap-2 align-items-end">
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label">Stato</label>
                            <select class="form-select" id="statusFilter">
                            <option value="">Tutti gli stati</option>
                            <option value="open" selected>Da gestire</option>
                            <option value="0">In attesa</option>
                            <option value="1">In lavorazione</option>
                            <option value="2">Completata</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="typeFilter" class="form-label">Tipo Richiesta</label>
                            <select class="form-select" id="typeFilter">
                            <option value="">Tutti i tipi</option>
                            <option value="event">Evento</option>
                            <option value="service">Servizio</option>
                            <option value="promos">Promozione</option>
                            <option value="reservation">Prenotazione</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="searchFilter" class="form-label">Ricerca</label>
                            <input type="text" class="form-control" id="searchFilter" placeholder="Cerca nel messaggio o farmacia...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary flex-fill" id="applyFilters">
                                <i class="fas fa-search"></i> Filtra
                            </button>
                            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center" id="resetFilters" title="Reset filtri">
                                <i class="fas fa-times"></i>
                                <span class="badge bg-secondary ms-1" id="filterCount" style="display:none;">0</span>
                            </button>
                            </div>
                        </div>
                        </div><!-- /.row -->
                    </div>
                    </div>
                </div>
                </div>

                <!-- Statistiche -->
                <div class="row mb-4 stats-row">
                <div class="col-md-3 mb-3">
                    <div class="card text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title text-warning" id="pendingCount">0</h5>
                        <p class="card-text">In Attesa</p>
                    </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title text-info" id="processingCount">0</h5>
                        <p class="card-text">In Lavorazione</p>
                    </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title text-success" id="completedCount">0</h5>
                        <p class="card-text">Completate</p>
                    </div>
                    </div>
                </div>
                <!-- <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-danger" id="rejectedCount">0</h5>
                            <p class="card-text">Rifiutate</p>
                        </div>
                    </div>
                    </div>
                    <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-secondary" id="cancelledCount">0</h5>
                            <p class="card-text">Annullate</p>
                        </div>
                    </div>
                    </div>
                -->
                <div class="col-md-3 mb-3">
                    <div class="card text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title text-primary" id="totalCount">0</h5>
                        <p class="card-text">Totale</p>
                    </div>
                    </div>
                </div>
                </div>

            <!-- Tabella Richieste -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="requestsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Utente</th>
                                    <th>Messaggio</th>
                                    <th>Stato</th>
                                    <th>Data</th>
                                    <th style="width: 160px;">Azioni</th>
                                </tr>
                            </thead>
                            <tbody id="requestsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Caricamento...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginazione -->
                    <nav aria-label="Paginazione richieste">
                        <ul class="pagination justify-content-center" id="pagination">
                        </ul>
                    </nav>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Dettagli Richiesta -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestDetailsModalLabel">Dettagli Richiesta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Contenuto caricato dinamicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Aggiorna Stato -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">Aggiorna Stato Richiesta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm">
                    <input type="hidden" id="updateRequestId">
                    <div class="mb-3">
                        <label for="newStatus" class="form-label">Nuovo Stato</label>
                        <select class="form-select" id="newStatus" required>
                            <option value="0">In attesa</option>
                            <option value="1">In lavorazione</option>
                            <option value="2">Completata</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="statusNote" class="form-label">Nota Interna (opzionale)</label>
                        <textarea class="form-control" id="statusNote" rows="3" placeholder="Aggiungi una nota per questo cambio di stato..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="saveStatusBtn">Salva</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Elimina Richiesta -->
<div class="modal fade" id="deleteRequestModal" tabindex="-1" aria-labelledby="deleteRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRequestModalLabel">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Elimina Richiesta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Attenzione!</strong> Stai per eliminare una richiesta. Questa azione non può essere annullata.
                </div>
                <form id="deleteRequestForm">
                    <input type="hidden" id="deleteRequestId">
                    <div class="mb-3">
                        <label for="deleteReason" class="form-label">Motivo dell'eliminazione (opzionale)</label>
                        <textarea class="form-control" id="deleteReason" rows="3" placeholder="Specifica il motivo dell'eliminazione..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-1"></i>
                    Elimina
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Invia WhatsApp -->
<div class="modal fade" id="whatsappModal" tabindex="-1" aria-labelledby="whatsappModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="whatsappModalLabel">
                    <i class="fab fa-whatsapp text-success me-2"></i>
                    Invia Messaggio WhatsApp
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="whatsappForm">
                    <input type="hidden" id="whatsappRequestId">
                    <div class="mb-3">
                        <label for="whatsappPhone" class="form-label">Numero di Telefono</label>
                        <div class="input-group">
                            <span class="input-group-text">+39</span>
                            <input type="text" class="form-control" id="whatsappPhone" placeholder="320 283 8555" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="whatsappMessage" class="form-label">Messaggio</label>
                        <textarea class="form-control" id="whatsappMessage" rows="6" placeholder="Scrivi il tuo messaggio qui..." required></textarea>
                        <div class="form-text">
                            <span id="messageLength">0</span> caratteri
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-success" id="sendWhatsAppBtn">
                    <i class="fab fa-whatsapp me-1"></i>
                    Invia
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<link rel="stylesheet" href="./assets/css/richieste.css">
<script src="assets/js/richieste.js"></script>

<script>
// Aggiorna l'ID dell'ultima richiesta vista quando l'utente visita questa pagina
document.addEventListener('DOMContentLoaded', function() {
    // Ottieni l'ID della richiesta più recente dalla tabella
    const requestRows = document.querySelectorAll('#requestsTable tbody tr');
    if (requestRows.length > 0) {
        // Trova l'ID più alto (più recente)
        let maxId = 0;
        requestRows.forEach(row => {
            const requestId = parseInt(row.getAttribute('data-request-id') || '0');
            if (requestId > maxId) {
                maxId = requestId;
            }
        });
        
        // Aggiorna l'ID nel sistema di notifiche
        if (window.notificationSystem && maxId > 0) {
            window.notificationSystem.updateLastSeenId(maxId);
        }
    }
    
    // Controllo permessi notifiche con interazione utente
    let permissionRequested = false;
    
    // Funzione per richiedere permessi quando l'utente interagisce
    function requestNotificationPermissionOnInteraction() {
        if (!permissionRequested && window.notificationSystem) {
            if ('Notification' in window && Notification.permission === 'default') {
                console.log('Richiesta permessi notifiche tramite interazione utente...');
                window.notificationSystem.requestPermissions();
                permissionRequested = true;
            }
        }
    }
    
    // Richiedi permessi al primo click su qualsiasi elemento della pagina
    document.addEventListener('click', requestNotificationPermissionOnInteraction, { once: true });
    
    // Richiedi permessi anche al primo scroll
    document.addEventListener('scroll', requestNotificationPermissionOnInteraction, { once: true });
    
    // Richiedi permessi anche al primo movimento del mouse
    document.addEventListener('mousemove', requestNotificationPermissionOnInteraction, { once: true });
    
    // Log dello stato iniziale
    if (window.notificationSystem) {
        if ('Notification' in window && Notification.permission === 'granted') {
            console.log('Permessi notifiche già concessi');
        } else if (Notification.permission === 'denied') {
            console.log('Permessi notifiche negati dall\'utente');
        } else {
            console.log('Permessi notifiche: Richiesti al primo click/interazione');
        }
    }
    
    // Gestione pulsante permessi notifiche
    document.getElementById('notificationPermissionsBtn').addEventListener('click', function() {
        if (window.notificationSystem) {
            // Controlla se i permessi sono già concessi
            if ('Notification' in window && Notification.permission === 'granted') {
                // Mostra popup di avviso
                showNotificationPermissionsPopup();
            } else {
                // Richiedi permessi normalmente
                window.notificationSystem.requestPermissions();
            }
        }
    });
    
    // Funzione per mostrare popup permessi già concessi
    function showNotificationPermissionsPopup() {
        const popupHtml = `
            <div class="modal fade" id="notificationPermissionsModal" tabindex="-1" aria-labelledby="notificationPermissionsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="notificationPermissionsModalLabel">
                                <i class="fas fa-bell text-success me-2"></i>
                                Permessi Notifiche
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h6 class="text-success">Permessi già abilitati!</h6>
                            <p class="text-muted mb-0">
                                Le notifiche desktop sono già attive per questo sito.
                            </p>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                                <i class="fas fa-check me-1"></i>
                                Perfetto!
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Rimuovi modal esistente se presente
        const existingModal = document.getElementById('notificationPermissionsModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Aggiungi il nuovo modal
        document.body.insertAdjacentHTML('beforeend', popupHtml);
        
        // Mostra il modal
        const modal = new bootstrap.Modal(document.getElementById('notificationPermissionsModal'));
        modal.show();
        
        // Rimuovi il modal dal DOM dopo la chiusura
        document.getElementById('notificationPermissionsModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }
    
    // Gestione pulsante permessi audio
    document.getElementById('audioPermissionsBtn').addEventListener('click', function() {
        enableAudioPermissions();
    });
    
    // Funzione per testare e configurare audio
    function enableAudioPermissions() {
        // Mostra popup di conferma prima del test
        showAudioTestPopup();
    }
    
    // Popup per test audio
    function showAudioTestPopup() {
        const popupHtml = `
            <div class="modal fade" id="audioTestModal" tabindex="-1" aria-labelledby="audioTestModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="audioTestModalLabel">
                                <i class="fas fa-volume-up text-primary me-2"></i>
                                Test Audio
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <p class="text-muted mb-3">
                                Clicca "Test Audio" per verificare che il suono funzioni correttamente.
                            </p>
                            <div class="d-flex justify-content-center gap-2">
                                <button type="button" class="btn btn-primary" onclick="testAudioSound()">
                                    <i class="fas fa-play me-1"></i>
                                    Test Audio
                                </button>
                                <button type="button" class="btn btn-success" onclick="confirmAudioWorking()">
                                    <i class="fas fa-check me-1"></i>
                                    Funziona!
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Chiudi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Rimuovi modal esistente se presente
        const existingModal = document.getElementById('audioTestModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Aggiungi il nuovo modal
        document.body.insertAdjacentHTML('beforeend', popupHtml);
        
        // Mostra il modal
        const modal = new bootstrap.Modal(document.getElementById('audioTestModal'));
        modal.show();
        
        // Rimuovi il modal dal DOM dopo la chiusura
        document.getElementById('audioTestModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }
    
    // Funzione per testare il suono
    window.testAudioSound = function() {
        if (window.notificationSystem && window.notificationSystem.audio) {
            window.notificationSystem.playNotificationSound();
            showNotificationFeedback('Suono di test riprodotto!', 'info');
        } else {
            // Crea un audio temporaneo per il test
            const testAudio = new Audio('assets/sounds/notification.mp3');
            testAudio.volume = 0.3;
            testAudio.play().then(() => {
                showNotificationFeedback('Suono di test riprodotto!', 'success');
            }).catch(error => {
                console.log('Errore test audio:', error);
                showNotificationFeedback('Errore riproduzione audio. Controlla il volume del browser.', 'warning');
            });
        }
    };
    
    // Funzione per confermare che l'audio funziona
    window.confirmAudioWorking = function() {
        updateAudioButton(true);
        showNotificationFeedback('Audio configurato correttamente!', 'success');
        
        // Aggiorna il sistema di notifiche
        if (window.notificationSystem) {
            window.notificationSystem.initAudio();
        }
        
        // Chiudi il modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('audioTestModal'));
        if (modal) {
            modal.hide();
        }
    };
    
    // Funzione per aggiornare il pulsante audio
    function updateAudioButton(enabled) {
        const audioBtn = document.getElementById('audioPermissionsBtn');
        if (enabled) {
            audioBtn.classList.add('btn-outline-success');
            audioBtn.classList.remove('btn-outline-warning');
            audioBtn.innerHTML = '<i class="fas fa-check"></i> Audio OK';
            audioBtn.title = 'Permessi audio abilitati';
        } else {
            audioBtn.classList.add('btn-outline-warning');
            audioBtn.classList.remove('btn-outline-success');
            audioBtn.innerHTML = '<i class="fas fa-microphone"></i> Permessi Audio';
            audioBtn.title = 'Clicca per abilitare permessi audio';
        }
    }
    
    // Gestione toggle suono notifiche
    let soundEnabled = localStorage.getItem('notificationSoundEnabled') !== 'false'; // Default: true
    
    function updateSoundButton() {
        const soundIcon = document.getElementById('soundIcon');
        const soundLabel = document.getElementById('soundLabel');
        const soundBtn = document.getElementById('soundToggleBtn');
        
        if (soundEnabled) {
            soundIcon.className = 'fas fa-volume-up';
            soundLabel.textContent = 'Suono On';
            soundBtn.classList.add('btn-outline-success');
            soundBtn.classList.remove('btn-outline-secondary');
        } else {
            soundIcon.className = 'fas fa-volume-mute';
            soundLabel.textContent = 'Suono Off';
            soundBtn.classList.add('btn-outline-secondary');
            soundBtn.classList.remove('btn-outline-success');
        }
    }
    
    // Inizializza stato pulsante suono
    updateSoundButton();
    
    // Controlla stato permessi audio
    function checkAudioPermissions() {
        // Controlla se il browser supporta l'API AudioContext
        if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                const audioContext = new AudioContextClass();
                
                if (audioContext.state === 'running') {
                    console.log('AudioContext già attivo');
                    updateAudioButton(true);
                } else {
                    console.log('AudioContext sospeso');
                    updateAudioButton(false);
                }
                
                // Chiudi l'audio context per liberare risorse
                audioContext.close();
                
            } catch (error) {
                console.log('Errore controllo AudioContext:', error);
                updateAudioButton(false);
            }
        } else {
            // Fallback per browser che non supportano AudioContext
            console.log('AudioContext non supportato, usando fallback');
            updateAudioButton(false);
        }
    }
    
    // Controlla permessi audio all'avvio
    setTimeout(checkAudioPermissions, 1000);
    
    // Gestione click toggle suono
    document.getElementById('soundToggleBtn').addEventListener('click', function() {
        soundEnabled = !soundEnabled;
        localStorage.setItem('notificationSoundEnabled', soundEnabled);
        updateSoundButton();
        
        // Aggiorna il sistema di notifiche
        if (window.notificationSystem) {
            window.notificationSystem.setSoundEnabled(soundEnabled);
        }
        
        // Mostra feedback
        const message = soundEnabled ? 'Suono notifiche attivato' : 'Suono notifiche disattivato';
        showNotificationFeedback(message, soundEnabled ? 'success' : 'info');
    });
    
    // Funzione per mostrare feedback
    function showNotificationFeedback(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" role="alert" style="z-index: 9999; min-width: 300px;">
                <div style="padding-right: 30px;">
                    ${message}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%);"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', alertHtml);
        
        // Auto-remove dopo 3 secondi
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 3000);
    }
});
</script> 