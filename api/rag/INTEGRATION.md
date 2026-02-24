# 🔌 Guida all'Integrazione RAG Engine

Questa guida spiega come integrare il RAG Engine in altri progetti PHP.

## 📋 Panoramica

Il RAG Engine è stato progettato per essere facilmente integrabile in altri progetti. Offre tre metodi di integrazione:

1. **Inclusione Diretta** - Per progetti PHP
2. **API REST** - Per integrazioni cross-language
3. **JavaScript/Frontend** - Per applicazioni web

## 🚀 Metodo 1: Inclusione Diretta (Raccomandato)

### Requisiti
- PHP 7.4+
- Estensione cURL
- Chiave API OpenAI

### File Necessari vs Opzionali

**File Necessari per l'Integrazione:**
- `core/` - Tutto il motore RAG
- `config/settings.php` - Configurazione di base

**File Opzionali:**
- `data/` - Dati esistenti (embedding e documenti)

**File da Rimuovere (solo per applicazione standalone):**
- `avvia_server.php` - Server di sviluppo
- `test_rag.php` - File di test (rimosso)
- `.htaccess` - Configurazione server web
- `INTEGRATION.md` - Questa guida
- `README.md` - Documentazione progetto

**File Opzionali (utili ma rimovibili):**
- `web/` - Interfaccia web completa (utile per test e gestione documenti)
- `api/` - API REST (utile per integrazioni remote)
- `examples/` - Esempi di integrazione (utili per capire come usare il RAG)
- `documenti_esempio/` - File di esempio per testare il caricamento

### Installazione

1. **Copia il core RAG nel tuo progetto**
   ```bash
   cp -r rag/core/ /path/to/your/project/
   cp -r rag/config/ /path/to/your/project/
   ```

2. **Rimuovi i file specifici del progetto standalone**
   ```bash
   # File da rimuovere (specifici per l'applicazione standalone)
   rm -f avvia_server.php
   # rm -f test_rag.php  # File già rimosso
   # rm -rf web/          # Opzionale: interfaccia web per test
   # rm -rf api/          # Opzionale: API REST per integrazioni remote
   # rm -rf examples/     # Opzionale: esempi di integrazione
   # rm -rf documenti_esempio/  # Opzionale: file di esempio
   rm -f .htaccess
   rm -f INTEGRATION.md
   rm -f README.md
   
   # Mantieni solo:
   # - core/ (motore RAG)
   # - config/ (configurazione)
   # - data/ (dati, se vuoi mantenere quelli esistenti)
   ```

2. **Includi il core nel tuo codice**
   ```php
   <?php
   require_once 'core/autoload.php';
   
   // Configurazione personalizzata
   $config = [
       'openai_api_key' => 'your-api-key',
       'base_prompt' => 'Sei un assistente specializzato in [TUA_SPECIALIZZAZIONE]',
       'data_dir' => '/path/to/your/data'
   ];
   
   // Inizializza il RAG
   $rag = new RAGEngine($config);
?>
```

### Vantaggi dei File Opzionali

**Mantenere `web/`:**
- ✅ Interfaccia grafica per testare il RAG
- ✅ Gestione visuale dei documenti
- ✅ Debug e monitoraggio facilitato
- ✅ Possibilità di usare il RAG anche senza codice

**Mantenere `api/`:**
- ✅ Integrazioni remote via HTTP
- ✅ Supporto per applicazioni frontend
- ✅ Microservizi e architetture distribuite
- ✅ Integrazione con altri linguaggi

**Mantenere `examples/`:**
- ✅ Esempi pratici di utilizzo
- ✅ Riferimento per implementazioni
- ✅ Test di funzionalità
- ✅ Documentazione interattiva

**Mantenere `documenti_esempio/`:**
- ✅ File pronti per testare il caricamento
- ✅ Esempi di formati supportati
- ✅ Test di funzionalità RAG
- ✅ Dimostrazione delle capacità

### Integrazione Minima (Solo Core)

Se vuoi solo il motore RAG senza interfaccia web o API:

```bash
# Copia solo i file essenziali
cp -r rag/core/ /path/to/your/project/
cp rag/config/settings.php /path/to/your/project/config/

# Crea la directory per i dati
mkdir -p /path/to/your/project/data/embeddings
mkdir -p /path/to/your/project/data/documents
```

**Struttura minima risultante:**
```
your-project/
├── core/           # Motore RAG
├── config/         # Configurazione
└── data/           # Dati (opzionale)
    ├── embeddings/
    └── documents/
```

**Struttura completa (con file opzionali):**
```
your-project/
├── core/           # Motore RAG
├── config/         # Configurazione
├── web/            # Interfaccia web (opzionale)
├── api/            # API REST (opzionale)
├── examples/       # Esempi (opzionale)
├── documenti_esempio/  # File di esempio (opzionale)
└── data/           # Dati
    ├── embeddings/
    └── documents/
```

### Utilizzo Base

