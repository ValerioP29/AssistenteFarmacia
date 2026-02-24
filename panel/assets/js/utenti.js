/**
 * JavaScript per la Gestione Utenti
 * Assistente Farmacia Panel
 */

// Variabili globali
let selectedUserId = null;
let currentFilters = {
    role: '',
    pharmacy: '',
    search: ''
};

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
    initializeFormHandlers();
    initializeTableHandlers();
});

// Inizializza filtri
function initializeFilters() {
    const roleFilter = document.getElementById('roleFilter');
    const pharmacyFilter = document.getElementById('pharmacyFilter');
    const searchInput = document.getElementById('searchUser');

    if (roleFilter) {
        roleFilter.addEventListener('change', function() {
            currentFilters.role = this.value;
            filterTable();
        });
    }

    if (pharmacyFilter) {
        pharmacyFilter.addEventListener('change', function() {
            currentFilters.pharmacy = this.value;
            filterTable();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', APP.utils.debounce(function() {
            currentFilters.search = searchInput.value.toLowerCase();
            filterTable();
        }, 300));
    }
}

// Inizializza gestori form
function initializeFormHandlers() {
    const addUserForm = document.getElementById('addUserForm');
    const editUserForm = document.getElementById('editUserForm');
    const roleSelect = document.getElementById('role');
    const editRoleSelect = document.getElementById('editRole');
    const pharmacySelectGroup = document.getElementById('pharmacySelectGroup');
    const editPharmacySelectGroup = document.getElementById('editPharmacySelectGroup');

    if (addUserForm) {
        addUserForm.addEventListener('submit', handleAddUser);
        
        // Mostra il campo farmacia di default quando si apre il modal
        const modal = document.getElementById('addUserModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function() {
                const pharmacySelectGroup = document.getElementById('pharmacySelectGroup');
                if (pharmacySelectGroup) {
                    pharmacySelectGroup.style.display = 'block';
                }
            });
        }
    }

    if (editUserForm) {
        editUserForm.addEventListener('submit', handleEditUser);
        
        // Mostra il campo farmacia di default quando si apre il modal
        const modal = document.getElementById('editUserModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function() {
                const editPharmacySelectGroup = document.getElementById('editPharmacySelectGroup');
                if (editPharmacySelectGroup) {
                    editPharmacySelectGroup.style.display = 'block';
                }
            });
        }
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            // Mostra sempre il campo farmacia, indipendentemente dal ruolo
            pharmacySelectGroup.style.display = 'block';
        });
    }

    if (editRoleSelect) {
        editRoleSelect.addEventListener('change', function() {
            // Mostra sempre il campo farmacia, indipendentemente dal ruolo
            editPharmacySelectGroup.style.display = 'block';
        });
    }
}

// Inizializza gestori tabella
function initializeTableHandlers() {
    // Eventi per ordinamento (se necessario)
    const tableHeaders = document.querySelectorAll('#usersTable th');
    tableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            // Implementa ordinamento se necessario
        });
    });
}

// Aggiorna la tabella utenti
async function refreshUsersTable() {
    try {
        const response = await APP.api.get('api/users/list.php');
        
        if (response.success) {
            updateTableContent(response.users);
            APP.ui.showAlert('Tabella aggiornata', 'success');
        } else {
            console.error('Errore nel caricamento utenti:', response.message);
            // Fallback: ricarica la pagina
            location.reload();
        }
    } catch (error) {
        console.error('Errore nell\'aggiornamento tabella:', error);
        // Fallback: ricarica la pagina
        location.reload();
    }
}

// Aggiorna il contenuto della tabella
function updateTableContent(users) {
    const tbody = document.querySelector('#usersTable tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    users.forEach(user => {
        const row = createUserRow(user);
        tbody.appendChild(row);
    });
    
    // Riapplica i filtri
    filterTable();
}

