// Elementi DOM per la pagina documenti
const fileInput = document.getElementById('fileInput');
const fileDropZone = document.getElementById('fileDropZone');
const uploadFileBtn = document.getElementById('uploadFileBtn');
const textInput = document.getElementById('textInput');
const filenameInput = document.getElementById('filenameInput');
const uploadTextBtn = document.getElementById('uploadTextBtn');
const uploadStatus = document.getElementById('uploadStatus');
const documentsList = document.getElementById('documentsList');
const refreshDocsBtn = document.getElementById('refreshDocsBtn');
const loadingOverlay = document.getElementById('loadingOverlay');

// Elementi statistiche
const totalDocuments = document.getElementById('totalDocuments');
const totalChunks = document.getElementById('totalChunks');
const totalSize = document.getElementById('totalSize');
const lastUpload = document.getElementById('lastUpload');

// Funzioni di utilità
function showLoading() {
    loadingOverlay.style.display = 'flex';
}

function hideLoading() {
    loadingOverlay.style.display = 'none';
}

function showStatus(message, type = 'success') {
    uploadStatus.textContent = message;
    uploadStatus.className = `status-message ${type}`;
    setTimeout(() => {
        uploadStatus.textContent = '';
        uploadStatus.className = 'status-message';
    }, 5000);
}

function showError(message) {
    showStatus(message, 'error');
}

function showSuccess(message) {
    showStatus(message, 'success');
}

