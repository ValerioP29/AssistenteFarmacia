<?php
/**
 * Cron per l'invio di promemoria WhatsApp per terapie
 * Sistema semplice: controlla terapie attive e invia promemoria agli orari programmati
 */

require_once(__DIR__ . '/main_cron.php');

// Messaggi per le terapie
$MESSAGES = [
    'normal' => "💊 Promemoria: è ora di assumere il prodotto {medication}, Dosaggio: {dosage}. {notes}",
    'expired' => "⚠️ ATTENZIONE! La terapia per il prodotto {medication}, Dosaggio: {dosage} è scaduta il {end_date}. Contatta il medico per rinnovare la prescrizione. {notes}"
];

/**
 * Funzione principale del cron
 */
function runTherapyReminders() {
    global $MESSAGES;
    
    $pdo = getConnection();
    $current_date = date('Y-m-d');
    $current_time = date('H:i');
    
    echo "🔍 Controllo terapie - Data: {$current_date}, Ora: {$current_time}\n\n";
    
    $sent_count = 0;
    $error_count = 0;
    
    // 1. Ottieni tutte le terapie attive (non cancellate, data corrente tra start e end COMPRESE)
    $sql = "SELECT rt.*, u.phone_number, u.name, u.surname, u.last_notification
            FROM jta_reminder_therapy rt
            INNER JOIN jta_users u ON rt.user_id = u.id
            WHERE rt.deleted_at IS NULL 
            AND rt.start_date <= CURDATE() 
            AND rt.end_date >= CURDATE()
            AND u.phone_number IS NOT NULL 
            AND u.phone_number != ''";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $active_therapies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Trovate " . count($active_therapies) . " terapie attive\n\n";
    
    // 2. Processa terapie attive
    foreach ($active_therapies as $therapy) {
        try {
            // Gestione JSON con escape multipli
            $times_raw = $therapy['times'];
            $times = null;
            
            // Metodo ottimizzato: rimuovi virgolette esterne + sostituisci escape
            if (strlen($times_raw) > 2 && $times_raw[0] === '"' && $times_raw[-1] === '"') {
                $cleaned = str_replace('\"', '"', substr($times_raw, 1, -1));
                $times = json_decode($cleaned, true);
            } else {
                // Fallback: json_decode diretto
                $times = json_decode($times_raw, true);
            }
            
            if (!is_array($times)) {
                echo "❌ Orari non validi per terapia ID {$therapy['id']} (raw: {$times_raw})\n";
                continue;
            }
            
            // Controlla orari programmati in base alla frequenza
            $sent_today = false;
            $current_day = date('N'); // 1=Lunedì, 7=Domenica
            
            foreach ($times as $time) {
                $time_datetime = date('Y-m-d H:i:s', strtotime($current_date . ' ' . $time . ':00'));
                $should_send = false;
                
                // Logica diversa per ogni frequenza
                switch ($therapy['frequency']) {
                    case 'daily':
                    case 'twice_daily':
                    case 'three_times':
                    case 'custom':
                        // Per terapie quotidiane: controlla se l'orario è passato e non ancora notificato
                        if ($time <= $current_time) {
                            // Se last_notification è NULL o è precedente all'orario specifico di oggi
                            if ($therapy['last_notification'] === null || $therapy['last_notification'] < $time_datetime) {
                                $should_send = true;
                            }
                        }
                        break;
                        
                    case 'weekly':
                        // Invia solo il lunedì agli orari specificati
                        if ($current_day == 1 && $time <= $current_time && 
                            ($therapy['last_notification'] === null || $therapy['last_notification'] < $time_datetime)) {
                            $should_send = true;
                        }
                        break;
                }
                
                if ($should_send) {
                    // Debug per verificare la logica
                    $debug_msg = "🔔 Invia notifica: {$therapy['drug_name']} alle {$time} (frequenza: {$therapy['frequency']})";
                    $debug_msg .= "\n   - last_notification: " . ($therapy['last_notification'] ?: 'NULL');
                    $debug_msg .= "\n   - time_datetime: {$time_datetime}";
                    $debug_msg .= "\n   - Condizione: " . ($therapy['last_notification'] === null ? 'NULL' : ($therapy['last_notification'] < $time_datetime ? 'TRUE' : 'FALSE'));
                    echo $debug_msg . "\n";
                    
                    // Prepara e invia messaggio
                    $message = str_replace(
                        ['{medication}', '{dosage}', '{notes}'],
                        [
                            $therapy['drug_name'],
                            $therapy['dosage'],
                            $therapy['notes'] ? "Note: " . $therapy['notes'] : ""
                        ],
                        $MESSAGES['normal']
                    );
                    
                    // Aggiungi saluto
                    $greeting = "Ciao " . ($therapy['name'] ?: 'utente') . "! ";
                    $message = $greeting . $message;
                    
                    // Invia WhatsApp
                    if (sendWhatsAppMessage($therapy['phone_number'], $message)) {
                        // Aggiorna last_notification
                        $update_sql = "UPDATE jta_users SET last_notification = ? WHERE id = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([$time_datetime, $therapy['user_id']]);
                        
                        $sent_count++;
                        echo "✅ Inviato: {$therapy['drug_name']} alle {$time} a {$therapy['phone_number']}\n";
                        $sent_today = true;
                    } else {
                        $error_count++;
                        echo "❌ Errore invio: {$therapy['drug_name']} a {$therapy['phone_number']}\n";
                    }
                }
            }
            
            if (!$sent_today) {
                echo "ℹ️ Nessuna notifica: {$therapy['drug_name']} (frequenza: {$therapy['frequency']}, ora: {$current_time}, orari: " . implode(', ', $times) . ", last_notification: " . ($therapy['last_notification'] ?: 'NULL') . ")\n";
            }
            
        } catch (Exception $e) {
            $error_count++;
            echo "❌ Errore terapia ID {$therapy['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Controlla terapie scadute (end_date < oggi)
    echo "\n🔍 Controllo terapie scadute...\n";
    
    $sql_expired = "SELECT rt.*, u.phone_number, u.name, u.surname, u.last_notification
                    FROM jta_reminder_therapy rt
                    INNER JOIN jta_users u ON rt.user_id = u.id
                    WHERE rt.deleted_at IS NULL 
                    AND rt.end_date < CURDATE()
                    AND u.phone_number IS NOT NULL 
                    AND u.phone_number != ''";
    
    $stmt_expired = $pdo->prepare($sql_expired);
    $stmt_expired->execute();
    $expired_therapies = $stmt_expired->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Trovate " . count($expired_therapies) . " terapie scadute\n\n";
    
    foreach ($expired_therapies as $therapy) {
        try {
            $notification_datetime = date('Y-m-d H:i:s', strtotime($current_date . ' 08:00:00'));
            
            // Controlla se già notificato oggi
            if ($therapy['last_notification'] !== null && $therapy['last_notification'] >= $notification_datetime) {
                echo "✅ Scadenza già notificata: {$therapy['drug_name']}\n";
                continue;
            }
            
            // Prepara messaggio scadenza
            $message = str_replace(
                ['{medication}', '{dosage}', '{notes}', '{end_date}'],
                [
                    $therapy['drug_name'],
                    $therapy['dosage'],
                    $therapy['notes'] ? "Note: " . $therapy['notes'] : "",
                    date('d/m/Y', strtotime($therapy['end_date']))
                ],
                $MESSAGES['expired']
            );
            
            // Aggiungi saluto
            $greeting = "Ciao " . ($therapy['name'] ?: 'utente') . "! ";
            $message = $greeting . $message;
            
            // Invia WhatsApp
            if (sendWhatsAppMessage($therapy['phone_number'], $message)) {
                // Aggiorna last_notification
                $update_sql = "UPDATE jta_users SET last_notification = ? WHERE id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$notification_datetime, $therapy['user_id']]);
                
                $sent_count++;
                echo "✅ Scadenza inviata: {$therapy['drug_name']} a {$therapy['phone_number']}\n";
            } else {
                $error_count++;
                echo "❌ Errore invio scadenza: {$therapy['drug_name']} a {$therapy['phone_number']}\n";
            }
            
        } catch (Exception $e) {
            $error_count++;
            echo "❌ Errore terapia scaduta ID {$therapy['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📊 Riepilogo: {$sent_count} messaggi inviati, {$error_count} errori\n";
    
    return $error_count == 0;
}

// Esegui il cron
$result = executeCron('reminder_therapy.php', 'runTherapyReminders');

if ($result) {
    echo "✅ Cron terapie eseguito con successo\n";
    exit(0);
} else {
    echo "❌ Cron terapie completato con errori\n";
    exit(1);
} 