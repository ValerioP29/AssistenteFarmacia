/**
 * JavaScript per la Gestione Farmacie
 * Assistente Farmacia Panel
 */

// Variabili globali
let currentFilters = {
    search: '',
    status: ''
};

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
    initializeFormHandlers();
    initializeSlugUrlGeneration();
});

// Inizializza filtri
function initializeFilters() {
    const searchInput = document.getElementById('searchPharmacy');
    const statusFilter = document.getElementById('statusFilter');

    if (searchInput) {
        searchInput.addEventListener('input', APP.utils.debounce(function() {
            currentFilters.search = searchInput.value.toLowerCase();
            filterTable();
        }, 300));
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            currentFilters.status = this.value;
            filterTable();
        });
    }
}

// Inizializza gestori form
function initializeFormHandlers() {
    const addPharmacyForm = document.getElementById('addPharmacyForm');
    const editPharmacyForm = document.getElementById('editPharmacyForm');

    if (addPharmacyForm) {
        addPharmacyForm.addEventListener('submit', handleAddPharmacy);
    }

    if (editPharmacyForm) {
        editPharmacyForm.addEventListener('submit', handleEditPharmacy);
    }
}

// Aggiorna la tabella farmacie
async function refreshPharmaciesTable() {
    try {
        const response = await APP.api.get('api/pharmacies/list.php');
        
        if (response.success) {
            updateTableContent(response.pharmacies);
            APP.ui.showAlert('Tabella aggiornata', 'success');
        } else {
            console.error('Errore nel caricamento farmacie:', response.message);
            location.reload();
        }
    } catch (error) {
        console.error('Errore nell\'aggiornamento tabella:', error);
        location.reload();
    }
}

// Aggiorna il contenuto della tabella
function updateTableContent(pharmacies) {
    const tbody = document.querySelector('#pharmaciesTable tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    pharmacies.forEach(pharmacy => {
        const row = createPharmacyRow(pharmacy);
        tbody.appendChild(row);
    });
    
    // Riapplica i filtri
    filterTable();
}

