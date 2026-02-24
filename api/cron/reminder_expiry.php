<?php
/**
 * Cron per l'invio di promemoria WhatsApp per scadenze prodotti
 * Controlla le scadenze prossime e invia messaggi agli utenti
 * Usa sistema di tracking generico con last_notification
 */

// Includi il file principale
require_once(__DIR__ . '/main_cron.php');

// Configurazione messaggi (facilmente modificabile)
$MESSAGES = [
    '30' => "⚠️ Attenzione! Il prodotto {product} scade tra 30 giorni ({expiry_date}). Ti consigliamo di controllare la scorta.",
    '15' => "⚠️ Promemoria! Il prodotto {product} scade tra 15 giorni ({expiry_date}). Assicurati di averne una scorta sufficiente.",
    '7' => "🚨 Attenzione! Il prodotto {product} scade tra 7 giorni ({expiry_date}). Ti consigliamo di sostituirlo al più presto.",
    '1' => "🚨 URGENTE! Il prodotto {product} scade domani ({expiry_date}). Sostituiscilo immediatamente.",
    '0' => "🚨 SCADUTO! Il prodotto {product} è scaduto ieri ({expiry_date}). Sostituiscilo immediatamente.",
    '-1' => "🚨 SCADUTO! Il prodotto {product} è scaduto il {expiry_date}. Sostituiscilo immediatamente."
];

// Configurazione giorni di anticipo per gli avvisi
$ALERT_DAYS = [30, 15, 7, 1, 0, -1];

/**
 * Funzione principale del cron
 */
function runExpiryReminders() {
    global $MESSAGES, $ALERT_DAYS;
    
    $pdo = getConnection();
    $current_date = date('Y-m-d');
    
    $sent_count = 0;
    $error_count = 0;
    
    // Controlla ogni giorno di anticipo configurato
    foreach ($ALERT_DAYS as $days) {
        $target_date = date('Y-m-d', strtotime("+{$days} days"));
        
        // Calcola la data/ora della notifica specifica per questo giorno
        $notification_datetime = date('Y-m-d H:i:s', strtotime($target_date . ' 08:00:00'));
        
        // Ottieni prodotti che scadono nella data target e utenti che non hanno ricevuto notifiche recenti
        $sql = "SELECT re.*, u.phone_number, u.name, u.surname, u.last_notification
                FROM jta_reminders_expiry re
                INNER JOIN jta_users u ON re.user_id = u.id
                WHERE re.expiry_date = ?
                AND u.phone_number IS NOT NULL 
                AND u.phone_number != ''
                AND JSON_EXTRACT(re.alerts, '$.alert{$days}') = true
                AND (
                    u.last_notification IS NULL 
                    OR u.last_notification < ?
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target_date, $notification_datetime]);
        $expiring_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expiring_products as $product) {
            try {
                // Prepara il messaggio
                $message = str_replace(
                    ['{product}', '{expiry_date}'],
                    [
                        $product['product_name'],
                        date('d/m/Y', strtotime($product['expiry_date']))
                    ],
                    $MESSAGES[$days]
                );
                
                // Aggiungi saluto personalizzato
                $greeting = "Ciao " . ($product['name'] ?: 'utente') . "! ";
                $message = $greeting . $message;
                
                // Aggiungi note se presenti
                if (!empty($product['notes'])) {
                    $message .= "\n\nNote: " . $product['notes'];
                }
                
                // Invia il messaggio WhatsApp
                if (sendWhatsAppMessage($product['phone_number'], $message)) {
                    // Aggiorna la data dell'ultima notifica per l'utente con la data/ora della notifica specifica
                    $update_sql = "UPDATE jta_users SET last_notification = ? WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([$notification_datetime, $product['user_id']]);
                    
                    $sent_count++;
                    echo "Messaggio scadenza inviato a {$product['phone_number']} per il prodotto {$product['product_name']} (tra {$days} giorni) - Notifica: {$notification_datetime}\n";
                } else {
                    $error_count++;
                    echo "Errore invio messaggio scadenza a {$product['phone_number']} per il prodotto {$product['product_name']}\n";
                }
                
            } catch (Exception $e) {
                $error_count++;
                echo "Errore elaborazione scadenza ID {$product['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "Cron scadenze completato: {$sent_count} messaggi inviati, {$error_count} errori\n";
    
    return $error_count == 0;
}

// Esegui il cron
$result = executeCron('reminder_expiry.php', 'runExpiryReminders');

if ($result) {
    echo "Cron scadenze eseguito con successo\n";
    exit(0);
} else {
    echo "Cron scadenze completato con errori\n";
    exit(1);
} 