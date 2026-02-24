# Sistema di Gestione Cron - Assistente Farmacia

## Panoramica
Sistema completo per la gestione dei cron jobs con monitoraggio, controllo duplicati e notifiche automatiche in caso di errori.

## Struttura

### Cartella `cron/`
```
cron/
├── .htaccess                          # Protezione accessi web
├── main_cron.php                      # File principale con logica comune
├── reminder_therapy.php               # Cron per promemoria terapie
├── reminder_expiry.php                # Cron per promemoria scadenze
├── update_database.php                # Script PHP per aggiornare il database
├── update_database.sql                # Script SQL per aggiornare il database
└── README_CRON.md                     # Documentazione completa del sistema cron
```

### Tabella `jta_cron`
```sql
CREATE TABLE `jta_cron` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL COMMENT 'Nome del file cron',
  `description` text COMMENT 'Descrizione del cron',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=attivo, 0=disattivo',
  `last_start` datetime NULL DEFAULT NULL COMMENT 'Data/ora ultimo avvio',
  `last_end` datetime NULL DEFAULT NULL COMMENT 'Data/ora ultima fine',
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '-1=errore, 0=terminato, 1=in esecuzione',
  `last_error` text COMMENT 'Ultimo errore generato',
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_name` (`file_name`),
  KEY `is_active` (`is_active`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Sistema Universale di Tracking Notifiche
```sql
-- Campo aggiunto alla tabella jta_users per tracking universale
ALTER TABLE `jta_users` 
ADD COLUMN `last_notification` datetime NULL DEFAULT NULL COMMENT 'Data/ora ultima notifica inviata';

-- Indice per ottimizzare le query
ALTER TABLE `jta_users` 
ADD KEY `idx_last_notification` (`last_notification`);
```

## Setup

### 1. Aggiornamento Database
Esegui uno dei seguenti script per aggiornare il database:

#### Opzione A: Script PHP (Raccomandato)
```bash
php cron/update_database.php
```

#### Opzione B: Script SQL
```sql
-- Esegui il contenuto del file cron/update_database.sql
```

### 2. Configurazione Plesk
Configura i cron in Plesk con i seguenti comandi:

#### Cron Terapie (ogni ora)
```bash
php /path/to/assistente_farmacia_api/cron/reminder_therapy.php
```

#### Cron Scadenze (ogni 5 minuti)
```bash
php /path/to/assistente_farmacia_api/cron/reminder_expiry.php
```

## Funzionalità

### Controllo Duplicati
- Verifica se esiste già un'istanza attiva (status = 1)
- Impedisce l'esecuzione simultanea dello stesso cron
- Gestione automatica dello stato

### Monitoraggio
- Tracciamento data/ora inizio e fine
- Status: -1 (errore), 0 (terminato), 1 (in esecuzione)
- Log degli ultimi errori

### Notifiche
- Email automatica a `sviluppo@jungleteam.it` in caso di errore
- Include dettagli dell'errore, data/ora e server

### Protezione
- `.htaccess` blocca accessi web alla cartella cron
- Verifica che i script siano eseguiti solo da CLI

## Sistema Universale di Tracking Notifiche

### Vantaggi
- **Flessibilità:** Un solo campo per tutti i tipi di notifica
- **Semplicità:** Nessuna modifica database per nuovi tipi di notifica
- **Performance:** Meno campi da gestire e indicizzare
- **Manutenibilità:** Codice più pulito e universale

### Logica Corretta
1. **Calcolo:** Per ogni notifica, calcola la data/ora specifica della notifica
2. **Controllo:** Confronta `last_notification` dell'utente con la data/ora della notifica specifica
3. **Decisione:** Se `last_notification < data_ora_notifica_specifica`, invia la notifica
4. **Aggiornamento:** Dopo l'invio riuscito, aggiorna `last_notification` con la data/ora della notifica specifica
5. **Prevenzione:** Evita notifiche duplicate basandosi sul timestamp specifico della notifica

### Esempi Pratici

#### **Esempio 1: Scadenza Prodotto**
```
📅 Data: 15 Gennaio 2024
📦 Prodotto: Paracetamolo scade il 16 Gennaio 2024 (tra 1 giorno)

🕐 Data/ora notifica specifica: 2024-01-16 08:00:00 (giorno prima alle 8:00)
🕐 Ultimo aggiornamento utente: 2024-01-15 17:00:00
 Cron gira: 2024-01-15 17:02:00
📱 Risultato: INVIA (17:00:00 < 2024-01-16 08:00:00)
🕐 Nuovo aggiornamento: 2024-01-16 08:00:00

 Cron gira: 2024-01-15 17:07:00
📱 Risultato: NON INVIARE (2024-01-16 08:00:00 >= 2024-01-16 08:00:00)
```

#### **Esempio 2: Terapia Quotidiana**
```
📅 Data: 15 Gennaio 2024
💊 Terapia: Antibiotico 3 volte al giorno (08:00, 14:00, 20:00)

🕐 Data/ora notifica specifica: 2024-01-15 08:00:00
🕐 Ultimo aggiornamento utente: 2024-01-15 07:55:00
 Cron gira: 2024-01-15 08:00:00
📱 Risultato: INVIA (07:55:00 < 08:00:00)
🕐 Nuovo aggiornamento: 2024-01-15 08:00:00

🕐 Data/ora notifica specifica: 2024-01-15 14:00:00
🕐 Ultimo aggiornamento utente: 2024-01-15 08:00:00
 Cron gira: 2024-01-15 14:00:00
📱 Risultato: INVIA (08:00:00 < 14:00:00)
🕐 Nuovo aggiornamento: 2024-01-15 14:00:00
```

#### **Esempio 3: Cron Lento**
```
📅 Data: 15 Gennaio 2024
💊 Terapia: Vitamina D alle 08:00

🕐 Data/ora notifica specifica: 2024-01-15 08:00:00
🕐 Ultimo aggiornamento utente: 2024-01-15 07:30:00
 Cron gira: 2024-01-15 08:15:00 (15 minuti in ritardo)
📱 Risultato: INVIA (07:30:00 < 08:00:00)
🕐 Nuovo aggiornamento: 2024-01-15 08:00:00 (non 08:15:00!)
```

## Cron Disponibili

### 1. reminder_therapy.php
**Scopo:** Invio promemoria WhatsApp per terapie attive e scadute

**Logica aggiornata:**
1. **Terapie attive:** Controlla terapie con data corrente tra start_date e end_date (COMPRESE)
2. **Frequenze supportate:**
   - `daily`: Invia ogni giorno agli orari specificati
   - `twice_daily`: Invia due volte al giorno agli orari specificati
   - `three_times`: Invia tre volte al giorno agli orari specificati
   - `weekly`: Invia solo il lunedì agli orari specificati
   - `custom`: Invia agli orari personalizzati
3. **Orari programmati:** Invia promemoria per orari passati di oggi non ancora notificati
4. **Terapie scadute:** Controlla terapie con end_date < oggi e invia notifica di scadenza
5. **Sistema tracking:** Usa last_notification per evitare duplicati

**Esempi:**
- **daily:** Antibiotico alle 15:00 → Invia ogni giorno alle 15:00
- **weekly:** Vitamina D alle 08:00 → Invia solo il lunedì alle 08:00
- **three_times:** OKI alle 16:00, 16:10, 16:20 → Invia tutti e tre gli orari ogni giorno

**Esempio di Calcolo:**
```php
// Per terapia quotidiana alle 08:00
$notification_datetime = date('Y-m-d H:i:s', strtotime($current_date . ' 08:00:00'));
// Risultato: 2024-01-15 08:00:00

// Controllo
if ($therapy['last_notification'] < $notification_datetime) {
    // Invia notifica
    // Aggiorna last_notification con 2024-01-15 08:00:00
}
```

**Esempi di Messaggi:**
- **Orario normale:** "Buongiorno! 💊 Promemoria: è ora di assumere Paracetamolo - 1 compressa."
- **Orario ritardato:** "⏰ PROMEMORIA! Non dimenticare di assumere Paracetamolo - 1 compressa (programmato per le 15:00)."
- **Terapia scaduta:** "⚠️ ATTENZIONE! La terapia Paracetamolo - 1 compressa è scaduta il 05/08/2024. Contatta il medico per rinnovare la prescrizione."

### 2. reminder_expiry.php
**Scopo:** Invio promemoria WhatsApp per scadenze prodotti

**Logica:**
- Controlla prodotti che scadono tra 30, 15, 7, 1, 0 e -1 giorni
- Verifica che l'utente abbia abilitato l'avviso specifico
- **Sistema universale:** Calcola la data/ora specifica della notifica (giorno scadenza alle 08:00) e confronta con `last_notification`
- Invia messaggi con urgenza crescente

**Esempio di Calcolo:**
```php
// Per scadenza tra 1 giorno
$target_date = date('Y-m-d', strtotime("+1 days")); // 2024-01-16
$notification_datetime = date('Y-m-d H:i:s', strtotime($target_date . ' 08:00:00'));
// Risultato: 2024-01-16 08:00:00

// Controllo
if ($user['last_notification'] < $notification_datetime) {
    // Invia notifica
    // Aggiorna last_notification con 2024-01-16 08:00:00
}
```

**Gestione Cron Ogni 5 Minuti:**
- Se una scadenza è alle 17:00 e il cron gira alle 17:02, il sistema:
  1. Calcola la data/ora della notifica specifica (es: 2024-01-15 08:00:00)
  2. Controlla `last_notification` dell'utente
  3. Se `last_notification < data_ora_notifica_specifica`, invia il messaggio
  4. Aggiorna `last_notification` con la data/ora della notifica specifica

## Personalizzazione

### Modifica Messaggi
I messaggi sono facilmente modificabili nei file dedicati:

1. **Terapie:** Modifica l'array `$MESSAGES` in `reminder_therapy.php`
2. **Scadenze:** Modifica l'array `$MESSAGES` in `reminder_expiry.php`

### Aggiunta Nuovi Cron
1. Crea il file nella cartella `cron/`
2. Includi `main_cron.php`
3. Definisci la funzione principale
4. Usa `executeCron()` per l'esecuzione
5. Inserisci il record nella tabella `jta_cron`
6. **Usa il sistema universale:** Calcola la data/ora specifica della notifica e confronta con `last_notification`

**Esempio:**
```php
<?php
require_once(__DIR__ . '/main_cron.php');

function runMyCron() {
    $pdo = getConnection();
    $current_date = date('Y-m-d');
    
    // Calcola la data/ora specifica della notifica
    $notification_datetime = date('Y-m-d H:i:s', strtotime($current_date . ' 10:00:00'));
    
    // Query con controllo last_notification
    $sql = "SELECT u.* FROM jta_users u 
            WHERE u.phone_number IS NOT NULL 
            AND (u.last_notification IS NULL OR u.last_notification < ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$notification_datetime]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        // Invia notifica
        if (sendWhatsAppMessage($user['phone_number'], $message)) {
            // Aggiorna last_notification con la data/ora specifica della notifica
            $update_sql = "UPDATE jta_users SET last_notification = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$notification_datetime, $user['id']]);
        }
    }
    
    return true;
}

$result = executeCron('my_cron.php', 'runMyCron');
```

## Gestione Errori

### Email di Errore
In caso di errore, viene inviata automaticamente un'email a `sviluppo@jungleteam.it` con:
- Nome del cron
- Messaggio di errore
- Data/ora dell'errore
- Nome del server

### Log
Gli errori vengono anche loggati nel log di sistema PHP.

## Controllo Stato

### Query Utili
```sql
-- Verifica stato cron
SELECT * FROM jta_cron WHERE file_name = 'reminder_therapy.php';

-- Cron con errori
SELECT * FROM jta_cron WHERE status = -1;

-- Cron attivi
SELECT * FROM jta_cron WHERE is_active = 1;

-- Cron in esecuzione
SELECT * FROM jta_cron WHERE status = 1;

-- Verifica ultime notifiche utenti
SELECT id, first_name, last_name, phone_number, last_notification 
FROM jta_users 
WHERE last_notification IS NOT NULL 
ORDER BY last_notification DESC;

-- Utenti che non hanno ricevuto notifiche per una data specifica
SELECT id, first_name, last_name, phone_number, last_notification 
FROM jta_users 
WHERE last_notification IS NULL 
   OR last_notification < '2024-01-15 08:00:00';
```

### Gestione Manuale
```sql
-- Disattiva cron
UPDATE jta_cron SET is_active = 0 WHERE file_name = 'reminder_therapy.php';

-- Reset stato
UPDATE jta_cron SET status = 0, last_error = NULL WHERE file_name = 'reminder_therapy.php';

-- Reset notifiche utente (per test)
UPDATE jta_users SET last_notification = NULL WHERE id = 1;

-- Forza invio notifica (imposta last_notification a ieri)
UPDATE jta_users SET last_notification = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = 1;

-- Simula notifica specifica già inviata
UPDATE jta_users SET last_notification = '2024-01-15 08:00:00' WHERE id = 1;
```

## Sicurezza

### Protezione Accessi
- `.htaccess` blocca tutti gli accessi web
- Verifica che i script siano eseguiti solo da CLI
- Controllo duplicati previene sovraccarico

### Autenticazione
- Nessuna autenticazione JWT per i cron (processi server-side)
- Utilizzo diretto delle funzioni di database
- Protezione tramite file system

## Troubleshooting

### Problemi Comuni

1. **Cron non parte:**
   - Verifica che `is_active = 1`
   - Controlla che non ci sia già un'istanza attiva (status = 1)

2. **Messaggi WhatsApp non inviati:**
   - Verifica connessione WhatsApp (`pharma_is_wa_connected($pharma_id)`)
   - Controlla numeri di telefono degli utenti

3. **Errori di database:**
   - Verifica connessione database
   - Controlla permessi utente database

4. **Notifiche duplicate:**
   - Verifica che il campo `last_notification` sia stato aggiunto
   - Controlla che il tracking funzioni correttamente
   - Verifica che la data/ora della notifica specifica sia calcolata correttamente

5. **Notifiche non inviate:**
   - Controlla `last_notification` dell'utente
   - Verifica che la data/ora della notifica specifica sia corretta
   - Controlla che il confronto temporale sia esatto

### Debug
Per debug, esegui manualmente:
```bash
php /path/to/cron/reminder_therapy.php
php /path/to/cron/reminder_expiry.php
```

I messaggi di output mostrano:
- Messaggi inviati con successo
- Errori di invio
- Conteggio finale
- Data/ora specifica della notifica
- Tracking delle notifiche universale 