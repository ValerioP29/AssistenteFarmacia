# 🤖 RAG AI Assistant - Versione Modulare

Un'applicazione web completa per **Retrieval-Augmented Generation (RAG)** che può essere utilizzata sia come applicazione standalone che integrata in altri progetti PHP.

## 🚀 Caratteristiche

### ✨ Funzionalità Principali
- **Chat Interface**: Interfaccia chat moderna e intuitiva
- **Caricamento Documenti**: Supporta file TXT, CSV, PDF, DOC, DOCX, RTF, ODT
- **Drag & Drop**: Caricamento file tramite drag & drop
- **RAG Intelligente**: Utilizza embedding per trovare i documenti più rilevanti
- **Ottimizzazione Token**: Gestione intelligente dei prompt troppo lunghi
- **Modalità Debug**: Visualizza prompt completo, chunk utilizzati, similarità e statistiche token
- **Gestione Documenti**: Eliminazione documenti e statistiche in tempo reale
- **Design Responsive**: Interfaccia moderna e user-friendly
- **Gestione Token**: Controllo automatico dei limiti di token
- **Architettura Modulare**: Facilmente integrabile in altri progetti

### 🔧 Tecnologie Utilizzate
- **Backend**: PHP puro (senza framework)
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **AI**: OpenAI GPT-4-turbo e text-embedding-ada-002
- **Storage**: File JSON locali (senza database)

## 📋 Requisiti

- Server web con PHP 7.4+ (Apache/Nginx)
- Estensione PHP cURL abilitata
- Connessione internet per le API OpenAI
- Chiave API OpenAI valida

## 🛠️ Installazione

1. **Clona o scarica il progetto**
   ```bash
   git clone <repository-url>
   cd RAG
   ```

2. **Configura il server web**
   - Copia tutti i file nella directory del tuo server web
   - Assicurati che PHP abbia i permessi di scrittura per la cartella `data/`

3. **Configura la chiave API**
   - Apri `config/settings.php`
   - Sostituisci la chiave API con la tua chiave OpenAI:
   ```php
   'openai_api_key' => 'la-tua-chiave-api-qui'
   ```

4. **Verifica i permessi**
   ```bash
   chmod 755 data/
   chmod 755 data/embeddings/
   ```

5. **Testa l'installazione**
   
   **Opzione A: Server PHP built-in (raccomandato per sviluppo)**
   ```bash
   php -S localhost:8000 -t .
   ```
   Poi apri `http://localhost:8000/web/interface/` nel browser
   
   **Nota:** L'interfaccia web usa percorsi assoluti (`/api/...`) per le chiamate API, quindi funziona correttamente con il server PHP built-in.
   
   **Opzione B: Server web (Apache/Nginx)**
   - Apri `web/interface/index.html` nel browser
   - Dovresti vedere l'interfaccia dell'applicazione

6. **Verifica il funzionamento**
   - Carica alcuni documenti dalla pagina "Documenti"
   - Torna alla chat e fai una domanda
   - Il sistema dovrebbe rispondere utilizzando i documenti caricati

## 🔌 Integrazione in Altri Progetti

### Metodo 1: Inclusione Diretta (Raccomandato)

Il modo più semplice per integrare il RAG in altri progetti PHP:

```php
<?php
// 1. Includi il core RAG
require_once 'path/to/rag/core/autoload.php';

// 2. Configurazione personalizzata (opzionale)
$customConfig = [
    'openai_api_key' => 'la-tua-chiave-api-qui',
    'base_prompt' => 'Sei un assistente specializzato in assistenza clienti.',
    'max_chunks' => 3,
    'data_dir' => '/path/to/your/data'
];

// 3. Inizializza il motore RAG
$rag = new RAGEngine($customConfig);

// 4. Usa il RAG
$response = $rag->query("La tua domanda qui");
echo $response['answer'];
?>
```

### Metodo 2: API REST

Per integrazioni cross-language o microservizi:

```php
<?php
// Client PHP per le API
$ragClient = new RAGClient('http://localhost/rag');

// Fai una domanda
$response = $ragClient->query("La tua domanda");
echo $response['answer'];

// Aggiungi un documento
$ragClient->addDocument("Contenuto del documento", "documento.txt");
?>
```