```php
<?php
// Fai una domanda
$response = $rag->query("La tua domanda qui");
echo $response['answer'];

// Aggiungi un documento
$result = $rag->addDocument("Contenuto del documento", "documento.txt");
echo "Chunk creati: " . $result['chunks_created'];

// Ottieni statistiche
$stats = $rag->getStats();
echo "Documenti: " . $stats['total_documents'];
?>
```

### Configurazione Avanzata

```php
<?php
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

$rag = new RAGEngine($customConfig);
?>
```

## 🌐 Metodo 2: API REST

**Nota:** L'interfaccia web usa automaticamente gli URL corretti:

- **Server PHP built-in (`php -S`)**: Usa `/api/router.php?endpoint=...`
- **Apache/Nginx con mod_rewrite**: Usa `/api/...` (grazie al file `.htaccess`)

Entrambi i formati sono supportati dal router API.

### Endpoint Disponibili

#### POST /api/chat
Esegue una query RAG.

**Parametri:**
```json
{
    "question": "La tua domanda",
    "use_rag": true,
    "debug": false,
    "max_chunks": 5,
    "max_tokens": 8000,
    "config": {
        "base_prompt": "Prompt personalizzato"
    }
}
```

**Risposta:**
```json
{
    "success": true,
    "data": {
        "answer": "Risposta dell'AI",
        "tokens_used": 785,
        "rag_used": true,
        "chunks_used": [...],
        "debug_info": {...}
    }
}
```

#### GET /api/documents
Lista tutti i documenti.

**Risposta:**
```json
{
    "success": true,
    "data": {
        "documents": [...],
        "stats": {
            "total_documents": 5,
            "total_embeddings": 25
        }
    }
}
```

#### POST /api/documents
Aggiunge un documento.

**Parametri:**
```json
{
    "content": "Contenuto del documento",
    "filename": "documento.txt",
    "metadata": {
        "category": "manuale",
        "version": "1.0"
    }
}
```

#### DELETE /api/documents
Rimuove un documento.

**Parametri:**
```json
{
    "id": "document-id"
}
```

### Client PHP

```php
<?php
class RAGClient {
    private $baseUrl;
    
    public function __construct($baseUrl = 'http://localhost/rag') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    public function query($question, $options = []) {
        $data = array_merge([
            'question' => $question,
            'use_rag' => true,
            'debug' => false
        ], $options);
        
        return $this->makeRequest('POST', '/api/chat', $data);
    }
    
    public function addDocument($content, $filename = '', $metadata = []) {
        $data = [
            'content' => $content,
            'filename' => $filename,
            'metadata' => $metadata
        ];
        
        return $this->makeRequest('POST', '/api/documents', $data);
    }
    
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => 'Content-Type: application/json',
                'ignore_errors' => true
            ]
        ];
        
        if ($data && $method !== 'GET') {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        $result = json_decode($response, true);
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Errore sconosciuto');
        }
        
        return $result['data'];
    }
}

// Utilizzo
$ragClient = new RAGClient('http://localhost/rag');
$response = $ragClient->query("La tua domanda");
echo $response['answer'];
?>
```

## 🎨 Metodo 3: JavaScript/Frontend

### Chiamate API da JavaScript

```javascript
// Query RAG
async function queryRAG(question) {
    const response = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            question: question,
            use_rag: true,
            debug: false
        })
    });
    
    const result = await response.json();
    return result.data;
}

// Aggiungi documento
async function addDocument(content, filename) {
    const response = await fetch('/api/documents', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            content: content,
            filename: filename
        })
    });
    
    const result = await response.json();
    return result.data;
}

// Lista documenti
async function listDocuments() {
    const response = await fetch('/api/documents');
    const result = await response.json();
    return result.data;
}

// Utilizzo
queryRAG("La tua domanda").then(response => {
    console.log(response.answer);
});
```

## ⚙️ Personalizzazione del Prompt

### Modifica Globale

Modifica `config/settings.php`:

```php
'base_prompt' => "Sei un assistente esperto in [TUA_SPECIALIZZAZIONE]. Rispondi sempre in modo [STILE_RISPOSTA]."
```

### Personalizzazione per Istanza

```php
$customConfig = [
    'base_prompt' => 'Sei un assistente specializzato in assistenza clienti. Rispondi sempre in modo professionale.'
];
$rag = new RAGEngine($customConfig);
```

### Personalizzazione via API

```json
{
    "question": "La tua domanda",
    "config": {
        "base_prompt": "Prompt personalizzato per questa sessione"
    }
}
```

## 🔧 Configurazioni Avanzate

### Gestione dei Dati

```php
// Percorsi personalizzati per i dati
$config = [
    'data_dir' => '/path/to/your/data',
    'embeddings_dir' => '/path/to/your/embeddings',
    'documents_dir' => '/path/to/your/documents'
];
```

### Ottimizzazione Performance

```php
// Riduci il numero di chunk per migliori performance
$config = [
    'max_chunks' => 3,
    'chunk_size' => 150,
    'max_tokens' => 6000
];
```

### Debug e Monitoraggio

