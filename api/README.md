# API Assistente Farmacia

Codice per la risoluzione delle richieste di "_API Assistente Farmacia_".\
Online all'indirizzo [https://api.assistentefarmacia.it](https://api.assistentefarmacia.it).

## Per cominciare

1. **Clona o scarica** il repository nella tua macchina locale:

   ```bash
   git clone https://gitlab.com/jungle-team-srl/assistente_farmacia_api.git
   cd assistente_farmacia_api
   ```

2. **Installa le dipendenze** con Composer:

   ```bash
   composer install
   ```

3. **Crea un file `.env`** nella root del progetto con il seguente contenuto (usa i tuoi valori reali):

   ```
   JTA_APP_JWT_SECRET=<JWT_SECRET>
   JTA_APP_OPENAI_API_KEY=<OPENAI_API_KEY>
   DB_HOST=<DB_HOST>
   DB_NAME=<DB_NAME>
   DB_USER=<DB_USER>
   DB_PSW=<DB_PSW>
   ```

4. **Configura il database** eseguendo gli script SQL per le tabelle dei promemoria:

   ```bash
   php create_reminders_tables.php
   ```

## Struttura del progetto

```
assistente_farmacia_api/
├── helpers/              # Grande pool con model, helper e altro.
├── uploads/              # File caricati dagli utenti
│   ├── {user_id}/        # Organizzati per utente
│   │   ├── terapies/     # File terapie
│   │   └── expiry/       # File scadenze
├── .env                  # Variabili di ambiente
├── composer.json         # Configurazione Composer
├── README.md             # Questo file
└── ...                   # File per vari per gli endpoint
```

## Autenticazione

Tutti gli endpoint (eccetto login) **richiedono un token JWT** nel header `Authorization`:

```
Authorization: Bearer <JWT token>
```

Per ottenere un token, effettua una richiesta all'endpoint di accesso (vedi sotto).\
\
NB: I token scaduti restituiscono `401 Unauthorized`. In App assicurati di gestire il refresh lato client.

## Endpoint disponibili

### Autenticazione
1. https://api.assistentefarmacia.it/auth-check.php
1. https://api.assistentefarmacia.it/auth-login.php
1. https://api.assistentefarmacia.it/auth-refresh.php

### Chatbot
1. https://api.assistentefarmacia.it/chatbot-init.php
1. https://api.assistentefarmacia.it/chatbot-send.php

### Promemoria Terapia
1. https://api.assistentefarmacia.it/reminder-therapy-post.php
1. https://api.assistentefarmacia.it/reminders-therapy-list.php
1. https://api.assistentefarmacia.it/reminder-therapy-get.php
1. https://api.assistentefarmacia.it/reminder-therapy-put.php
1. https://api.assistentefarmacia.it/reminder-therapy-delete.php
1. https://api.assistentefarmacia.it/reminder-therapy-complete.php

### Promemoria Scadenza
1. https://api.assistentefarmacia.it/reminder-expiry-post.php
1. https://api.assistentefarmacia.it/reminders-expiry-list.php
1. https://api.assistentefarmacia.it/reminder-expiry-get.php
1. https://api.assistentefarmacia.it/reminder-expiry-put.php
1. https://api.assistentefarmacia.it/reminder-expiry-delete.php

### Gestione File
1. https://api.assistentefarmacia.it/download-file.php
1. https://api.assistentefarmacia.it/view-file.php

### Altri Endpoint
1. https://api.assistentefarmacia.it/pharma-fav-get.php
1. https://api.assistentefarmacia.it/placeholder-drug-get.php
1. https://api.assistentefarmacia.it/placeholder-events.php
1. https://api.assistentefarmacia.it/placeholder-event-get.php
1. https://api.assistentefarmacia.it/placeholder-event-post.php
1. https://api.assistentefarmacia.it/placeholder-promos.php
1. https://api.assistentefarmacia.it/placeholder-promo-get.php
1. https://api.assistentefarmacia.it/placeholder-services.php
1. https://api.assistentefarmacia.it/placeholder-service-get.php
1. https://api.assistentefarmacia.it/placeholder-service-post.php

