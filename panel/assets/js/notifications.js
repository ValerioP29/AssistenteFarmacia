class NotificationSystem {
    constructor() {
        this.lastSeenId = this.getLastSeenId();
        this.checkInterval = null;
        this.audio = null;
        this.notificationPermission = false;
        this.isActive = true;
        this.soundEnabled = localStorage.getItem('notificationSoundEnabled') !== 'false'; // Default: true
        
        this.init();
    }
    
    init() {
        // Richiedi permesso per le notifiche
        this.requestNotificationPermission();
        
        // Inizializza audio
        this.initAudio();
        
        // Avvia il controllo periodico
        this.startPolling();
        
        // Gestisci visibilità della pagina
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.isActive = false;
            } else {
                this.isActive = true;
                // Controlla subito quando la pagina torna attiva
                this.checkNewRequests();
            }
        });
        
        // Gestisci focus della finestra
        window.addEventListener('focus', () => {
            this.isActive = true;
            this.checkNewRequests();
        });
        
        window.addEventListener('blur', () => {
            this.isActive = false;
        });
    }
    
    requestNotificationPermission() {
        if ('Notification' in window) {
            if (Notification.permission === 'default') {
                this.notificationPermission = false;
                
                // Richiedi permessi quando l'utente clicca o interagisce
                const requestPermission = () => {
                    Notification.requestPermission().then(permission => {
                        this.notificationPermission = permission === 'granted';
                    });
                };
                
                // Richiedi permessi al primo click dell'utente
                document.addEventListener('click', requestPermission, { once: true });
                
            } else if (Notification.permission === 'denied') {
                this.notificationPermission = false;
            } else if (Notification.permission === 'granted') {
                this.notificationPermission = true;
            }
        } else {
            this.notificationPermission = false;
        }
    }
    
    initAudio() {
        this.audio = new Audio('assets/sounds/notification.mp3');
        this.audio.preload = 'auto';
    }
    
    startPolling() {
        // Controlla ogni 5 minuti (300000 ms)
        this.checkInterval = setInterval(() => {
            this.checkNewRequests();
        }, 300000);
        
        // Controlla anche subito all'avvio
        this.checkNewRequests();
    }
    
    async checkNewRequests() {
        try {
            const response = await fetch(`api/notifications/check-new.php?last_seen=${this.lastSeenId}`);
            const data = await response.json();
            
            if (data.success && data.new_count > 0) {
                this.handleNewRequests(data);
            }
        } catch (error) {
            console.error('Errore nel controllo notifiche:', error);
        }
    }
    
    handleNewRequests(data) {
        // Aggiorna l'ID dell'ultima richiesta vista
        this.lastSeenId = data.latest_id;
        this.saveLastSeenId(this.lastSeenId);
        
        // Mostra sempre le notifiche desktop
        this.showDesktopNotification(data);
        
        // Riproduci suono
        this.playNotificationSound();
        
        // Mostra notifica in-app
        this.showInAppNotification(data.new_requests);
    }
    
    showDesktopNotification(data) {
        if (!this.notificationPermission) return;
        
        try {
            const notification = new Notification('Nuova Richiesta Farmacia', {
                body: `Hai ${data.new_count} nuova/e richiesta/e in attesa`,
                icon: 'images/farmacia_icon.png',
                badge: 'images/farmacia_icon.png',
                tag: 'new-request',
                requireInteraction: false,
                silent: false
            });
            
            notification.onclick = () => {
                window.focus();
                window.location.href = 'richieste.php';
                notification.close();
            };
            
            // Chiudi automaticamente dopo 5 secondi
            setTimeout(() => {
                notification.close();
            }, 5000);
        } catch (error) {
            console.error('Errore creazione notifica desktop:', error);
        }
    }
    
    showInAppNotification(newRequests) {
        // Crea o aggiorna il toast di notifica
        let toast = document.getElementById('notification-toast');
        
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'notification-toast';
            toast.className = 'notification-toast';
            document.body.appendChild(toast);
        }
        
        const requestText = newRequests.length === 1 ? 'richiesta' : 'richieste';
        toast.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon">🔔</div>
                <div class="notification-text">
                    <strong>${newRequests.length} nuova ${requestText}</strong>
                    <div class="notification-details">
                        ${newRequests.slice(0, 3).map(req => 
                            `${req.customer_name} - ${req.product_name || 'Prodotto'}`
                        ).join('<br>')}
                        ${newRequests.length > 3 ? `<br>...e altre ${newRequests.length - 3}` : ''}
                    </div>
                </div>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Mostra il toast
        toast.style.display = 'block';
        
        // Nascondi automaticamente dopo 10 secondi
        setTimeout(() => {
            if (toast && toast.parentElement) {
                toast.remove();
            }
        }, 10000);
    }
    

    
    playNotificationSound() {
        if (this.audio && this.soundEnabled) {
            this.audio.currentTime = 0;
            this.audio.play().catch(() => {
                // Audio non riproducibile (browser policy, ecc.)
            });
        }
    }
    
    setSoundEnabled(enabled) {
        this.soundEnabled = enabled;
        localStorage.setItem('notificationSoundEnabled', enabled);
    }
    
    // Metodo per reinizializzare l'audio (utile dopo aver ottenuto i permessi)
    initAudio() {
        this.audio = new Audio('assets/sounds/notification.mp3');
        this.audio.preload = 'auto';
    }
    
    getLastSeenId() {
        return parseInt(localStorage.getItem('lastSeenRequestId') || '0');
    }
    
    saveLastSeenId(id) {
        localStorage.setItem('lastSeenRequestId', id.toString());
    }
    
    // Metodo per aggiornare l'ID quando l'utente visita la pagina richieste
    updateLastSeenId(id) {
        this.lastSeenId = id;
        this.saveLastSeenId(id);
    }
    
    // Metodo per richiedere permessi notifiche
    requestPermissions() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                this.notificationPermission = permission === 'granted';
                
                if (permission === 'granted') {
                    // Test immediato della notifica
                    this.showDesktopNotification({
                        new_count: 1,
                        new_requests: [{
                            customer_name: 'Test',
                            product_name: 'Test'
                        }]
                    });
                }
            });
        } else if (Notification.permission === 'denied') {
            alert('Le notifiche sono state negate. Per abilitarle, vai nelle impostazioni del browser.');
        } else if (Notification.permission === 'granted') {
            this.notificationPermission = true;
            
            // Test immediato se già concessi
            this.showDesktopNotification({
                new_count: 1,
                new_requests: [{
                    customer_name: 'Test',
                    product_name: 'Test'
                }]
            });
        }
    }
    
    // Metodo per fermare il polling (utile per logout)
    stop() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
    }
}

// Inizializza il sistema di notifiche quando il DOM è caricato
document.addEventListener('DOMContentLoaded', () => {
    window.notificationSystem = new NotificationSystem();
});

// Esporta per uso globale
window.NotificationSystem = NotificationSystem; 