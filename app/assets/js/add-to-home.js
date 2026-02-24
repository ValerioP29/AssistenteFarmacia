/**
 * Componente per il banner "Aggiungi alla schermata Home"
 * Mostra un banner fisso in basso su dispositivi mobili per invitare l'utente
 * a salvare l'app sulla schermata home
 */

class AddToHomeBanner {
    constructor() {
        this.banner = null;
        this.isVisible = false;
        this.storageKeys = {
            lastShown: 'addToHomeLastShown',
            dismissedForever: 'addToHomeDismissedForever'
        };
        this.sevenDaysInMs = 7 * 24 * 60 * 60 * 1000; // 7 giorni in millisecondi
        
        this.init();
    }

    /**
     * Inizializza il componente
     */
    init() {
        // Crea il banner se non esiste
        this.createBanner();
        
        // Controlla se mostrare il banner
        this.checkAndShowBanner();
        
        // Aggiungi listener per il pulsante nel menu
        this.addMenuButtonListener();
    }

    /**
     * Rileva se l'utente sta usando un dispositivo mobile
     */
    isMobileDevice() {
        const userAgent = navigator.userAgent.toLowerCase();
        const isIOS = /iphone|ipad|ipod/.test(userAgent);
        const isAndroid = /android/.test(userAgent);
        const isMobile = /mobile|tablet/.test(userAgent);
        
        return (isIOS || isAndroid) && isMobile;
    }

    /**
     * Rileva se l'utente sta usando iOS Safari (non in modalità standalone)
     */
    isIOSSafari() {
        const userAgent = navigator.userAgent.toLowerCase();
        const isIOS = /iphone|ipad|ipod/.test(userAgent);
        const isSafari = /safari/.test(userAgent) && !/chrome/.test(userAgent);
        const isStandalone = window.navigator.standalone === true;
        
        return isIOS && isSafari && !isStandalone;
    }

    /**
     * Rileva se l'utente sta usando Android
     */
    isAndroid() {
        const userAgent = navigator.userAgent.toLowerCase();
        return /android/.test(userAgent);
    }

    /**
     * Ottiene il messaggio appropriato per il dispositivo
     */
    getMessage() {
        if (this.isIOSSafari()) {
            return "📱 Per salvare questa pagina sul tuo iPhone, tocca il pulsante Condividi e poi \"Aggiungi a Home\".";
        } else if (this.isAndroid()) {
            return "📱 Per aggiungere questa pagina alla schermata Home, tocca i tre puntini in alto a destra e scegli \"Aggiungi a schermata Home\".";
        } else {
            return "💻 Da desktop non è necessaria nessuna azione per salvare l'app. Questa funzione è ottimizzata per dispositivi mobili.";
        }
    }

    /**
     * Controlla se il banner deve essere mostrato
     */
    shouldShowBanner() {
        // Non mostrare se l'utente ha scelto "Non mostrare più"
        if (localStorage.getItem(this.storageKeys.dismissedForever) === 'true') {
            return false;
        }

        // Controlla se sono passati più di 7 giorni dall'ultima visualizzazione
        const lastShown = localStorage.getItem(this.storageKeys.lastShown);
        if (lastShown) {
            const lastShownDate = new Date(parseInt(lastShown));
            const now = new Date();
            const timeDiff = now.getTime() - lastShownDate.getTime();
            
            if (timeDiff < this.sevenDaysInMs) {
                return false;
            }
        }

        return true;
    }