---

## Sistema Promemoria - Scadenze e Terapie

### Struttura Database

#### Tabella `jta_reminder_therapy`
```sql
CREATE TABLE `jta_reminder_therapy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `drug_name` varchar(255) NOT NULL,
  `dosage` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `frequency` enum('daily','twice_daily','three_times','weekly','custom') NOT NULL,
  `times` json NOT NULL,
  `notes` text,
  `file` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabella `jta_reminders_expiry`
```sql
CREATE TABLE `jta_reminders_expiry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `expiry_date` date NOT NULL,
  `alerts` json NOT NULL,
  `notes` text,
  `file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Endpoint Promemoria Terapia

#### 1. Creazione Promemoria Terapia
**POST** `/reminder-therapy-post.php`

**Content-Type:** `multipart/form-data`

**Parametri:**
- `medicationName` (string, obbligatorio) - Nome del farmaco
- `dosage` (string, obbligatorio) - Dosaggio
- `startDate` (date, obbligatorio) - Data di inizio terapia
- `endDate` (date, obbligatorio) - Data di fine terapia
- `frequency` (enum, obbligatorio) - Frequenza: `daily`, `twice_daily`, `three_times`, `weekly`, `custom`
- `times` (string, obbligatorio) - Orari in formato JSON
- `notes` (string, opzionale) - Note aggiuntive
- `file` (file, opzionale) - File allegato (PDF, immagini, documenti)

**Esempio di richiesta:**
```bash
curl -X POST "http://localhost:8000/reminder-therapy-post.php" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "medicationName=Paracetamolo" \
  -F "dosage=500mg" \
  -F "startDate=2024-01-01" \
  -F "endDate=2024-01-07" \
  -F "frequency=daily" \
  -F "times=[\"08:00\",\"20:00\"]" \
  -F "notes=Assumere dopo i pasti" \
  -F "file=@/path/to/prescription.pdf"
```

**Risposta di successo:**
```json
{
  "code": 200,
  "status": true,
  "message": "Promemoria terapia aggiunto con successo",
  "data": {
    "id": 123,
    "file": "1/terapies/1704067200_a1b2c3d4.pdf"
  }
}
```

#### 2. Lista Promemoria Terapia
**GET** `/reminders-therapy-list.php`

**Risposta:**
```json
{
  "code": 200,
  "status": true,
  "data": [
    {
      "id": 123,
      "medicationName": "Paracetamolo",
      "dosage": "500mg",
      "startDate": "2024-01-01",
      "endDate": "2024-01-07",
      "frequency": "daily",
      "times": ["08:00", "20:00"],
      "notes": "Assumere dopo i pasti",
      "file": "1/terapies/1704067200_a1b2c3d4.pdf",
      "fileUrl": "http://localhost:8000/download-file.php?file=1%2Fterapies%2F1704067200_a1b2c3d4.pdf&token=...",
      "viewUrl": "http://localhost:8000/view-file.php?file=1%2Fterapies%2F1704067200_a1b2c3d4.pdf&token=...",
      "createdAt": "2024-01-01 10:00:00",
      "deletedAt": null
    }
  ]
}
```

#### 3. Dettaglio Promemoria Terapia
**GET** `/reminder-therapy-get.php?id=123`

#### 4. Aggiornamento Promemoria Terapia
**PUT** `/reminder-therapy-put.php`

#### 5. Eliminazione Promemoria Terapia
**DELETE** `/reminder-therapy-delete.php`

**Nota:** Implementa soft delete (imposta `deleted_at`)

### Endpoint Promemoria Scadenza

#### 1. Creazione Promemoria Scadenza
**POST** `/reminder-expiry-post.php`

**Content-Type:** `multipart/form-data` (per file) o `application/json`

**Parametri:**
- `productName` (string, obbligatorio) - Nome del prodotto
- `expiryDate` (date, obbligatorio) - Data di scadenza (formato YYYY-MM-DD)
- `alerts` (object/string, obbligatorio) - Configurazione avvisi
  - `alert30` (boolean) - Avviso 30 giorni prima
  - `alert15` (boolean) - Avviso 15 giorni prima
  - `alert7` (boolean) - Avviso 7 giorni prima
  - `alert1` (boolean) - Avviso 1 giorno prima
- `notes` (string, opzionale) - Note aggiuntive
- `file` (file, opzionale) - Immagine del prodotto (JPG, PNG, GIF, max 5MB)

**Esempio con curl:**
```bash
curl -X POST "http://localhost:8000/reminder-expiry-post.php" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "productName=Tachipirina 500mg" \
  -F "expiryDate=2024-12-31" \
  -F "alerts={\"alert30\":true,\"alert15\":false,\"alert7\":true,\"alert1\":false}" \
  -F "notes=Dopo i pasti" \
  -F "file=@/path/to/product-photo.jpg"