```php
// Abilita debug per monitorare il processo
$response = $rag->query("La tua domanda", [
    'debug' => true
]);

if ($response['debug_info']) {
    echo "Ottimizzazione: " . $response['debug_info']['optimization_applied'];
    echo "Chunk utilizzati: " . count($response['debug_info']['chunks_details']);
}
```

## 🚨 Gestione Errori

### Errori Comuni

```php
try {
    $response = $rag->query("La tua domanda");
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Chiave API non valida') !== false) {
        echo "Errore: Configura la chiave API OpenAI";
    } elseif (strpos($e->getMessage(), 'Nessun documento caricato') !== false) {
        echo "Errore: Carica almeno un documento";
    } else {
        echo "Errore generico: " . $e->getMessage();
    }
}
```

### Validazione Input

```php
// Valida la domanda prima di inviarla
function validateQuestion($question) {
    if (empty(trim($question))) {
        throw new Exception('La domanda non può essere vuota');
    }
    
    if (strlen($question) > 1000) {
        throw new Exception('La domanda è troppo lunga');
    }
    
    return true;
}

// Utilizzo
validateQuestion($question);
$response = $rag->query($question);
```

## 📊 Monitoraggio e Logging

### Statistiche di Utilizzo

```php
// Ottieni statistiche complete
$stats = $rag->getStats();

echo "Documenti totali: " . $stats['total_documents'];
echo "Embedding totali: " . $stats['total_embeddings'];

foreach ($stats['documents'] as $doc) {
    echo "Documento: " . $doc['source'] . " (" . $doc['chunks'] . " chunk)";
}
```

### Logging delle Query

```php
// Log delle query per monitoraggio
function logQuery($question, $response) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'question' => $question,
        'answer_length' => strlen($response['answer']),
        'tokens_used' => $response['tokens_used'],
        'rag_used' => $response['rag_used']
    ];
    
    file_put_contents('rag_queries.log', json_encode($log) . "\n", FILE_APPEND);
}

// Utilizzo
$response = $rag->query($question);
logQuery($question, $response);
```

## 🔒 Sicurezza

### Validazione Input

```php
// Sanitizza l'input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Utilizzo
$question = sanitizeInput($_POST['question']);
$response = $rag->query($question);
```

### Limiti di Rate

```php
// Implementa rate limiting
class RateLimiter {
    private $cache = [];
    private $maxRequests = 10;
    private $timeWindow = 60; // secondi
    
    public function checkLimit($userId) {
        $now = time();
        $key = $userId . '_' . floor($now / $this->timeWindow);
        
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = 0;
        }
        
        if ($this->cache[$key] >= $this->maxRequests) {
            throw new Exception('Limite di richieste superato');
        }
        
        $this->cache[$key]++;
        return true;
    }
}

// Utilizzo
$rateLimiter = new RateLimiter();
$rateLimiter->checkLimit($userId);
$response = $rag->query($question);
```

## 📝 Esempi Completi

### Esempio: Chatbot per E-commerce

```php
<?php
require_once 'core/autoload.php';

class EcommerceRAG {
    private $rag;
    
    public function __construct() {
        $config = [
            'openai_api_key' => 'your-key',
            'base_prompt' => 'Sei un assistente di vendita esperto. Aiuta i clienti a trovare i prodotti giusti.',
            'max_chunks' => 3
        ];
        
        $this->rag = new RAGEngine($config);
    }
    
    public function handleCustomerQuery($question, $customerId) {
        try {
            // Log della query
            $this->logQuery($customerId, $question);
            
            // Esegui query RAG
            $response = $this->rag->query($question, [
                'use_rag' => true,
                'debug' => false
            ]);
            
            // Log della risposta
            $this->logResponse($customerId, $response);
            
            return $response['answer'];
            
        } catch (Exception $e) {
            return "Mi dispiace, non riesco a rispondere al momento. Riprova più tardi.";
        }
    }
    
    private function logQuery($customerId, $question) {
        // Implementa logging
    }
    
    private function logResponse($customerId, $response) {
        // Implementa logging
    }
}

// Utilizzo
$ecommerceRAG = new EcommerceRAG();
$answer = $ecommerceRAG->handleCustomerQuery("Avete prodotti in sconto?", "user123");
echo $answer;
?>
```

### Esempio: API REST Completa

```php
<?php
// api/customer_support.php
require_once '../core/autoload.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['question'])) {
        throw new Exception('Parametro question mancante');
    }
    
    $rag = new RAGEngine([
        'openai_api_key' => 'your-key',
        'base_prompt' => 'Sei un assistente di supporto tecnico.'
    ]);
    
    $response = $rag->query($input['question']);
    
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
```

## 🆘 Supporto

Per problemi di integrazione:

1. Controlla i log di errore
2. Verifica la configurazione
3. Testa con un file di test personalizzato
   ```php
   <?php
   require_once 'core/autoload.php';
   $rag = new RAGEngine();
   $response = $rag->query("Test");
   echo "Risposta: " . $response['answer'];
   ?>
   ```
4. Controlla la documentazione API in `/api/help`

---

**Nota**: Assicurati di gestire correttamente la chiave API OpenAI e di rispettare i limiti di utilizzo. 