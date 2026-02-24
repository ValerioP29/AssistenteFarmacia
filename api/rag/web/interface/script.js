// Elementi DOM per la chat
const questionInput = document.getElementById('questionInput');
const ragToggle = document.getElementById('ragToggle');
const debugToggle = document.getElementById('debugToggle');
const chatMessages = document.getElementById('chatMessages');
const loadingOverlay = document.getElementById('loadingOverlay');

// Funzioni di utilità
function showLoading() {
    loadingOverlay.style.display = 'flex';
}

function hideLoading() {
    loadingOverlay.style.display = 'none';
}

function showError(message) {
    addMessage('error', message, '❌');
}

function showSuccess(message) {
    addMessage('success', message, '✅');
}

// Funzione per chiamare l'API
async function callAPI(action, data = {}) {
    try {
        let url, method, body;
        
        if (action === 'chat') {
            url = '/rag/api/router.php?endpoint=chat';
            method = 'POST';
            body = JSON.stringify(data);
        } else if (action === 'documents' || action === 'list_documents') {
            url = '/rag/api/router.php?endpoint=documents';
            method = 'GET';
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

// Aggiungi messaggio alla chat
function addMessage(type, content, avatar = '🤖') {
    const messageDiv = document.createElement('div');
    messageDiv.className = type === 'user' ? 'user-message' : 'ai-message';
    
    const timestamp = new Date().toLocaleTimeString('it-IT', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    
    messageDiv.innerHTML = `
        <div class="message-avatar">${avatar}</div>
        <div class="message-content">
            <div class="message-text">${content}</div>
            <div class="message-time">${timestamp}</div>
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    // Scroll all'ultimo messaggio
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Aggiungi messaggio con debug info
function addMessageWithDebug(content, debugInfo = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'ai-message';
    
    const timestamp = new Date().toLocaleTimeString('it-IT', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    
    let debugHtml = '';
    if (debugInfo) {
        // Gestisci il prompt che può essere un oggetto o una stringa
        let promptText = '';
        if (typeof debugInfo.prompt === 'object' && debugInfo.prompt !== null) {
            promptText = `Sistema: ${debugInfo.prompt.system || ''}\n\nUtente: ${debugInfo.prompt.user || ''}`;
        } else if (typeof debugInfo.prompt === 'string') {
            promptText = debugInfo.prompt;
        }
        
        // Gestisci i chunk che possono essere in chunks_details o used_chunks
        const chunks = debugInfo.chunks_details || debugInfo.used_chunks || [];
        
        debugHtml = `
            <div class="debug-info" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef;">
                <details>
                    <summary style="cursor: pointer; color: #3498db; font-weight: 600;">🔍 Mostra dettagli debug</summary>
                    <div style="margin-top: 10px;">
                        <div style="margin-bottom: 15px;">
                            <strong>Chunk utilizzati:</strong> ${chunks.length}
                        </div>
                        <div style="margin-bottom: 15px;">
                            <strong>Token stimati:</strong> ${debugInfo.estimated_tokens || 0}
                        </div>
                        ${promptText ? `
                            <div style="margin-bottom: 15px;">
                                <strong>Prompt completo:</strong>
                                <details>
                                    <summary style="cursor: pointer; color: #3498db; font-weight: 600;">📝 Mostra prompt completo</summary>
                                    <div class="debug-prompt" style="margin-top: 5px; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; font-size: 11px; max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6;">
                                        ${promptText}
                                    </div>
                                </details>
                            </div>
                        ` : ''}
                        ${chunks.length > 0 ? `
                            <div style="margin-top: 10px;">
                                <strong>Fonti utilizzate:</strong>
                                <ul style="margin-top: 5px; padding-left: 20px;">
                                    ${chunks.map(chunk => 
                                        `<li>${chunk.source} (${(chunk.similarity * 100).toFixed(1)}%)</li>`
                                    ).join('')}
                                </ul>
                            </div>
                        ` : ''}
                    </div>
                </details>
            </div>
        `;
    }
    
    messageDiv.innerHTML = `
        <div class="message-avatar">🤖</div>
        <div class="message-content">
            <div class="message-text">${content}</div>
            <div class="message-time">${timestamp}</div>
            ${debugHtml}
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    // Scroll fluido all'ultimo messaggio
    setTimeout(() => {
        requestAnimationFrame(() => {
            chatMessages.scrollTo({
                top: chatMessages.scrollHeight,
                behavior: 'smooth'
            });
        });
    }, 50);
}

// Invia domanda
async function sendMessage() {
    const question = questionInput.value.trim();
    if (!question) {
        return;
    }
    
    const useRAG = ragToggle.checked;
    const debug = debugToggle.checked;
    
    // Aggiungi messaggio utente
    addMessage('user', question, '👤');
    
    // Pulisci input
    questionInput.value = '';
    
    showLoading();
    
    try {
        const result = await callAPI('chat', {
            question: question,
            use_rag: useRAG,
            debug: debug
        });
        
        const data = result.data;
        
        // Mostra warning se presente
        if (data.warning) {
            addMessage('warning', data.warning, '⚠️');
        }
        
        // Mostra warning sui token se presente
        if (data.token_warning) {
            addMessage('warning token-warning', data.token_warning, '📊');
        }
        
        // Mostra strategia di ottimizzazione se presente
        if (data.debug_info && data.debug_info.optimization_applied) {
            let strategyText = '';
            switch(data.debug_info.optimization_applied) {
                case 'compressione_selettiva':
                    strategyText = 'Ottimizzazione basata su rilevanza dei chunk';
                    break;
                case 'estrazione_frasi_chiave':
                    strategyText = 'Ottimizzazione tramite estrazione frasi chiave';
                    break;
                default:
                    strategyText = 'Strategia di ottimizzazione: ' + data.debug_info.optimization_applied;
            }
            addMessage('info', strategyText, '⚡');
        }
        
        // Aggiungi risposta AI
        if (debug && data.debug_info) {
            addMessageWithDebug(data.answer, data.debug_info);
        } else {
            addMessage('ai', data.answer);
        }
        
    } catch (error) {
        showError(`Errore nell'elaborazione: ${error.message}`);
    } finally {
        hideLoading();
    }
}



// Invio con Enter
questionInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});





// Inizializzazione
document.addEventListener('DOMContentLoaded', () => {
    // Focus sul campo input
    questionInput.focus();
    
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
    
    // Animazioni per i toggle
    [ragToggle, debugToggle].forEach(toggle => {
        toggle.addEventListener('change', () => {
            const slider = toggle.nextElementSibling;
            if (toggle.checked) {
                slider.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    slider.style.transform = '';
                }, 200);
            }
        });
    });
});



// Funzione per pulire la chat
function clearChat() {
    const chatMessages = document.getElementById('chatMessages');
    const welcomeMessage = chatMessages.querySelector('.welcome-message');
    
    // Mantieni solo il messaggio di benvenuto
    chatMessages.innerHTML = '';
    chatMessages.appendChild(welcomeMessage);
    

}

// Funzione per scorrere in fondo alla chat
function scrollToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
} 