```

**Risposta di successo:**
```json
{
  "code": 200,
  "status": true,
  "message": "Promemoria scadenza aggiunto con successo",
  "data": {
    "id": 123
  }
}
```

#### 2. Lista Promemoria Scadenza
**GET** `/reminders-expiry-list.php`

**Risposta:**
```json
{
  "code": 200,
  "status": true,
  "data": [
    {
      "id": 123,
      "productName": "Tachipirina 500mg",
      "expiryDate": "2024-12-31",
      "alerts": {
        "alert30": true,
        "alert15": false,
        "alert7": true,
        "alert1": false
      },
      "notes": "Dopo i pasti",
      "file": "uploads/expiry/expiry_1_1703123456_abc123.jpg",
      "fileUrl": "http://localhost:8000/download-file.php?file=uploads/expiry/expiry_1_1703123456_abc123.jpg&token=...",
      "viewUrl": "http://localhost:8000/view-file.php?file=uploads/expiry/expiry_1_1703123456_abc123.jpg&token=...",
      "completed": false,
      "createdAt": "2024-01-15 10:30:00"
    }
  ]
}
```

#### 3. Dettaglio Promemoria Scadenza
**GET** `/reminder-expiry-get.php?id=123`

#### 4. Aggiornamento Promemoria Scadenza
**PUT** `/reminder-expiry-put.php`

#### 5. Eliminazione Promemoria Scadenza
**DELETE** `/reminder-expiry-delete.php`

### Gestione File

#### Endpoint File
- **Download:** `GET /download-file.php?file={path}&token={jwt_token}`
- **Visualizzazione:** `GET /view-file.php?file={path}&token={jwt_token}`

#### Struttura Cartelle
```
uploads/
├── 1/                    # ID utente
│   ├── terapies/         # Cartella terapie
│   │   ├── 1704067200_a1b2c3d4.pdf
│   │   └── 1704067200_e5f6g7h8.jpg
│   └── expiry/           # Cartella scadenze
│       └── expiry_1_1703123456_abc123.jpg
└── 2/
    ├── terapies/
    └── expiry/
```

#### Tipi File Supportati
- **Terapie:** PDF, immagini (JPEG, PNG, GIF, WebP), documenti (DOC, DOCX), testo
- **Scadenze:** Immagini (JPG, JPEG, PNG, GIF)

#### Limiti
- **Terapie:** Dimensione massima 10MB
- **Scadenze:** Dimensione massima 5MB
- **Estensioni permesse:** pdf, jpg, jpeg, png, gif, doc, docx, txt

#### Sicurezza
- I file sono salvati con nomi unici (timestamp + stringa casuale)
- Accesso controllato tramite JWT
- Verifica che il file appartenga all'utente autenticato
- Protezione cartella uploads tramite `.htaccess`

### Esempi di Utilizzo Frontend

#### Upload File con FormData (Terapia)
```javascript
const formData = new FormData();
formData.append('medicationName', 'Paracetamolo');
formData.append('dosage', '500mg');
formData.append('startDate', '2024-01-01');
formData.append('endDate', '2024-01-07');
formData.append('frequency', 'daily');
formData.append('times', JSON.stringify(['08:00', '20:00']));
formData.append('notes', 'Assumere dopo i pasti');

