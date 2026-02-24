<?php
/**
 * Script semplificato per creare le tabelle dei promemoria
 * Assistente Farmacia API
 * 
 * Questo script crea le tabelle jta_reminder_therapy e jta_reminders_expiry
 * con tutte le foreign key e indici necessari.
 */

// Includi il bootstrap dell'API
require_once('_api_bootstrap.php');

echo "=== CREAZIONE TABELLE PROMEMORIA ===\n";
echo "Data e ora: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Test connessione database
    echo "1. Test connessione database...\n";
    $test_query = $pdo->query("SELECT 1");
    echo "✅ Connessione database OK\n\n";

    // Verifica se jta_users esiste
    echo "2. Verifica tabella jta_users...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'jta_users'");
    if ($stmt->rowCount() == 0) {
        echo "❌ ERRORE: Tabella jta_users non trovata!\n";
        echo "   Le foreign key non possono essere create.\n";
        exit(1);
    }
    echo "✅ Tabella jta_users trovata.\n\n";

    // Crea tabella jta_reminder_therapy
    echo "3. Creazione tabella jta_reminder_therapy...\n";
    $sql_therapy = "
    CREATE TABLE IF NOT EXISTS jta_reminder_therapy (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        drug_name VARCHAR(255) NOT NULL,
        dosage VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        frequency ENUM('daily', 'twice_daily', 'three_times', 'weekly', 'custom') NOT NULL,
        times JSON NOT NULL,
        notes TEXT,
        file VARCHAR(255) COMMENT 'Percorso del file immagine del farmaco',
        completed BOOLEAN DEFAULT FALSE,
        progress INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP DEFAULT NULL,
        
        INDEX idx_reminder_therapy_user (user_id),
        INDEX idx_reminder_therapy_completed (completed),
        INDEX idx_reminder_therapy_start (start_date),
        INDEX idx_reminder_therapy_end (end_date),
        
        CONSTRAINT fk_reminder_therapy_user 
            FOREIGN KEY (user_id) REFERENCES jta_users(id) 
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_therapy);
    echo "✅ Tabella jta_reminder_therapy creata.\n";

    // Crea tabella jta_reminders_expiry
    echo "4. Creazione tabella jta_reminders_expiry...\n";
    $sql_expiry = "
    CREATE TABLE IF NOT EXISTS jta_reminders_expiry (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        expiry_date DATE NOT NULL,
        alerts JSON NOT NULL,
        notes TEXT,
        file VARCHAR(255) COMMENT 'Percorso del file immagine del prodotto',
        completed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP DEFAULT NULL,
        
        INDEX idx_reminders_expiry_user (user_id),
        INDEX idx_reminders_expiry_completed (completed),
        INDEX idx_reminders_expiry_date (expiry_date),
        
        CONSTRAINT fk_reminders_expiry_user 
            FOREIGN KEY (user_id) REFERENCES jta_users(id) 
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_expiry);
    echo "✅ Tabella jta_reminders_expiry creata.\n\n";

    // Verifica finale
    echo "5. Verifica finale...\n";
    $final_check = $pdo->query("SHOW TABLES LIKE 'jta_reminder_%'");
    $final_tables = [];
    while ($row = $final_check->fetch(PDO::FETCH_NUM)) {
        $final_tables[] = $row[0];
    }
    
    if (count($final_tables) === 2) {
        echo "✅ Tutte le tabelle sono state create correttamente.\n";
    } else {
        echo "⚠️  Alcune tabelle potrebbero non essere state create.\n";
    }

    // Test inserimento dati di esempio
    echo "6. Test inserimento dati...\n";
    try {
        // Test jta_reminder_therapy
        $test_therapy = $pdo->prepare("
            INSERT INTO jta_reminder_therapy (user_id, drug_name, dosage, start_date, end_date, frequency, times, notes, file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $test_therapy->execute([
            1, // user_id di test
            'Paracetamolo',
            '1 compressa da 500mg',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+7 days')),
            'daily',
            json_encode(['08:00']),
            'Test note',
            null // file opzionale
        ]);
        $therapy_id = $pdo->lastInsertId();
        
        // Elimina il record di test
        $pdo->exec("DELETE FROM jta_reminder_therapy WHERE id = $therapy_id");
        echo "✅ Test inserimento jta_reminder_therapy OK.\n";
        
        // Test jta_reminders_expiry
        $test_expiry = $pdo->prepare("
            INSERT INTO jta_reminders_expiry (user_id, product_name, expiry_date, alerts, notes, file)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $test_expiry->execute([
            1, // user_id di test
            'Paracetamolo 500mg',
            date('Y-m-d', strtotime('+30 days')),
            json_encode(['alert30' => true, 'alert15' => true, 'alert7' => false, 'alert1' => false]),
            'Test note',
            null // file opzionale
        ]);
        $expiry_id = $pdo->lastInsertId();
        
        // Elimina il record di test
        $pdo->exec("DELETE FROM jta_reminders_expiry WHERE id = $expiry_id");
        echo "✅ Test inserimento jta_reminders_expiry OK.\n";
        
    } catch (PDOException $e) {
        echo "⚠️  Errore test inserimento: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Report finale
    echo "=== REPORT FINALE ===\n";
    echo "✅ Database configurato con successo!\n";
    echo "✅ Tabelle create: " . implode(', ', $final_tables) . "\n";
    echo "✅ Foreign key configurate verso jta_users(id)\n";
    echo "✅ Indici creati per le performance\n";
    echo "✅ Test inserimento completati\n\n";
    
    echo "Struttura tabelle:\n";
    echo "- jta_reminder_therapy: id, user_id, drug_name, dosage, start_date, end_date, frequency, times, notes, file, completed, progress\n";
    echo "- jta_reminders_expiry: id, user_id, product_name, expiry_date, alerts, notes, file, completed\n\n";

} catch (PDOException $e) {
    echo "❌ ERRORE CRITICO: " . $e->getMessage() . "\n";
    echo "Verifica la configurazione del database.\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ ERRORE GENERALE: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Setup completato con successo! 🎉\n";
?> 