// Funzione per chiamare l'API
async function callAPI(action, data = {}) {
    try {
        let url, method, body;
        
        if (action === 'upload_file') {
            url = '/rag/api/router.php?endpoint=documents';
            method = 'POST';
            body = JSON.stringify(data);
        } else if (action === 'upload_text') {
            url = '/rag/api/router.php?endpoint=documents';
            method = 'POST';
            body = JSON.stringify(data);
        } else if (action === 'list_documents') {
            url = '/rag/api/router.php?endpoint=documents';
            method = 'GET';
        } else if (action === 'delete_document') {
            url = '/rag/api/router.php?endpoint=documents';
            method = 'DELETE';
            body = JSON.stringify(data);
        } else {
            throw new Error('Azione non supportata');
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: body
        });
        
        if (!response.ok) {
            throw new Error(`Errore HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Errore sconosciuto');
        }
        
        return result;
    } catch (error) {
        console.error('Errore API:', error);
        throw error;
    }
}

// Gestione drag & drop
fileDropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileDropZone.classList.add('dragover');
});

fileDropZone.addEventListener('dragleave', (e) => {
    e.preventDefault();
    fileDropZone.classList.remove('dragover');
});

fileDropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    fileDropZone.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        updateFileList();
    }
});

// Click sulla drop zone
fileDropZone.addEventListener('click', () => {
    fileInput.click();
});

// Aggiorna lista file selezionati
function updateFileList() {
    const files = fileInput.files;
    if (files.length > 0) {
        const fileNames = Array.from(files).map(f => f.name).join(', ');
        fileDropZone.querySelector('.drop-zone-content p').textContent = 
            `${files.length} file selezionati: ${fileNames}`;
    } else {
        fileDropZone.querySelector('.drop-zone-content p').textContent = 
            'Trascina i file qui o clicca per selezionare';
    }
}

fileInput.addEventListener('change', updateFileList);

// Caricamento file multipli
uploadFileBtn.addEventListener('click', async () => {
    const files = fileInput.files;
    if (files.length === 0) {
        showError('Seleziona almeno un file prima di caricarlo');
        return;
    }
    
    showLoading();
    let successCount = 0;
    let errorCount = 0;
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        try {
            // Verifica tipo file
            const allowedTypes = ['.txt', '.csv', '.pdf', '.doc', '.docx', '.rtf', '.odt'];
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(fileExtension)) {
                showError(`Tipo file non supportato: ${file.name}`);
                errorCount++;
                continue;
            }
            
            // Leggi contenuto file
            let content;
            if (fileExtension === '.txt' || fileExtension === '.csv') {
                content = await file.text();
            } else {
                // Per ora supportiamo solo testo, in futuro si può aggiungere parsing PDF/DOC
                content = await file.text();
            }
            
            const result = await callAPI('upload_file', {
                content: content,
                filename: file.name
            });
            
            successCount++;
            showSuccess(`File ${file.name} caricato con successo!`);
            
        } catch (error) {
            errorCount++;
            showError(`Errore nel caricamento di ${file.name}: ${error.message}`);
        }
    }
    
    // Risultato finale
    if (successCount > 0) {
        showSuccess(`${successCount} file caricati con successo${errorCount > 0 ? `, ${errorCount} errori` : ''}`);
        fileInput.value = '';
        updateFileList();
        loadDocuments();
        updateStats();
    }
    
    hideLoading();
});

// Caricamento testo
uploadTextBtn.addEventListener('click', async () => {
    const content = textInput.value.trim();
    if (!content) {
        showError('Inserisci del testo prima di caricarlo');
        return;
    }
    
    showLoading();
    
    try {
        const filename = filenameInput.value.trim() || `documento_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}`;
        
        const result = await callAPI('upload_text', {
            content: content,
            filename: filename
        });
        
        showSuccess(`Documento caricato con successo! Creati ${result.total_chunks} chunk.`);
        textInput.value = '';
        filenameInput.value = '';
        loadDocuments();
        updateStats();
    } catch (error) {
        showError(`Errore nel caricamento: ${error.message}`);
    } finally {
        hideLoading();
    }
});

// Caricamento lista documenti
async function loadDocuments() {
    try {
        const result = await callAPI('list_documents');
        
        if (result.data.documents.length === 0) {
            documentsList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <h3>Nessun documento caricato</h3>
                    <p>Carica i tuoi primi documenti per iniziare a utilizzare il RAG!</p>
                </div>
            `;
        } else {
            documentsList.innerHTML = result.data.documents.map(doc => `
                <div class="document-item">
                    <div class="document-info">
                        <h4>${doc.source}</h4>
                        <p>${doc.chunks} chunk • Caricato il ${new Date(doc.created_at).toLocaleDateString('it-IT')}</p>
                    </div>
                    <div class="document-actions">
                        <button class="btn btn-secondary btn-sm" onclick="deleteDocument('${doc.source}')">🗑️</button>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Errore nel caricamento documenti:', error);
        documentsList.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">❌</div>
                <h3>Errore nel caricamento</h3>
                <p>Impossibile caricare la lista dei documenti</p>
            </div>
        `;
    }
}

// Aggiorna statistiche
async function updateStats() {
    try {
        const result = await callAPI('list_documents');
        
        const totalDocs = result.data.documents.length;
        const totalChunksCount = result.data.documents.reduce((sum, doc) => sum + doc.chunks, 0);
        const lastUploadDate = result.data.documents.length > 0 ? 
            new Date(Math.max(...result.data.documents.map(d => new Date(d.created_at)))).toLocaleDateString('it-IT') : '-';
        
        totalDocuments.textContent = totalDocs;
        totalChunks.textContent = totalChunksCount;
        totalSize.textContent = `${Math.round(totalChunksCount * 0.5)} KB`; // Stima approssimativa
        lastUpload.textContent = lastUploadDate;
        
    } catch (error) {
        console.error('Errore nel caricamento statistiche:', error);
    }
}

// Elimina documento
async function deleteDocument(source) {
    if (confirm(`Sei sicuro di voler eliminare il documento "${source}"?`)) {
        showLoading();
        
        try {
            const result = await callAPI('delete_document', {
                id: source
            });
            
            showSuccess(`Documento eliminato con successo!`);
            
            // Forza il refresh immediato
            setTimeout(() => {
                loadDocuments();
                updateStats();
            }, 100);
            
        } catch (error) {
            showError(`Errore nell'eliminazione: ${error.message}`);
        } finally {
            hideLoading();
        }
    }
}

// Aggiorna lista documenti
refreshDocsBtn.addEventListener('click', () => {
    loadDocuments();
    updateStats();
});

// Miglioramenti UX
document.addEventListener('DOMContentLoaded', () => {
    // Carica dati iniziali
    loadDocuments();
    updateStats();
    
    // Animazioni per i bottoni
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Validazione input testo
    textInput.addEventListener('input', () => {
        const charCount = textInput.value.length;
        const maxChars = 50000;
        
        if (charCount > maxChars) {
            textInput.value = textInput.value.substring(0, maxChars);
            showError(`Limite di ${maxChars} caratteri raggiunto`);
        }
    });
    
    // Contatore caratteri
    textInput.addEventListener('input', () => {
        const charCount = textInput.value.length;
        const maxChars = 50000;
        const remaining = maxChars - charCount;
        
        // Aggiorna placeholder con contatore
        if (charCount > 0) {
            textInput.placeholder = `${charCount}/${maxChars} caratteri`;
        } else {
            textInput.placeholder = 'Incolla qui il testo del documento...';
        }
    });
});

// Supporto per file di grandi dimensioni
function validateFileSize(file) {
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        showError(`File troppo grande: ${file.name} (${Math.round(file.size / 1024 / 1024)}MB). Limite: 10MB`);
        return false;
    }
    return true;
}

// Aggiungi validazione dimensione file
fileInput.addEventListener('change', () => {
    const files = fileInput.files;
    for (let file of files) {
        if (!validateFileSize(file)) {
            fileInput.value = '';
            updateFileList();
            return;
        }
    }
    updateFileList();
}); 