// Aggiungi file se presente
if (fileInput.files[0]) {
    formData.append('file', fileInput.files[0]);
}

fetch('/reminder-therapy-post.php', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: formData
});
```

#### Upload File con FormData (Scadenza)
```javascript
const formData = new FormData();
formData.append('productName', 'Tachipirina 500mg');
formData.append('expiryDate', '2024-12-31');
formData.append('alerts', JSON.stringify({
    alert30: true,
    alert15: false,
    alert7: true,
    alert1: false
}));
formData.append('notes', 'Dopo i pasti');

// Aggiungi file se presente
if (fileInput.files[0]) {
    formData.append('file', fileInput.files[0]);
}

fetch('/reminder-expiry-post.php', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: formData
});
```

#### Download e Visualizzazione File
```javascript
// Metodo 1: Usa gli URL completi forniti dall'API (RACCOMANDATO)
const fileUrl = response.data.fileUrl;    // Per download
const viewUrl = response.data.viewUrl;    // Per visualizzazione

if (fileUrl) {
    // Per download
    window.open(fileUrl);
    
    // Per visualizzazione (PDF, immagini, testo)
    window.open(viewUrl, '_blank');
}

// Metodo 2: Costruisci gli URL manualmente
const filePath = response.data.file;
if (filePath) {
    const downloadUrl = `/download-file.php?file=${encodeURIComponent(filePath)}&token=${token}`;
    const viewUrl = `/view-file.php?file=${encodeURIComponent(filePath)}&token=${token}`;
    
    window.open(downloadUrl);  // Download
    window.open(viewUrl);      // Visualizzazione
}
```

### Gestione Errori
Tutti gli endpoint restituiscono risposte JSON con:
- `code`: Codice HTTP
- `status`: true/false
- `message`: Messaggio descrittivo
- `error`: Tipo di errore (se applicabile)
- `data`: Dati della risposta (se applicabile)

### Errori Comuni

#### 400 Bad Request
- Campi obbligatori mancanti
- Data di scadenza non futura
- Nessun avviso selezionato
- Tipo di file non supportato
- File troppo grande

#### 401 Unauthorized
- Token JWT mancante o non valido
- Token scaduto

#### 404 Not Found
- Promemoria non trovato
- Promemoria non appartiene all'utente

#### 500 Internal Server Error
- Errore durante il salvataggio del file
- Errore del database

### Note Tecniche

#### Soft Delete
I promemoria terapia utilizzano soft delete (campo `deleted_at`) invece dell'eliminazione fisica.

#### Validazione File
- Controllo dimensione massima
- Validazione tipo MIME
- Controllo estensione file
- Generazione nomi file unici

#### Sicurezza
- Verifica proprietà file per utente
- Protezione cartella uploads
- Validazione JWT per accesso file
- Sanitizzazione input

#### Performance
- Indici database su `user_id` e `deleted_at`
- Organizzazione file per utente
- Nomi file ottimizzati per filesystem

---

## Altri Endpoint

### Login

#### `POST /auth-login.php`

**Corpo richiesta:**
```json
{
  "username": "jungleteam",
  "password": "password123"
}
```

### Promozioni

#### `GET /promos.php`
Restituisce un elenco delle promozioni disponibili.

**Risposta esempio:**
```json
{
  "code": 200,
  "status": true,
  "data": [
    <Drug object>,
    <Drug object>
  ]
}
```

#### `GET /promo-get.php`

Restituisce i dettagli di una singola promozione.

**Query parametri:**
- `id`: ID della promo

**Risposta esempio:**
```json
{
  "code": 200,
  "status": true,
  "data": <Drug object>
}
```

---

## Note

Assicurati che l'ambiente PHP abbia attive le estensioni necessarie (es. `pdo`, `curl`, ecc).