// Crea una riga della tabella per un utente
function createUserRow(user) {
    const row = document.createElement('tr');
    row.dataset.userId = user.id;
    row.dataset.role = user.role;
    row.dataset.pharmacy = user.starred_pharma;
    
    row.innerHTML = `
        <td>
            <strong>${escapeHtml((user.name || '') + ' ' + (user.surname || ''))}</strong>
            <br><small class="text-muted">@${escapeHtml(user.slug_name || '')}</small>
        </td>
        <td>${escapeHtml(user.email || 'Non specificata')}</td>
                        <td>${escapeHtml(user.phone_number || 'Non specificato')}</td>
        <td>
            <span class="badge bg-${user.role === 'pharmacist' ? 'primary' : 'secondary'}">
                ${user.role === 'pharmacist' ? 'Farmacista' : 'Utente'}
            </span>
        </td>
        <td>${escapeHtml(user.pharmacy_name || 'Non assegnata')}</td>
        <td>
            ${user.last_access ? 
                `<small>${formatDateTime(user.last_access)}</small>` : 
                '<small class="text-muted">Mai</small>'
            }
        </td>
        <td>
            <div class="btn-group" role="group">
                ${user.role === 'pharmacist' ? `
                    <button class="btn btn-sm btn-outline-primary" 
                            onclick="changePharmacy(${user.id}, '${escapeHtml((user.name || '') + ' ' + (user.surname || ''))}')"
                            title="Cambia Farmacia">
                        <i class="fas fa-building"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-success" 
                            onclick="loginAsUser(${user.id})"
                            title="Accedi come">
                        <i class="fas fa-sign-in-alt"></i>
                    </button>
                ` : ''}
                <button class="btn btn-sm btn-outline-warning" 
                        onclick="editUser(${user.id})"
                        title="Modifica">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" 
                        onclick="deleteUser(${user.id}, '${escapeHtml((user.name || '') + ' ' + (user.surname || ''))}')"
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

// Funzione di utilità per formattare data
function formatDateTime(datetime) {
    if (!datetime) return '';
    const date = new Date(datetime);
    return date.toLocaleString('it-IT', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Filtra tabella
function filterTable() {
    const table = document.getElementById('usersTable');
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        let show = true;

        // Filtro per ruolo
        if (currentFilters.role && row.dataset.role !== currentFilters.role) {
            show = false;
        }

        // Filtro per farmacia
        if (currentFilters.pharmacy && String(row.dataset.pharmacy) !== String(currentFilters.pharmacy)) {
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

// Gestisce aggiunta utente
async function handleAddUser(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        APP.ui.showLoading(form, 'Creazione in corso...');
        
        const response = await APP.api.post('api/users/add.php', formData);
        
        if (response && typeof response === 'object' && response.success) {
            APP.ui.showAlert('Utente creato con successo', 'success');
            form.reset();
            
            // Controllo sicuro per pharmacySelectGroup
            const pharmacySelectGroup = document.getElementById('pharmacySelectGroup');
            if (pharmacySelectGroup) {
                pharmacySelectGroup.style.display = 'none';
            }
            
            // Controllo sicuro per il modal
            const modal = document.getElementById('addUserModal');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
            
            // Aggiorna la tabella senza ricaricare la pagina
            await refreshUsersTable();
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

// Gestisce modifica utente
async function handleEditUser(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        APP.ui.showLoading(form, 'Salvataggio in corso...');
        
        const response = await APP.api.post('api/users/edit.php', formData);
        
        if (response && typeof response === 'object' && response.success) {
            APP.ui.showAlert('Utente modificato con successo', 'success');
            
            // Controllo sicuro per il modal
            const modal = document.getElementById('editUserModal');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
            
            // Aggiorna la tabella senza ricaricare la pagina
            await refreshUsersTable();
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

// Cambia farmacia per un utente
function changePharmacy(userId, userName) {
    selectedUserId = userId;
    document.getElementById('selectedUserName').textContent = userName;
    
    // Mostra il modal
    const modal = new bootstrap.Modal(document.getElementById('changePharmacyModal'));
    modal.show();
}

// Salva cambio farmacia
async function savePharmacyChange() {
    const pharmacySelect = document.getElementById('newPharmacySelect');
    const pharmacyId = pharmacySelect.value;
    
    if (!pharmacyId) {
        APP.ui.showAlert('Seleziona una farmacia', 'warning');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('user_id', selectedUserId);
        formData.append('pharmacy_id', pharmacyId);
        formData.append('csrf_token', APP.config.csrfToken);
        
        const response = await APP.api.post('api/users/change-pharmacy.php', formData);
        
        if (response.success) {
            APP.ui.showAlert('Farmacia cambiata con successo', 'success');
            bootstrap.Modal.getInstance(document.getElementById('changePharmacyModal')).hide();
            
            // Aggiorna la tabella senza ricaricare la pagina
            await refreshUsersTable();
        } else {
            APP.ui.showAlert(response.message || 'Errore durante il cambio farmacia', 'danger');
        }
    } catch (error) {
        console.error('Errore:', error);
        APP.ui.showAlert('Errore di connessione', 'danger');
    }
}

// Accedi come utente
async function loginAsUser(userId) {
    if (!confirm('Sei sicuro di voler accedere come questa farmacia? Potrai tornare alla lista utenti in qualsiasi momento.')) {
        return;
    }
    
    try {
        // Ottieni il token CSRF dal meta tag o dal form
        let csrfToken = '';
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        
        if (metaToken) {
            csrfToken = metaToken.getAttribute('content');
        } else if (window.APP_CONFIG && window.APP_CONFIG.csrfToken) {
            csrfToken = window.APP_CONFIG.csrfToken;
        } else {
            // Fallback: cerca nel form
            const formToken = document.querySelector('input[name="csrf_token"]');
            if (formToken) {
                csrfToken = formToken.value;
            }
        }
        
        if (!csrfToken) {
            throw new Error('Token CSRF non trovato');
        }
        
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('csrf_token', csrfToken);
        
        // Prova prima con APP.api, se fallisce usa fetch diretto
        let response;
        try {
            response = await APP.api.post('api/users/login-as.php', formData);
        } catch (apiError) {
            // Fallback con fetch diretto
            const fetchResponse = await fetch('api/users/login-as.php', {
                method: 'POST',
                body: formData
            });
            
            if (!fetchResponse.ok) {
                throw new Error(`HTTP ${fetchResponse.status}: ${fetchResponse.statusText}`);
            }
            
            response = await fetchResponse.json();
        }
        
        if (response.success) {
            APP.ui.showAlert('Accesso effettuato come farmacia. Ora puoi operare a nome della farmacia.', 'success');
            // Reindirizza alla dashboard dell'utente
            setTimeout(() => window.location.href = 'dashboard.php', 1000);
        } else {
            APP.ui.showAlert(response.message || 'Errore durante l\'accesso', 'danger');
        }
    } catch (error) {
        console.error('Errore:', error);
        APP.ui.showAlert('Errore di connessione: ' + error.message, 'danger');
    }
}

// Torna admin
async function returnToAdmin() {
    if (!confirm('Sei sicuro di voler tornare alla lista utenti?')) {
        return;
    }
    
    try {
        // Ottieni il token CSRF dal meta tag o dal form
        let csrfToken = '';
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            csrfToken = metaToken.getAttribute('content');
        } else if (window.APP_CONFIG && window.APP_CONFIG.csrfToken) {
            csrfToken = window.APP_CONFIG.csrfToken;
        } else {
            // Fallback: cerca nel form
            const formToken = document.querySelector('input[name="csrf_token"]');
            if (formToken) {
                csrfToken = formToken.value;
            }
        }
        
        if (!csrfToken) {
            throw new Error('Token CSRF non trovato');
        }
        
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        
        const response = await APP.api.post('api/users/return-admin.php', formData);
        
        if (response.success) {
            APP.ui.showAlert('Tornato alla lista utenti', 'success');
            // Reindirizza alla lista utenti
            setTimeout(() => window.location.href = 'utenti.php', 1000);
        } else {
            APP.ui.showAlert(response.message || 'Errore durante il ritorno', 'danger');
        }
    } catch (error) {
        console.error('Errore:', error);
        APP.ui.showAlert('Errore di connessione: ' + error.message, 'danger');
    }
}

// Modifica utente
async function editUser(userId) {
    try {
        // Recupera i dati dell'utente
        const response = await APP.api.get(`api/users/get.php?user_id=${userId}`);
        
        if (response.success) {
            const user = response.user;
            
            // Popola il form con i dati dell'utente
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editFirstName').value = user.name || '';
            document.getElementById('editLastName').value = user.surname || '';
            document.getElementById('editUsername').value = user.slug_name || '';
            document.getElementById('editEmail').value = user.email || '';
            document.getElementById('editPhone').value = user.phone_number || '';
            document.getElementById('editRole').value = user.role || '';
            document.getElementById('editStatus').value = user.status || 'active';
            document.getElementById('editPassword').value = ''; // Password sempre vuota per sicurezza
            
            // Mostra sempre il campo farmacia e popola il valore
            const editPharmacySelectGroup = document.getElementById('editPharmacySelectGroup');
            if (editPharmacySelectGroup) {
                editPharmacySelectGroup.style.display = 'block';
            }
            document.getElementById('editPharmacy').value = user.starred_pharma || '';
            
            // Mostra il modal
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        } else {
            APP.ui.showAlert(response.message || 'Errore nel caricamento dati utente', 'danger');
        }
    } catch (error) {
        console.error('Errore:', error);
        APP.ui.showAlert('Errore di connessione', 'danger');
    }
}

// Elimina utente
async function deleteUser(userId, userName) {
    if (!confirm(`Sei sicuro di voler eliminare l'utente "${userName}"? Questa azione non può essere annullata.`)) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('csrf_token', APP.config.csrfToken);
        

        
        const response = await APP.api.post('api/users/delete.php', formData);
        
        if (response.success) {
            APP.ui.showAlert('Utente eliminato con successo', 'success');
            // Rimuovi la riga dalla tabella
            const row = document.querySelector(`tr[data-user-id="${userId}"]`);
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

// Esporta utenti (funzione aggiuntiva)
function exportUsers() {
    const table = document.getElementById('usersTable');
    const rows = Array.from(table.querySelectorAll('tbody tr:not([style*="display: none"])'));
    
    let csv = 'Nome,Email,Telefono,Ruolo,Farmacia,Ultimo Accesso\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const name = cells[0].textContent.trim().split('\n')[0];
        const email = cells[1].textContent.trim();
                    const phone_number = cells[2].textContent.trim();
        const role = cells[3].textContent.trim();
        const pharmacy = cells[4].textContent.trim();
        const lastAccess = cells[5].textContent.trim();
        
                    csv += `"${name}","${email}","${phone_number}","${role}","${pharmacy}","${lastAccess}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'utenti_export.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Funzioni di utilità
function resetFilters() {
    document.getElementById('roleFilter').value = '';
    document.getElementById('pharmacyFilter').value = '';
    document.getElementById('searchUser').value = '';
    
    currentFilters = {
        role: '',
        pharmacy: '',
        search: ''
    };
    
    filterTable();
}

// Esporta funzioni globalmente
window.changePharmacy = changePharmacy;
window.savePharmacyChange = savePharmacyChange;
window.loginAsUser = loginAsUser;
window.returnToAdmin = returnToAdmin;
window.editUser = editUser;
window.deleteUser = deleteUser;
window.exportUsers = exportUsers;
window.resetFilters = resetFilters; 