### Metodo 3: JavaScript/Frontend

```javascript
// Chiamata API da JavaScript
const response = await fetch('/api/chat', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        question: 'La tua domanda',
        use_rag: true,
        debug: false
    })
});

const result = await response.json();
console.log(result.data.answer);
```

## ⚙️ Configurazione

### Personalizzazione del Prompt Base

Il prompt base può essere personalizzato in due modi:

#### 1. Modifica Globale (config/settings.php)
```php
'base_prompt' => "Sei un assistente esperto in [TUA_SPECIALIZZAZIONE]. Rispondi sempre in modo [STILE_RISPOSTA]."
```

#### 2. Personalizzazione per Istanza
```php
$customConfig = [
    'base_prompt' => 'Sei un assistente specializzato in assistenza clienti. Rispondi sempre in modo professionale.'
];
$rag = new RAGEngine($customConfig);
```

### Configurazioni Avanzate

```php
$customConfig = [
    'openai_api_key' => 'your-key',
    'gpt_model' => 'gpt-4-turbo-preview',
    'embedding_model' => 'text-embedding-ada-002',
    'max_chunks' => 5,
    'chunk_size' => 200,
    'max_tokens' => 8000,
    'base_prompt' => 'Il tuo prompt personalizzato',
    'data_dir' => '/path/to/data',
    'embeddings_dir' => '/path/to/embeddings',
    'documents_dir' => '/path/to/documents'
];
```

## 📖 Come Utilizzare

### 1. Navigazione
- **Chat**: `web/interface/index.html` - Pagina principale con interfaccia chat
- **Documenti**: `web/interface/documenti.html` - Pagina per caricare e gestire i documenti

### 2. Gestione Token Intelligente
Il sistema gestisce automaticamente i prompt troppo lunghi:
- **Ottimizzazione Automatica**: Riduce intelligentemente i chunk quando necessario
- **Strategie Multiple**: Usa compressione selettiva o estrazione frasi chiave
- **Debug Avanzato**: Mostra quale strategia è stata utilizzata
- **Warning Visivi**: Avvisi colorati per token e ottimizzazioni

### 3. Caricamento Documenti

**Metodo A: Carica File Multipli**
- Trascina i file nella zona di drop o clicca per selezionare
- Supporta: TXT, CSV, PDF, DOC, DOCX, RTF, ODT
- Clicca "Carica File Selezionati"
- Il sistema dividerà automaticamente i documenti in chunk

**Metodo B: Inserisci Testo**
- Incolla il testo nella textarea
- Opzionalmente, inserisci un nome per il documento
- Clicca "Carica Testo"

### 4. Chat con AI

1. **Scrivi la tua domanda** nel campo di input
2. **Controlla i toggle**:
   - ✅ **RAG**: Attiva/disattiva l'uso dei documenti
   - 🔍 **Debug**: Mostra informazioni dettagliate
3. **Premi Invio** o clicca il pulsante per inviare
4. **Visualizza la risposta** con timestamp e dettagli debug (se attivo)

## 🔍 Modalità Debug

La modalità debug mostra informazioni dettagliate:

### Prompt Completo
```
[PROMPT BASE]
CONTENUTO RAG (chunk rilevanti)
Domanda: [DOMANDA UTENTE]
```

### Chunk Utilizzati
- **Fonte**: Nome del documento
- **Similarità**: Percentuale di rilevanza (0-100%)
- **Contenuto**: Testo del chunk utilizzato
- **Compressione**: Indicatore se il chunk è stato ottimizzato

### Statistiche Token
- **Token stimati**: Numero approssimativo di token
- **Limite massimo**: 8000 token (configurabile)
- **Status**: ✅ SOTTO IL LIMITE / ⚠️ SUPERIORE AL LIMITE
- **Ottimizzazione**: Strategia utilizzata per ridurre i token

## 🧠 Ottimizzazione Intelligente dei Token

### Problema Risolto
Quando il prompt supera il limite di 8000 token, il sistema non taglia semplicemente il testo ma utilizza strategie intelligenti per mantenere le informazioni più rilevanti.

