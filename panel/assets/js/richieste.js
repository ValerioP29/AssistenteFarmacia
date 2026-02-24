/**
 * JavaScript per la gestione delle richieste
 * Assistente Farmacia Panel
 */

class RichiesteManager {
    constructor() {
        this.currentPage = 1;
        this.currentFilters = {};
        this.init();
    }

    init() {
        this.bindEvents();
        document.getElementById('statusFilter').value = 'open';
        this.applyFilters();
        this.loadRequests();
        this.updateStatistics();
        this.updateResetButtonState();
    }

    bindEvents() {
        // Filtri
        document.getElementById('applyFilters').addEventListener('click', () => {
            this.applyFilters();
        });

        // Reset filtri
        document.getElementById('resetFilters').addEventListener('click', () => {
            this.resetFilters();
        });

        // Ricerca con Enter
        document.getElementById('searchFilter').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.applyFilters();
            }
        });

        // Aggiorna stato pulsante reset quando cambiano i filtri
        document.getElementById('statusFilter').addEventListener('change', () => {
            this.updateResetButtonState();
        });
        
        document.getElementById('typeFilter').addEventListener('change', () => {
            this.updateResetButtonState();
        });
        
        document.getElementById('searchFilter').addEventListener('input', () => {
            this.updateResetButtonState();
        });

        // Pulsante aggiorna
        document.getElementById('refreshBtn').addEventListener('click', () => {
            this.loadRequests();
            this.updateStatistics();
        });

        // Salva stato
        document.getElementById('saveStatusBtn').addEventListener('click', () => {
            this.updateRequestStatus();
        });

        // Conferma eliminazione
        document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
            this.deleteRequest();
        });

        // Invia WhatsApp
        document.getElementById('sendWhatsAppBtn').addEventListener('click', () => {
            this.sendWhatsAppMessage();
        });

        // Conta caratteri messaggio WhatsApp
        document.getElementById('whatsappMessage').addEventListener('input', (e) => {
            document.getElementById('messageLength').textContent = e.target.value.length;
        });

        // Event listener per pulsanti (delegazione eventi)
        document.addEventListener('click', async (e) => {
            // Pulsante aggiorna stato
            if (e.target.closest('.update-status-btn')) {
                const button = e.target.closest('.update-status-btn');
                const requestId = button.getAttribute('data-request-id');
                await this.openUpdateStatus(requestId);
            }
            
            // Pulsante visualizza dettagli
            if (e.target.closest('.view-details-btn')) {
                const button = e.target.closest('.view-details-btn');
                const requestId = button.getAttribute('data-request-id');
                await this.viewDetails(requestId);
            }
            
            // Pulsante WhatsApp
            if (e.target.closest('.open-whatsapp-btn')) {
                const button = e.target.closest('.open-whatsapp-btn');
                const requestId = button.getAttribute('data-request-id');
                const userPhone = button.getAttribute('data-user-phone');
                this.openWhatsAppMessage(requestId, userPhone);
            }
            
            // Pulsante elimina
            if (e.target.closest('.open-delete-btn')) {
                const button = e.target.closest('.open-delete-btn');
                const requestId = button.getAttribute('data-request-id');
                this.openDeleteRequest(requestId);
            }
            
            // Link paginazione
            if (e.target.closest('.pagination-link')) {
                e.preventDefault();
                const link = e.target.closest('.pagination-link');
                const page = parseInt(link.getAttribute('data-page'));
                this.goToPage(page);
            }
        });
    }

    applyFilters() {
    const rawStatus = document.getElementById('statusFilter').value;

    const filters = {
        request_type: document.getElementById('typeFilter').value,
        search: document.getElementById('searchFilter').value
    };

    if (rawStatus === 'open') {
        filters.status_in = '0,1'; 
    } else if (rawStatus !== '') {
        filters.status = Number(rawStatus);
    }

    this.currentFilters = filters;
    this.currentPage = 1;
    this.loadRequests();
    this.updateResetButtonState();
}


    resetFilters() {
        // Reset dei valori dei filtri
        document.getElementById('statusFilter').value = '';
        document.getElementById('typeFilter').value = '';
        document.getElementById('searchFilter').value = '';
        
        // Reset dei filtri correnti
        this.currentFilters = {};
        this.currentPage = 1;
        
        // Ricarica le richieste
        this.loadRequests();
        
        // Aggiorna stato pulsante
        this.updateResetButtonState();
        
        // Mostra feedback
        this.showSuccess('Filtri resettati');
    }

    updateResetButtonState() {
        const resetBtn = document.getElementById('resetFilters');
        const filterCountBadge = document.getElementById('filterCount');
        
        // Conta i filtri attivi
        let activeFiltersCount = 0;
        if (this.currentFilters.status || this.currentFilters.status_in) activeFiltersCount++;
        if (this.currentFilters.request_type) activeFiltersCount++;
        if (this.currentFilters.search) activeFiltersCount++;
        
        const hasActiveFilters = activeFiltersCount > 0;
        
        if (hasActiveFilters) {
            resetBtn.classList.remove('btn-outline-secondary');
            resetBtn.classList.add('btn-warning');
            resetBtn.title = `Reset ${activeFiltersCount} filtro${activeFiltersCount > 1 ? 'i' : ''} attivo${activeFiltersCount > 1 ? 'i' : ''}`;
            
            // Mostra badge con conteggio
            filterCountBadge.textContent = activeFiltersCount;
            filterCountBadge.style.display = 'inline';
        } else {
            resetBtn.classList.remove('btn-warning');
            resetBtn.classList.add('btn-outline-secondary');
            resetBtn.title = 'Reset filtri';
            
            // Nascondi badge
            filterCountBadge.style.display = 'none';
        }
    }

    async loadRequests() {
        try {
            this.showLoading();
            
            const params = new URLSearchParams({
                page: this.currentPage,
                limit: 20,
                ...this.currentFilters
            });

            const response = await fetch(`api/requests/list.php?${params}`);
            const data = await response.json();

            if (data.success) {
                this.renderRequests(data.data);
                this.renderPagination(data.pagination);
            } else {
                this.showError('Errore nel caricamento delle richieste: ' + data.error);
            }
        } catch (error) {
            this.showError('Errore di connessione: ' + error.message);
        }
    }

    renderRequests(requests) {
        const tbody = document.getElementById('requestsTableBody');
        
        if (requests.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>Nessuna richiesta trovata</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = requests.map(request => `
            <tr>
                <td class="justify-content-start"><strong>#${request.id}</strong></td>
                <td>
                    <span class="badge bg-primary text-center">${request.request_type_label}</span>
                </td>
                <td>
                    <div>
                        <strong>${request.user_username || 'N/A'}</strong>
                        ${request.user_phone ? `<br><small class="text-muted"><i class="fas fa-phone me-1"></i>${this.formatPhoneNumber(request.user_phone)}</small>` : ''}
                    </div>
                </td>
                <td>
                    <div title="${request.message}" >
                        ${request.message}
                    </div>
                </td>
                <td>
                    <span class="badge bg-${request.status_color}">${request.status_label}</span>
                </td>
                <td>
                    <small>${request.created_at_formatted}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary view-details-btn" 
                                data-request-id="${request.id}"
                                title="Visualizza dettagli">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="btn btn-outline-warning update-status-btn" 
                                data-request-id="${request.id}"
                                title="Aggiorna stato">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success open-whatsapp-btn" 
                                data-request-id="${request.id}"
                                data-user-phone="${request.user_phone || ''}"
                                title="Invia WhatsApp"
                                ${!request.user_phone ? 'disabled' : ''}>
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger open-delete-btn" 
                                data-request-id="${request.id}"
                                title="Elimina richiesta">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    renderPagination(pagination) {
        const paginationEl = document.getElementById('pagination');
        
        if (pagination.pages <= 1) {
            paginationEl.innerHTML = '';
            return;
        }

        let paginationHtml = '';

        // Pulsante precedente
        if (pagination.page > 1) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link pagination-link" href="#" data-page="${pagination.page - 1}">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;
        }

        // Pagine
        const startPage = Math.max(1, pagination.page - 2);
        const endPage = Math.min(pagination.pages, pagination.page + 2);

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `
                <li class="page-item ${i === pagination.page ? 'active' : ''}">
                    <a class="page-link pagination-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        // Pulsante successivo
        if (pagination.page < pagination.pages) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link pagination-link" href="#" data-page="${pagination.page + 1}">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;
        }

        paginationEl.innerHTML = paginationHtml;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadRequests();
    }

    async viewDetails(requestId) {
        try {
            const response = await fetch(`api/requests/get.php?id=${requestId}`);
            const data = await response.json();

            if (data.success) {
                this.renderRequestDetails(data.data);
                new bootstrap.Modal(document.getElementById('requestDetailsModal')).show();
            } else {
                this.showError('Errore nel caricamento dei dettagli: ' + data.error);
            }
        } catch (error) {
            this.showError('Errore di connessione: ' + error.message);
        }
    }

    renderRequestDetails(request) {
        const content = document.getElementById('requestDetailsContent');
        const productsTable = renderProductsTableFromMeta(request.metadata);
        const optionsRow = renderRequestOptionsFromMeta(request.metadata);
        
        content.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-clipboard-list me-2"></i>Informazioni Richiesta</h6>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>ID:</strong></td>
                            <td>#${request.id}</td>
                        </tr>
                        <tr>
                            <td><strong>Tipo:</strong></td>
                            <td><span class="badge bg-primary">${request.request_type_label}</span></td>
                        </tr>
                        <tr>
                            <td><strong>Stato:</strong></td>
                            <td><span class="badge bg-${request.status_color}">${request.status_label}</span></td>
                        </tr>
                        <tr>
                            <td><strong>Data Creazione:</strong></td>
                            <td>${request.created_at_formatted}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-user me-2"></i>Informazioni Cliente</h6>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Nome:</strong></td>
                            <td>${request.user.username || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><strong>Telefono:</strong></td>
                            <td><i class="fas fa-phone me-1"></i>${this.formatPhoneNumber(request.user.phone_number) || 'N/A'}</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <h6><i class="fas fa-comment me-2"></i>Messaggio</h6>
                    <div class="alert alert-info">
                        ${nl2br(request.message)}
                    </div>
                </div>
            </div>

            ${productsTable}
            ${optionsRow}

            <div class="row mt-3">
                <div class="col-12">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-warning update-status-btn" data-request-id="${request.id}">
                            <i class="fas fa-edit me-1"></i>
                            Aggiorna Stato
                        </button>
                        ${request.user.phone_number ? `
                            <button type="button" class="btn btn-success open-whatsapp-btn" data-request-id="${request.id}" data-user-phone="${request.user.phone_number}">
                                <i class="fab fa-whatsapp me-1"></i>
                                Invia WhatsApp
                            </button>
                        ` : `
                            <button type="button" class="btn btn-secondary" disabled>
                                <i class="fab fa-whatsapp me-1"></i>
                                Numero non disponibile
                            </button>
                        `}
                    </div>
                </div>
            </div>
            
            ${request.notes && request.notes.length > 0 ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6><i class="fas fa-sticky-note me-2"></i>Note</h6>
                        <div class="timeline">
                            ${request.notes.map(note => `
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-${this.getStatusColor(note.status)}"></div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between">
                                            <strong>${this.getStatusLabel(note.status)}</strong>
                                            <small class="text-muted">${new Date(note.updated_at).toLocaleString('it-IT')}</small>
                                        </div>
                                        <p class="mb-0">${note.text}</p>
                                        <small class="text-muted">Aggiornato da: ${note.updated_by}</small>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            ` : ''}
            
            ${request.metadata.whatsapp_messages && request.metadata.whatsapp_messages.length > 0 ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6><i class="fab fa-whatsapp me-2"></i>Messaggi WhatsApp Inviati</h6>
                        <div class="timeline">
                            ${request.metadata.whatsapp_messages.map(msg => `
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between">
                                            <strong><i class="fab fa-whatsapp me-1"></i>Messaggio WhatsApp</strong>
                                            <small class="text-muted">${new Date(msg.sent_at).toLocaleString('it-IT')}</small>
                                        </div>
                                        <p class="mb-0">${msg.message}</p>
                                        <small class="text-muted">Inviato da: ${msg.sent_by} | ID: ${msg.whatsapp_data.messageId || 'N/A'}</small>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            ` : ''}
        `;
    }

    async openUpdateStatus(requestId) {
        try {
            // Ottieni i dati della richiesta
            const response = await fetch(`api/requests/get.php?id=${requestId}`);
            const data = await response.json();
            
            if (data.success) {
                const request = data.data;
                
                // Imposta l'ID della richiesta
                document.getElementById('updateRequestId').value = requestId;
                
                // Imposta lo stato corrente nella select
                document.getElementById('newStatus').value = request.status;
                
                // Pulisci la nota
                document.getElementById('statusNote').value = '';
                
                // Mostra la modale
                new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
            } else {
                this.showError('Errore nel caricamento della richiesta: ' + data.error);
            }
        } catch (error) {
            this.showError('Errore di connessione: ' + error.message);
        }
    }

    openDeleteRequest(requestId) {
        document.getElementById('deleteRequestId').value = requestId;
        document.getElementById('deleteReason').value = '';
        new bootstrap.Modal(document.getElementById('deleteRequestModal')).show();
    }

    openWhatsAppMessage(requestId, phone) {
        if (!phone) {
            this.showError('Numero di telefono non disponibile per questo cliente');
            return;
        }

        document.getElementById('whatsappRequestId').value = requestId;
        document.getElementById('whatsappPhone').value = this.formatPhoneNumber(phone);
        document.getElementById('whatsappMessage').value = '';
        document.getElementById('messageLength').textContent = '0';
        
        new bootstrap.Modal(document.getElementById('whatsappModal')).show();
    }

    async updateRequestStatus() {
        const requestId = document.getElementById('updateRequestId').value;
        const status = document.getElementById('newStatus').value;
        const note = document.getElementById('statusNote').value;

        try {
            const response = await fetch('api/requests/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    status: status,
                    note: note
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Stato aggiornato con successo');
                bootstrap.Modal.getInstance(document.getElementById('updateStatusModal')).hide();
                document.getElementById('statusNote').value = '';
                this.loadRequests();
                this.updateStatistics();
            } else {
                this.showError('Errore nell\'aggiornamento: ' + data.error);
            }
        } catch (error) {
            this.showError('Errore di connessione: ' + error.message);
        }
    }

    async deleteRequest() {
        const requestId = document.getElementById('deleteRequestId').value;
        const reason = document.getElementById('deleteReason').value;

        try {
            const response = await fetch('api/requests/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    reason: reason
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Richiesta eliminata con successo');
                bootstrap.Modal.getInstance(document.getElementById('deleteRequestModal')).hide();
                document.getElementById('deleteReason').value = '';
                this.loadRequests();
                this.updateStatistics();
            } else {
                this.showError('Errore nell\'eliminazione: ' + data.error);
            }
        } catch (error) {
            this.showError('Errore di connessione: ' + error.message);
        }
    }

    async sendWhatsAppMessage() {
        const requestId = document.getElementById('whatsappRequestId').value;
        const phone = document.getElementById('whatsappPhone').value;
        const message = document.getElementById('whatsappMessage').value;

        if (!message.trim()) {
            this.showError('Inserisci un messaggio');
            return;
        }

        try {
            const raw = phone.replace(/\D/g, '');
            const local = raw.startsWith('39') ? raw.slice(2) : raw;
            const whatsappPhone = '39' + local;

            const response = await fetch('https://waservice-pharma1.jungleteam.it/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: whatsappPhone, message })
            });

            const data = await response.json();

            if (data.success) {
            this.showSuccess('Messaggio WhatsApp inviato con successo!');
            bootstrap.Modal.getInstance(document.getElementById('whatsappModal')).hide();
            document.getElementById('whatsappMessage').value = '';
            document.getElementById('messageLength').textContent = '0';

            this.logWhatsAppMessage(requestId, message, data.data);

            const ok = await this.setRequestStatus(requestId, 1, 'Avanzamento automatico dopo invio WhatsApp');
            if (ok) {
                this.loadRequests();
                this.updateStatistics();
            }
            } else {
            this.showError('Errore nell\'invio: ' + (data.message || 'Errore sconosciuto'));
            }
        } catch (error) {
            this.showError('Errore di connessione: ' + error.message);
        }
        }


    async logWhatsAppMessage(requestId, message, whatsappData) {
        try {
            const response = await fetch('api/requests/log-whatsapp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    message: message,
                    whatsapp_data: whatsappData
                })
            });
        } catch (error) {
            console.error('Errore nel logging del messaggio WhatsApp:', error);
        }
    }


        async updateStatistics() {
        try {
            const response = await fetch('api/requests/stats.php');
            const data = await response.json();

            if (data.success) {
            const s = data.data.status_stats || {};

            document.getElementById('pendingCount').textContent    = s.pending ?? 0;
            document.getElementById('processingCount').textContent = s.processing ?? 0;
            document.getElementById('completedCount').textContent  = s.completed ?? 0;
            // calcolo totale solo sui 3 stati visibili
            const total = (s.pending ?? 0) + (s.processing ?? 0) + (s.completed ?? 0);
            document.getElementById('totalCount').textContent = total;
            }
        } catch (error) {
            console.error('Errore nel caricamento delle statistiche:', error);
        }
        }



    showLoading() {
        const tbody = document.getElementById('requestsTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                </td>
            </tr>
        `;
    }

    showError(message) {
        // Crea un toast di errore
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-danger border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.appendChild(toast);
        document.body.appendChild(container);
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Rimuovi il toast dopo che è stato nascosto
        toast.addEventListener('hidden.bs.toast', () => {
            document.body.removeChild(container);
        });
    }

    showSuccess(message) {
        // Crea un toast di successo
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.appendChild(toast);
        document.body.appendChild(container);
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Rimuovi il toast dopo che è stato nascosto
        toast.addEventListener('hidden.bs.toast', () => {
            document.body.removeChild(container);
        });
    }

    getStatusLabel(status) {
        const labels = {
            0: 'In attesa',
            1: 'In lavorazione',
            2: 'Completata'
        };
        return labels[status] || 'Sconosciuto';
    }

    getStatusColor(status) {
        const colors = {
            0: 'warning',
            1: 'info',
            2: 'success'
        };
        return colors[status] || 'secondary';
    }

    formatPhoneNumber(phone) {
        if (!phone) return null;
        
        // Rimuovi spazi e caratteri speciali
        let cleanPhone = phone.replace(/[\s\-\(\)]/g, '');
        
        // Rimuovi prefisso internazionale +39 o 39
        if (cleanPhone.startsWith('+39')) {
            cleanPhone = cleanPhone.substring(3);
        } else if (cleanPhone.startsWith('39')) {
            cleanPhone = cleanPhone.substring(2);
        }
        
        // Formatta il numero per una migliore leggibilità
        if (cleanPhone.length === 10) {
            // Formato: 320 283 8555
            return cleanPhone.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3');
        } else if (cleanPhone.length === 9) {
            // Formato: 320 283 855
            return cleanPhone.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
        }
        
        return cleanPhone;
    }

        async setRequestStatus(requestId, newStatus, note = '') {
    try {
        const res = await fetch('api/requests/update-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            request_id: requestId,
            status: String(newStatus),
            note
        })
        });
        const data = await res.json();
        if (!data.success) {
        this.showError("Impossibile aggiornare lo stato: " + (data.error || "errore sconosciuto"));
        return false;
        }
        return true;
    } catch (e) {
        this.showError('Errore di connessione: ' + e.message);
        return false;
    }
    }


}

function renderRequestOptionsFromMeta(meta) {
  const m = parseMetadataRequest(meta);
  const saltaFila = !!(parseInt(m?.salta_fila, 10) || 0);
  const domicilio = !!(parseInt(m?.delivery, 10) || parseInt(m?.domicilio, 10) || 0);

  return `
    <div class="row mt-2">
      <div class="col-12">
        <h6><i class="fas fa-sliders-h me-2"></i>Opzioni ordine</h6>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge ${saltaFila ? 'bg-success' : 'bg-secondary'}">Salta fila: ${saltaFila ? 'Sì' : 'No'}</span>
          <span class="badge ${domicilio ? 'bg-success' : 'bg-secondary'}">Domicilio: ${domicilio ? 'Sì' : 'No'}</span>
        </div>
      </div>
    </div>
  `;
}

function nl2br(str) {
  if (!str) return "";
  return String(str).replace(/\n/g, '<br>');
}

// Helper per i metaData (stringa JSON o oggetto)
function parseMetadataRequest(meta) {
  if (!meta) return {};
  if (typeof meta === "string") {
    try { return JSON.parse(meta); } catch { return {}; }
  }
  return meta;
}

// URL completo
function buildReservationUrlImg(path) {
  if (!path) return null;
  return "https://api.assistentefarmacia.it" + path;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function productPrescriptionCell(prod) {
  const pres = prod?.prescription;

  if (!pres) {
    return `<span class="badge bg-secondary">Nessuna ricetta</span>`;
  }

  if (pres.type === "nre") {
    const nre = escapeHtml(pres.value ?? "");
    return `<strong>${nre}</strong>`;
  }

  if (pres.type === "file") {
    const v = pres.value || {};
    const url = buildReservationUrlImg(v.path);
    const filename = escapeHtml(v.filename || "allegato");

    if (!url) {
      return `<span class="badge bg-warning text-dark">File mancante</span>`;
    }

    return `<a href="${url}" target="_blank" rel="noopener" title="${filename}">Apri file</a>`;
  }

  return `<span class="badge bg-secondary">Nessuna ricetta</span>`;
}

function renderProductsTableFromMeta(meta) {
  const m = parseMetadataRequest(meta);
  const products = Array.isArray(m.products) ? m.products : [];
  if (!products.length) return "";

  const rows = products.map((p) => {
    const name = escapeHtml(p?.name ?? "—");
    const pres = productPrescriptionCell(p);

    return `
      <tr>
        <td class="col-name text-start">${name}</td>
        <td class="col-prescription">${pres}</td>
      </tr>
    `;
  }).join("");

  return `
    <div class="row mt-3">
      <div class="col-12">
        <h6><i class="fas fa-pills me-2"></i>Ricette</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th class="col-name">Nome</th>
                <th class="col-prescription">Ricetta</th>
              </tr>
            </thead>
            <tbody>
              ${rows}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `;
}

function nl2br(str) {
  if (!str) return "";
  return String(str).replace(/\n/g, '<br>');
}

// Helper per i metaData (stringa JSON o oggetto)
function parseMetadataRequest(meta) {
  if (!meta) return {};
  if (typeof meta === "string") {
    try { return JSON.parse(meta); } catch { return {}; }
  }
  return meta;
}

// URL completo
function buildReservationUrlImg(path) {
  if (!path) return null;
  return "https://api.assistentefarmacia.it" + path;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function productPrescriptionCell(prod) {
  const pres = prod?.prescription;

  if (!pres) {
    return `<span class="badge bg-secondary">Nessuna ricetta</span>`;
  }

  if (pres.type === "nre") {
    const v = pres.value || {};
    const nre = escapeHtml(v.nre ?? "");
    const cf  = escapeHtml(v.cf ?? "");

    let html = "";
    if (nre) html += `<div><strong>NRE:</strong> ${nre}</div>`;
    if (cf)  html += `<div><strong>CF:</strong> ${cf}</div>`;

    return html || `<span class="badge bg-secondary">Nessuna ricetta</span>`;
  }

  if (pres.type === "file") {
    const v = pres.value || {};
    const url = buildReservationUrlImg(v.path);
    const filename = escapeHtml(v.filename || "allegato");

    if (!url) {
      return `<span class="badge bg-warning text-dark">File mancante</span>`;
    }

    return `<a href="${url}" target="_blank" rel="noopener" title="${filename}">Apri file</a>`;
  }

  return `<span class="badge bg-secondary">Nessuna ricetta</span>`;
}


function renderProductsTableFromMeta(meta) {
  const m = parseMetadataRequest(meta);
  const products = Array.isArray(m.products) ? m.products : [];
  if (!products.length) return "";

  const rows = products.map((p) => {
    const name = escapeHtml(p?.name ?? "—");
    const pres = productPrescriptionCell(p);

    return `
      <tr>
        <td class="col-name text-start">${name}</td>
        <td class="col-prescription">${pres}</td>
      </tr>
    `;
  }).join("");

  return `
    <div class="row mt-3">
      <div class="col-12">
        <h6><i class="fas fa-pills me-2"></i>Ricette</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th class="col-name">Nome</th>
                <th class="col-prescription">Ricetta</th>
              </tr>
            </thead>
            <tbody>
              ${rows}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `;
}

// Inizializza il manager quando il DOM è caricato
document.addEventListener('DOMContentLoaded', () => {
    window.richiesteManager = new RichiesteManager();
}); 