    /**
     * Crea il banner HTML
     */
    createBanner() {
        // Rimuovi banner esistente se presente
        const existingBanner = document.getElementById('add-to-home-banner');
        if (existingBanner) {
            existingBanner.remove();
        }

        // Crea il banner
        this.banner = document.createElement('div');
        this.banner.id = 'add-to-home-banner';
        this.banner.className = 'add-to-home-banner';
        this.banner.style.cssText = `
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            transform: translateY(100%);
            transition: transform 0.3s ease-in-out;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.4;
        `;

        // Contenuto del banner
        this.banner.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <div style="flex: 1;">
                    <p style="margin: 0 0 12px 0; font-weight: 500;">
                        ${this.getMessage()}
                    </p>
                    <div style="display: flex; gap: 8px;">
                        <button id="add-to-home-close" style="
                            background: rgba(255, 255, 255, 0.2);
                            border: 1px solid rgba(255, 255, 255, 0.3);
                            color: white;
                            padding: 8px 16px;
                            border-radius: 6px;
                            font-size: 13px;
                            cursor: pointer;
                            transition: background 0.2s;
                        " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                            Chiudi
                        </button>
                        <button id="add-to-home-dismiss" style="
                            background: rgba(255, 255, 255, 0.1);
                            border: 1px solid rgba(255, 255, 255, 0.2);
                            color: rgba(255, 255, 255, 0.8);
                            padding: 8px 16px;
                            border-radius: 6px;
                            font-size: 13px;
                            cursor: pointer;
                            transition: background 0.2s;
                        " onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                            Non mostrare più
                        </button>
                    </div>
                </div>
                <button id="add-to-home-close-x" style="
                    background: none;
                    border: none;
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 18px;
                    cursor: pointer;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: color 0.2s;
                " onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">
                    ×
                </button>
            </div>
        `;

        // Aggiungi il banner al body
        document.body.appendChild(this.banner);

        // Aggiungi event listeners
        this.addEventListeners();
    }

    /**
     * Aggiunge gli event listeners al banner
     */
    addEventListeners() {
        if (!this.banner) return;

        // Pulsante "Chiudi"
        const closeBtn = this.banner.querySelector('#add-to-home-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hideBanner());
        }

        // Pulsante "Non mostrare più"
        const dismissBtn = this.banner.querySelector('#add-to-home-dismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => this.dismissForever());
        }

        // Pulsante X
        const closeXBtn = this.banner.querySelector('#add-to-home-close-x');
        if (closeXBtn) {
            closeXBtn.addEventListener('click', () => this.hideBanner());
        }
    }

    /**
     * Mostra il banner
     */
    showBanner() {
        if (!this.banner || this.isVisible) return;

        this.isVisible = true;
        this.banner.style.transform = 'translateY(0)';
        
        // Salva il timestamp della visualizzazione
        localStorage.setItem(this.storageKeys.lastShown, Date.now().toString());
    }

    /**
     * Nasconde il banner
     */
    hideBanner() {
        if (!this.banner || !this.isVisible) return;

        this.isVisible = false;
        this.banner.style.transform = 'translateY(100%)';
    }

    /**
     * Nasconde il banner per sempre
     */
    dismissForever() {
        localStorage.setItem(this.storageKeys.dismissedForever, 'true');
        this.hideBanner();
    }

    /**
     * Controlla e mostra il banner se necessario
     */
    checkAndShowBanner() {
        if (this.shouldShowBanner()) {
            // Mostra il banner dopo un breve delay per permettere il caricamento della pagina
            setTimeout(() => {
                this.showBanner();
            }, 1000);
        }
    }

    /**
     * Mostra manualmente il banner (per il pulsante del menu)
     */
    showManually() {
        // Il pulsante del menu deve sempre funzionare, indipendentemente dalla scelta "Non mostrare più"
        // La scelta "Non mostrare più" serve solo per il caricamento automatico
        
        // Mostra sempre il banner, anche su desktop
        this.showBanner();
        return true;
    }

    /**
     * Aggiunge il listener per il pulsante nel menu
     */
    addMenuButtonListener() {
        // Cerca il pulsante nel menu dopo che il DOM è caricato
        const checkForMenuButton = () => {
            const menuButton = document.getElementById('add-to-home-menu-btn');
            if (menuButton) {
                menuButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.showManually();
                });
            }
        };

        // Controlla immediatamente e dopo un delay
        checkForMenuButton();
        setTimeout(checkForMenuButton, 1000);
    }
}

// Inizializza il componente immediatamente
window.addToHomeBanner = new AddToHomeBanner();

// Backup: anche quando il DOM è pronto (per sicurezza)
document.addEventListener('DOMContentLoaded', () => {
    if (!window.addToHomeBanner) {
        window.addToHomeBanner = new AddToHomeBanner();
    }
});

// Funzione globale per mostrare il banner manualmente (chiamata dal menu)
function showAddToHomeBanner() {
    if (window.addToHomeBanner) {
        return window.addToHomeBanner.showManually();
    } else {
        // Se il componente non è ancora caricato, mostra un messaggio
        alert('Caricamento in corso... Riprova tra qualche secondo.');
        return false;
    }
}

// Esporta la classe per uso esterno
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AddToHomeBanner;
} 