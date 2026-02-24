/**
 * JavaScript per la gestione degli orari farmacia
 * Assistente Farmacia Panel
 */

document.addEventListener('DOMContentLoaded', function() {
    const orariForm = document.getElementById('orariForm');
    const turnoForm = document.getElementById('turnoForm');
    
    if (orariForm) {
        orariForm.addEventListener('submit', handleOrariSubmit);
        
        // Gestione checkbox "Chiuso"
        const closedCheckboxes = document.querySelectorAll('.day-closed-checkbox');
        closedCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', handleDayClosedChange);
        });
    }
    
    if (turnoForm) {
        turnoForm.addEventListener('submit', handleTurnoSubmit);
    }
});

/**
 * Gestisce l'invio del form degli orari
 */
async function handleOrariSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    // Disabilita il pulsante e mostra loading
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Salvataggio...';
    
    try {
        // Validazione form
        if (!validateOrariForm(form)) {
            throw new Error('Per favore correggi gli errori nel form');
        }
        
        // Prepara i dati del form
        const formData = new FormData(form);
        
        // Invia la richiesta
        const response = await fetch('api/pharmacies/update-hours.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Successo
            showAlert('Orari aggiornati con successo!', 'success');
        } else {
            // Errore dal server
            throw new Error(result.message || 'Errore durante il salvataggio');
        }
        
    } catch (error) {
        console.error('Errore:', error);
        showAlert(error.message || 'Errore durante il salvataggio degli orari', 'danger');
    } finally {
        // Ripristina il pulsante
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
}

/**
 * Gestisce l'invio del form del turno
 */
async function handleTurnoSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    // Disabilita il pulsante e mostra loading
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Salvataggio...';
    
    try {
        // Prepara i dati del form
        const formData = new FormData(form);
        
        // Aggiungi CSRF token
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        formData.append('csrf_token', csrfToken);
        
        // Invia la richiesta
        const response = await fetch('api/pharmacies/update-turno.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Successo
            showAlert('Turno aggiornato con successo!', 'success');
        } else {
            // Errore dal server
            throw new Error(result.message || 'Errore durante il salvataggio');
        }
        
    } catch (error) {
        console.error('Errore:', error);
        showAlert(error.message || 'Errore durante il salvataggio del turno', 'danger');
    } finally {
        // Ripristina il pulsante
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
}

/**
 * Gestisce il cambio di stato "Chiuso" per un giorno
 */
function handleDayClosedChange(event) {
    const checkbox = event.target;
    const day = checkbox.dataset.day;
    const row = checkbox.closest('tr');
    
    // Trova tutti gli input di tempo nella riga
    const timeInputs = row.querySelectorAll('input[type="time"]');
    
    if (checkbox.checked) {
        // Se è chiuso, disabilita tutti gli input di tempo
        timeInputs.forEach(input => {
            input.disabled = true;
            input.classList.add('text-muted');
        });
    } else {
        // Se è aperto, abilita tutti gli input di tempo
        timeInputs.forEach(input => {
            input.disabled = false;
            input.classList.remove('text-muted');
        });
    }
}

/**
 * Valida il form degli orari
 */
function validateOrariForm(form) {
    const days = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
    let isValid = true;
    
    days.forEach(day => {
        const closedCheckbox = form.querySelector(`input[name="${day}_chiuso"]`);
        const isClosed = closedCheckbox && closedCheckbox.checked;
        
        if (!isClosed) {
            // Verifica orari mattina
            const morningOpen = form.querySelector(`input[name="${day}_mattina_apertura"]`);
            const morningClose = form.querySelector(`input[name="${day}_mattina_chiusura"]`);
            
            if (!morningOpen.value || !morningClose.value) {
                showFieldError(morningOpen, 'Orari mattina obbligatori');
                isValid = false;
            } else if (morningOpen.value >= morningClose.value) {
                showFieldError(morningOpen, 'Apertura deve essere prima della chiusura');
                isValid = false;
            }
            
            // Verifica orari pomeriggio
            const afternoonOpen = form.querySelector(`input[name="${day}_pomeriggio_apertura"]`);
            const afternoonClose = form.querySelector(`input[name="${day}_pomeriggio_chiusura"]`);
            
            if (!afternoonOpen.value || !afternoonClose.value) {
                showFieldError(afternoonOpen, 'Orari pomeriggio obbligatori');
                isValid = false;
            } else if (afternoonOpen.value >= afternoonClose.value) {
                showFieldError(afternoonOpen, 'Apertura deve essere prima della chiusura');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

/**
 * Mostra errore per un campo
 */
function showFieldError(field, message) {
    // Rimuovi errori precedenti
    clearFieldError({ target: field });
    
    // Aggiungi classe di errore
    field.classList.add('is-invalid');
    
    // Crea messaggio di errore
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    
    // Inserisci dopo il campo
    field.parentNode.appendChild(errorDiv);
}

/**
 * Rimuove errore da un campo
 */
function clearFieldError(event) {
    const field = event.target;
    
    // Rimuovi classe di errore
    field.classList.remove('is-invalid');
    
    // Rimuovi messaggio di errore
    const errorDiv = field.parentNode.querySelector('.invalid-feedback');
    if (errorDiv) {
        errorDiv.remove();
    }
}

/**
 * Ripristina il form agli orari originali
 */
function resetForm() {
    if (confirm('Sei sicuro di voler ripristinare gli orari originali? Le modifiche non salvate andranno perse.')) {
        window.location.reload();
    }
}

/**
 * Mostra un alert
 */
function showAlert(message, type = 'info') {
    // Crea l'alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
    `;
    
    // Inserisci all'inizio del contenuto principale
    const mainContent = document.getElementById('main-content');
    if (mainContent) {
        mainContent.insertBefore(alertDiv, mainContent.firstChild);
        
        // Auto-rimuovi dopo 5 secondi
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
} 