### Strategie di Ottimizzazione

#### **Strategia 1: Compressione Selettiva**
- **Analisi**: Estrae parole chiave dalla domanda
- **Selezione**: Identifica le frasi più rilevanti
- **Compressione**: Mantiene solo le informazioni cruciali
- **Risultato**: Riduzione intelligente del testo

#### **Strategia 2: Estrazione Frasi Chiave**
- **Scansione**: Analizza tutti i chunk disponibili
- **Calcolo**: Valuta rilevanza di ogni frase
- **Combinazione**: Unisce le frasi migliori da diverse fonti
- **Risultato**: Contesto ottimizzato e coerente

## 📁 Struttura del Progetto

```
RAG/
├── core/                          # Core del motore RAG
│   ├── RAGEngine.php             # Classe principale
│   ├── OpenAIClient.php          # Client API OpenAI
│   ├── DocumentProcessor.php     # Gestione documenti
│   ├── EmbeddingManager.php      # Gestione embedding
│   ├── TokenOptimizer.php        # Ottimizzazione token
│   └── autoload.php              # Autoloader
├── api/                          # API REST
│   ├── router.php                # Router API
│   └── endpoints/
│       ├── chat.php              # Endpoint chat
│       └── documents.php         # Endpoint documenti
├── web/                          # Interfaccia web
│   └── interface/
│       ├── index.html            # Pagina chat
│       ├── documenti.html        # Pagina documenti
│       ├── style.css             # Stili CSS
│       ├── script.js             # Logica chat
│       └── documenti.js          # Logica documenti
├── config/
│   └── settings.php              # Configurazione
├── examples/                     # Esempi di integrazione
│   ├── integration_example.php   # Esempio inclusione diretta
│   └── api_integration_example.php # Esempio API
├── data/                         # Dati (generati automaticamente)
│   ├── embeddings/               # Embedding JSON
│   └── documents/                # Documenti originali
├── .htaccess                     # Configurazione server
└── README.md                     # Documentazione
```

## 🔧 Risoluzione Problemi

### Errore "Chiave API non valida"
- Verifica che la chiave API in `config/settings.php` sia corretta
- Controlla che la chiave abbia crediti sufficienti

### Errore "Nessun documento caricato"
- Carica almeno un documento prima di usare RAG
- Verifica i permessi della cartella `data/`

### Errore "Limite token superato"
- Il sistema ora ottimizza automaticamente i prompt troppo lunghi
- Riduci `max_chunks` in `config/settings.php` se necessario
- Usa documenti più piccoli per migliori performance
- Attiva debug per monitorare l'ottimizzazione

### Problemi di Performance
- Riduci la dimensione dei chunk (`chunk_size`)
- Limita il numero di documenti caricati
- Usa server con più RAM

## 📊 Monitoraggio e Manutenzione

### File di Log
I file JSON in `data/embeddings/` contengono:
```json
{
  "id": "uuid-unico",
  "source": "nome-documento",
  "text": "contenuto-chunk",
  "embedding": [0.123, -0.456, ...],
  "created_at": "2024-01-01 12:00:00"
}
```

### Pulizia Dati
- I file in `data/documents/` sono i documenti originali
- I file in `data/embeddings/` sono gli embedding generati
- Elimina i file per rimuovere documenti

## 🚀 Sviluppi Futuri

- [x] **Architettura Modulare**: Implementata separazione core/API/web
- [x] **API REST**: Endpoint separati per chat e documenti
- [x] **Integrazione Semplificata**: Esempi di inclusione diretta
- [ ] Supporto per più formati (PDF, DOCX)
- [ ] Cache degli embedding
- [ ] Autenticazione utenti
- [ ] Supporto per più modelli AI
- [ ] Database support (MySQL, PostgreSQL)

## 📄 Licenza

Questo progetto è rilasciato sotto licenza MIT.

## 🤝 Contributi

Le contribuzioni sono benvenute! Apri una issue o invia una pull request.

---

**Nota**: Questa applicazione utilizza le API OpenAI. Assicurati di rispettare i termini di servizio di OpenAI e di gestire correttamente i costi associati all'utilizzo delle API. 