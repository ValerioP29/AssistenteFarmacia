/**
 * JavaScript Principale
 * Assistente Farmacia Panel
 */

// Configurazione globale
const APP = {
    config: window.APP_CONFIG || {},
    utils: {},
    api: {},
    ui: {}
};

// Utility functions
APP.utils = {
    /**
     * Formatta una data
     */
    formatDate: function(dateString, format = 'it-IT') {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString(format, {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Formatta un prezzo
     */
    formatPrice: function(price, currency = 'EUR') {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: currency
        }).format(price);
    },

    /**
     * Valida email
     */
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    /**
     * Valida telefono
     */
    validatePhone: function(phone) {
        const re = /^\+?[1-9]\d{1,14}$/;
        return re.test(phone);
    },

    /**
     * Sanitizza input
     */
    sanitize: function(input) {
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    },

    /**
     * Debounce function
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function
     */
    throttle: function(func, limit) {
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
    },

    /**
     * Genera ID unico
     */
    generateId: function() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    },

    /**
     * Copia testo negli appunti
     */
    copyToClipboard: function(text) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(text);
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            const result = document.execCommand('copy');
            document.body.removeChild(textArea);
            return Promise.resolve(result);
        }
    }
};

// API functions
APP.api = {
    /**
     * Ottieni token CSRF
     */
    getCSRFToken: function() {
        // Prova prima dal meta tag
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }
        
        // Prova da APP_CONFIG
        if (window.APP_CONFIG && window.APP_CONFIG.csrfToken) {
            return window.APP_CONFIG.csrfToken;
        }
        
        // Prova da APP.config
        if (APP.config && APP.config.csrfToken) {
            return APP.config.csrfToken;
        }
        
        // Fallback: cerca nel form
        const formToken = document.querySelector('input[name="csrf_token"]');
        if (formToken) {
            return formToken.value;
        }
        
        return '';
    },

    /**
     * Richiesta API generica
     */
    request: async function(url, options = {}) {
        const csrfToken = this.getCSRFToken();
        const defaultOptions = {
            headers: {}
        };

        // Aggiungi token CSRF solo se disponibile
        if (csrfToken) {
            defaultOptions.headers['X-CSRF-Token'] = csrfToken;
        }

        const finalOptions = { ...defaultOptions, ...options };
        
        // Gestisci headers in base al tipo di body
        if (finalOptions.body instanceof FormData) {
            // Per FormData, rimuovi Content-Type per permettere al browser di impostarlo automaticamente
            delete finalOptions.headers['Content-Type'];
        } else if (finalOptions.body && typeof finalOptions.body === 'object' && !finalOptions.headers['Content-Type']) {
            // Per oggetti JSON, imposta Content-Type se non già impostato
            finalOptions.headers['Content-Type'] = 'application/json';
            finalOptions.body = JSON.stringify(finalOptions.body);
        }

        try {
            const response = await fetch(url, finalOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                const textData = await response.text();
                
                // Prova a parsare come JSON anche se il content-type non lo indica
                try {
                    return JSON.parse(textData);
                } catch (e) {
                    return textData;
                }
            }
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    /**
     * GET request
     */
    get: function(url) {
        return this.request(url, { method: 'GET' });
    },

    /**
     * POST request
     */
    post: function(url, data) {
        let options = { method: 'POST' };
        
        if (data instanceof FormData) {
            // Per FormData, non impostare Content-Type (browser lo farà automaticamente)
            options.body = data;
        } else {
            // Per JSON o altri dati
            const csrfToken = this.getCSRFToken();
            options.headers = {
                'Content-Type': 'application/json'
            };
            
            // Aggiungi token CSRF solo se disponibile
            if (csrfToken) {
                options.headers['X-CSRF-Token'] = csrfToken;
            }
            
            if (typeof data === 'object') {
                options.body = JSON.stringify(data);
            } else {
                options.body = data;
            }
        }
        
        return this.request(url, options);
    },

    /**
     * PUT request
     */
    put: function(url, data) {
        return this.request(url, { 
            method: 'PUT', 
            body: data 
        });
    },

    /**
     * DELETE request
     */
    delete: function(url) {
        return this.request(url, { method: 'DELETE' });
    }
};

// UI functions
APP.ui = {
    /**
     * Mostra alert
     */
    showAlert: function(message, type = 'info', duration = 5000) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" role="alert" style="z-index: 9999; min-width: 300px; max-width: 500px;">
                <div style="padding-right: 30px;">
                    ${message}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%);"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', alertHtml);
        
        // Auto-remove
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, duration);
    },

    /**
     * Mostra modal di conferma
     */
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },

    /**
     * Mostra loading spinner
     */
    showLoading: function(element, text = 'Caricamento...') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (element) {
            element.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">${text}</span>
                    </div>
                    <p class="mt-2 text-muted">${text}</p>
                </div>
            `;
        }
    },

    /**
     * Nasconde loading spinner
     */
    hideLoading: function(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (element) {
            element.innerHTML = '';
        }
    },

    /**
     * Abilita/disabilita form
     */
    toggleForm: function(formSelector, disabled = true) {
        const form = document.querySelector(formSelector);
        if (form) {
            const inputs = form.querySelectorAll('input, select, textarea, button');
            inputs.forEach(input => {
                input.disabled = disabled;
            });
        }
    },

    /**
     * Resetta form
     */
    resetForm: function(formSelector) {
        const form = document.querySelector(formSelector);
        if (form) {
            form.reset();
        }
    },

    /**
     * Valida form
     */
    validateForm: function(formSelector) {
        const form = document.querySelector(formSelector);
        if (!form) return false;
        
        return form.checkValidity();
    },

    /**
     * Ottiene dati form
     */
    getFormData: function(formSelector) {
        const form = document.querySelector(formSelector);
        if (!form) return {};
        
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    },

    /**
     * Imposta dati form
     */
    setFormData: function(formSelector, data) {
        const form = document.querySelector(formSelector);
        if (!form) return;
        
        Object.keys(data).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = data[key];
            }
        });
    }
};

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

    // Logout links - gestito da sidebars.js
    // Rimuoviamo la gestione qui per evitare conflitti

    // Form validation
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!APP.ui.validateForm(this)) {
                e.preventDefault();
                APP.ui.showAlert('Per favore compila tutti i campi obbligatori', 'warning');
            }
        });
    });

    // Auto-save forms
    document.querySelectorAll('form[data-autosave]').forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');
        const debouncedSave = APP.utils.debounce(() => {
            const data = APP.ui.getFormData(form);
            localStorage.setItem(`form_${form.dataset.autosave}`, JSON.stringify(data));
        }, 1000);

        inputs.forEach(input => {
            input.addEventListener('input', debouncedSave);
        });

        // Restore data
        const savedData = localStorage.getItem(`form_${form.dataset.autosave}`);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                APP.ui.setFormData(form, data);
            } catch (e) {
                console.error('Error restoring form data:', e);
            }
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S per salvare
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const saveButton = document.querySelector('[data-save]');
            if (saveButton) {
                saveButton.click();
            }
        }

        // Escape per chiudere modali
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
        }
    });

    // Lazy loading per immagini
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
});

// Gestione errori globali
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    APP.ui.showAlert('Errore JavaScript: ' + e.message, 'danger');
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
    APP.ui.showAlert('Errore: ' + e.reason, 'danger');
});

// Esporta per uso globale
window.APP = APP; 

        // Funzioni di utilità
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT');
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('it-IT', {
                style: 'currency',
                currency: 'EUR'
            }).format(price);
        }

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function validatePhone(phone) {
            const re = /^[\+]?[0-9\s\-\(\)]{8,}$/;
            return re.test(phone);
        }

        function sanitizeInput(input) {
            return input.replace(/[<>]/g, '');
        }

        // Funzioni di debounce e throttle
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

        // Funzione per tornare all'admin
        async function returnToAdmin() {
            try {
                const formData = new FormData();
                formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                
                const response = await APP.api.post('api/users/return-admin.php', formData);
                
                if (response.success) {
                    APP.ui.showAlert('Ritorno all\'account admin effettuato', 'success');
                    setTimeout(() => window.location.href = 'utenti.php', 1000);
                } else {
                    APP.ui.showAlert(response.message || 'Errore durante il ritorno all\'admin', 'danger');
                }
            } catch (error) {
                console.error('Errore:', error);
                APP.ui.showAlert('Errore di connessione', 'danger');
            }
        }

        // Esporta funzioni globalmente
        window.returnToAdmin = returnToAdmin; 