// Crea una riga della tabella per una farmacia
function createPharmacyRow(pharmacy) {
    const row = document.createElement('tr');
    row.dataset.pharmacyId = pharmacy.id;
    row.dataset.status = pharmacy.status;
    
                    row.innerHTML = `
                    <td>
                        <strong>${escapeHtml(pharmacy.nice_name || '')}</strong>
                        ${pharmacy.business_name ? `<br><small class="text-muted">${escapeHtml(pharmacy.business_name)}</small>` : ''}
                    </td>
                    <td>
                        ${pharmacy.slug_url ? `<code class="text-primary">${escapeHtml(pharmacy.slug_url)}</code>` : '<span class="text-muted">Non impostato</span>'}
                    </td>
                    <td>${escapeHtml(pharmacy.address || 'Non specificato')}</td>
                    <td>${escapeHtml(pharmacy.phone_number || 'Non specificato')}</td>
                    <td>${escapeHtml(pharmacy.email || 'Non specificato')}</td>
                    <td>
                        <span class="badge bg-${pharmacy.status === 'active' ? 'success' : 'secondary'}">
                            ${pharmacy.status === 'active' ? 'Attiva' : 'Inattiva'}
                        </span>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-warning" 
                                    onclick="editPharmacy(${pharmacy.id})"
                                    title="Modifica">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deletePharmacy(${pharmacy.id}, '${escapeHtml(pharmacy.nice_name)}')"
                                    title="Elimina">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
    
    return row;
}

// Funzione di utilità per escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Filtra tabella
function filterTable() {
    const table = document.getElementById('pharmaciesTable');
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        let show = true;

        // Filtro per stato
        if (currentFilters.status && row.dataset.status !== currentFilters.status) {
            show = false;
        }

        // Filtro per ricerca
        if (currentFilters.search) {
            const text = row.textContent.toLowerCase();
            if (!text.includes(currentFilters.search)) {
                show = false;
            }
        }

        row.style.display = show ? '' : 'none';
    });
}

// Gestisce aggiunta farmacia
async function handleAddPharmacy(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        APP.ui.showLoading(form, 'Creazione in corso...');
        
        const response = await APP.api.post('api/pharmacies/add.php', formData);
        
        if (response && typeof response === 'object' && response.success) {
            APP.ui.showAlert('Farmacia creata con successo', 'success');
            form.reset();
            
            // Controllo sicuro per il modal
            const modal = document.getElementById('addPharmacyModal');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
            
            // Aggiorna la tabella senza ricaricare la pagina
            await refreshPharmaciesTable();
        } else {
            APP.ui.showAlert(response.message || 'Errore durante la creazione', 'danger');
        }
    } catch (error) {
        console.error('Errore durante la creazione:', error);
        APP.ui.showAlert('Errore di connessione', 'danger');
    } finally {
        APP.ui.hideLoading(form);
    }
}

// Modifica farmacia
async function editPharmacy(pharmacyId) {
    try {
        // Recupera i dati della farmacia
        const response = await APP.api.get(`api/pharmacies/get.php?pharmacy_id=${pharmacyId}`);
        
        if (response.success) {
            const pharmacy = response.pharmacy;
            
                                                // Popola il form con i dati della farmacia
            document.getElementById('editPharmacyId').value = pharmacy.id;
            document.getElementById('editPharmacyName').value = pharmacy.nice_name || '';
            document.getElementById('editPharmacySlugUrl').value = pharmacy.slug_url || '';
            document.getElementById('editPharmacyBusinessName').value = pharmacy.business_name || '';
            document.getElementById('editPharmacyAddress').value = pharmacy.address || '';
            document.getElementById('editPharmacyCity').value = pharmacy.city || '';
            document.getElementById('editPharmacyPhone').value = pharmacy.phone_number || '';
            document.getElementById('editPharmacyEmail').value = pharmacy.email || '';
            document.getElementById('editPharmacyStatus').value = pharmacy.status || 'active';
            document.getElementById('editPharmacyLatLng').value = pharmacy.latlng || '';
            document.getElementById('editPharmacyDescription').value = pharmacy.description || '';
            document.getElementById('editPharmacyWorkingInfo').value = pharmacy.working_info || '';
            document.getElementById('editPharmacyPrompt').value = pharmacy.prompt || '';
            document.getElementById('editPharmacyImgAvatar').value = pharmacy.img_avatar || '';
            document.getElementById('editPharmacyImgCover').value = pharmacy.img_cover || '';
            document.getElementById('editPharmacyImgBot').value = pharmacy.img_bot || '';
            
            // Mostra preview delle immagini esistenti
            showImagePreview('editPharmacyImgAvatarPreview', pharmacy.img_avatar);
            showImagePreview('editPharmacyImgCoverPreview', pharmacy.img_cover);
            showImagePreview('editPharmacyImgBotPreview', pharmacy.img_bot);
            
            // Mostra il modal
            const modal = new bootstrap.Modal(document.getElementById('editPharmacyModal'));
            modal.show();
        } else {
            APP.ui.showAlert(response.message || 'Errore nel caricamento dati farmacia', 'danger');
        }
    } catch (error) {
        console.error('Errore:', error);
        APP.ui.showAlert('Errore di connessione', 'danger');
    }
}

// Gestisce modifica farmacia
async function handleEditPharmacy(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        APP.ui.showLoading(form, 'Salvataggio in corso...');
        
        const response = await APP.api.post('api/pharmacies/edit.php', formData);
        
        if (response && typeof response === 'object' && response.success) {
            APP.ui.showAlert('Farmacia modificata con successo', 'success');
            
            // Controllo sicuro per il modal
            const modal = document.getElementById('editPharmacyModal');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
            
            // Aggiorna la tabella senza ricaricare la pagina
            await refreshPharmaciesTable();
        } else {
            APP.ui.showAlert(response.message || 'Errore durante la modifica', 'danger');
        }
    } catch (error) {
        console.error('Errore durante la modifica:', error);
        APP.ui.showAlert('Errore di connessione', 'danger');
    } finally {
        APP.ui.hideLoading(form);
    }
}

// Elimina farmacia
async function deletePharmacy(pharmacyId, pharmacyName) {
    if (!confirm(`Sei sicuro di voler eliminare la farmacia "${pharmacyName}"? Questa azione non può essere annullata.`)) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('pharmacy_id', pharmacyId);
        formData.append('csrf_token', APP.config.csrfToken);
        
        const response = await APP.api.post('api/pharmacies/delete.php', formData);
        
        if (response.success) {
            APP.ui.showAlert('Farmacia eliminata con successo', 'success');
            // Rimuovi la riga dalla tabella
            const row = document.querySelector(`tr[data-pharmacy-id="${pharmacyId}"]`);
            if (row) {
                row.remove();
            }
        } else {
            APP.ui.showAlert(response.message || 'Errore durante l\'eliminazione', 'danger');
        }
    } catch (error) {
        console.error('Errore:', error);
        APP.ui.showAlert('Errore di connessione', 'danger');
    }
}

// Mostra preview immagine
function showImagePreview(previewId, imagePath) {
    const previewDiv = document.getElementById(previewId);
    if (previewDiv) {
        if (imagePath) {
            previewDiv.innerHTML = `
                <img src="${imagePath}" alt="Preview" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                <div class="mt-1">
                    <small class="text-muted">Immagine caricata</small>
                </div>
            `;
        } else {
            previewDiv.innerHTML = `
                <div class="text-muted">
                    <small>Nessuna immagine caricata</small>
                </div>
            `;
        }
    }
}

// Upload immagine
async function uploadImage(imageType, isEdit = false) {
    const prefix = isEdit ? 'edit' : '';
    const fileInput = document.getElementById(prefix + 'PharmacyImg' + imageType.charAt(0).toUpperCase() + imageType.slice(1) + 'File');
    const hiddenInput = document.getElementById(prefix + 'PharmacyImg' + imageType.charAt(0).toUpperCase() + imageType.slice(1));
    const previewDiv = document.getElementById(prefix + 'PharmacyImg' + imageType.charAt(0).toUpperCase() + imageType.slice(1) + 'Preview');
    
    if (!fileInput.files[0]) {
        APP.ui.showAlert('Seleziona un file da caricare', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('image', fileInput.files[0]);
    formData.append('image_type', imageType);
    formData.append('csrf_token', APP.config.csrfToken);
    
    // Se siamo in modalità modifica, aggiungi l'ID farmacia
    if (isEdit) {
        const pharmacyId = document.getElementById('editPharmacyId').value;
        if (pharmacyId) {
            formData.append('pharmacy_id', pharmacyId);
        }
    }

    try {
        // Mostra loading
        const button = fileInput.parentElement.querySelector('button');
        const originalText = button.textContent;
        button.textContent = 'Caricamento...';
        button.disabled = true;

        const response = await fetch('api/pharmacies/upload-image.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Aggiorna il campo hidden con il percorso
            hiddenInput.value = result.path;
            
            // Mostra preview
            previewDiv.innerHTML = `
                <div class="alert alert-success alert-sm">
                    <i class="bi bi-check-circle"></i> ${result.message}
                </div>
                <img src="${result.url}" alt="Preview" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
            `;
            
            APP.ui.showAlert('Immagine caricata con successo', 'success');
        } else {
            APP.ui.showAlert(result.message || 'Errore durante il caricamento', 'danger');
        }
    } catch (error) {
        console.error('Errore upload:', error);
        APP.ui.showAlert('Errore di connessione', 'danger');
    } finally {
        // Ripristina button
        const button = fileInput.parentElement.querySelector('button');
        button.textContent = originalText;
        button.disabled = false;
    }
}

// Reset filtri
function resetFilters() {
    document.getElementById('searchPharmacy').value = '';
    document.getElementById('statusFilter').value = '';
    
    currentFilters = {
        search: '',
        status: ''
    };
    
    filterTable();
}

// Genera slug URL dal nome farmacia
function generateSlugUrl(name) {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '') // Rimuovi caratteri speciali
        .replace(/\s+/g, '-') // Sostituisci spazi con trattini
        .replace(/-+/g, '-') // Rimuovi trattini multipli
        .trim('-'); // Rimuovi trattini iniziali e finali
}

// Aggiorna automaticamente lo slug URL quando cambia il nome farmacia
function initializeSlugUrlGeneration() {
    // Per il modal di aggiunta
    const pharmacyNameInput = document.getElementById('pharmacyName');
    const pharmacySlugUrlInput = document.getElementById('pharmacySlugUrl');
    
    if (pharmacyNameInput && pharmacySlugUrlInput) {
        pharmacyNameInput.addEventListener('input', function() {
            if (!pharmacySlugUrlInput.value) { // Solo se lo slug è vuoto
                pharmacySlugUrlInput.value = generateSlugUrl(this.value);
            }
        });
    }
    
    // Per il modal di modifica
    const editPharmacyNameInput = document.getElementById('editPharmacyName');
    const editPharmacySlugUrlInput = document.getElementById('editPharmacySlugUrl');
    
    if (editPharmacyNameInput && editPharmacySlugUrlInput) {
        editPharmacyNameInput.addEventListener('input', function() {
            if (!editPharmacySlugUrlInput.value) { // Solo se lo slug è vuoto
                editPharmacySlugUrlInput.value = generateSlugUrl(this.value);
            }
        });
    }
}

// Esporta funzioni globalmente
window.editPharmacy = editPharmacy;
window.deletePharmacy = deletePharmacy;
window.resetFilters = resetFilters;
window.uploadImage = uploadImage; 