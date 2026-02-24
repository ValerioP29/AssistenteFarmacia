<?php
/**
 * Script per aggiornare il database con le componenti necessarie per i cron
 * - Crea tabella jta_cron
 * - Aggiunge campo last_notification a jta_users
 * - Inserisce record iniziali
 */

require_once(__DIR__ . '/../vendor/autoload.php');

// Carica le variabili d'ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

try {
    // Connessione diretta al database usando le variabili d'ambiente
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8',
        $_ENV['DB_USER'],
        $_ENV['DB_PSW']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connessione al database stabilita\n\n";
    
    // 1. Crea tabella jta_cron
    echo "📋 Creazione tabella jta_cron...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `jta_cron` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Tabella jta_cron creata/aggiornata\n";
    
    // 2. Aggiungi campo last_notification alla tabella jta_users
    echo "\n📋 Aggiunta campo last_notification a jta_users...\n";
    
    // Verifica se il campo esiste già
    $check_sql = "SHOW COLUMNS FROM jta_users LIKE 'last_notification'";
    $stmt = $pdo->prepare($check_sql);
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        $sql = "ALTER TABLE jta_users ADD COLUMN `last_notification` datetime NULL DEFAULT NULL COMMENT 'Data/ora ultima notifica inviata'";
        $pdo->exec($sql);
        echo "✅ Campo last_notification aggiunto\n";
        
        // Aggiungi indice
        $sql = "ALTER TABLE jta_users ADD KEY `idx_last_notification` (`last_notification`)";
        $pdo->exec($sql);
        echo "✅ Indice idx_last_notification aggiunto\n";
    } else {
        echo "ℹ️ Campo last_notification già presente\n";
    }
    
    // 3. Inserisci record iniziali
    echo "\n📋 Inserimento record cron...\n";
    
    $crons = [
        [
            'file_name' => 'reminder_therapy.php',
            'description' => 'Cron per promemoria terapie attive',
            'is_active' => 1
        ],
        [
            'file_name' => 'reminder_expiry.php',
            'description' => 'Cron per promemoria scadenze prodotti',
            'is_active' => 1
        ]
    ];
    
    foreach ($crons as $cron) {
        $sql = "INSERT IGNORE INTO jta_cron (file_name, description, is_active) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cron['file_name'], $cron['description'], $cron['is_active']]);
        echo "✅ Record per {$cron['file_name']} inserito/verificato\n";
    }
    
    echo "\n🎉 Database aggiornato con successo!\n";
    
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage() . "\n";
    exit(1);
} 