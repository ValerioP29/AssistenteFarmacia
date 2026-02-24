<?php
/**
 * File principale per la gestione dei cron
 * Contiene tutte le funzionalità comuni per i cron jobs
 */

// Verifica che sia eseguito da CLI
if (php_sapi_name() !== 'cli') {
    die('Questo script può essere eseguito solo da riga di comando');
}

// Configurazione
// define('JTA', TRUE);
// date_default_timezone_set('Europe/Rome');

// Carica le dipendenze
require_once(__DIR__ . '/../_api_bootstrap.php');

// require_once(__DIR__ . '/../vendor/autoload.php');
// require_once(__DIR__ . '/../helpers/wa_helpers.php');

// Carica variabili ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configurazione email errori
define('CRON_ERROR_EMAIL', 'sviluppo@jungleteam.it');

/**
 * Funzione per ottenere la connessione al database
 */
function getConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
            $_ENV['DB_USER'],
            $_ENV['DB_PSW'],
            [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    return $pdo;
}

/**
 * Classe per la gestione dei cron
 */
class CronManager {
    private $pdo;
    private $file_name;
    private $cron_id;
    
    public function __construct($file_name) {
        $this->pdo = getConnection();
        $this->file_name = $file_name;
        $this->cron_id = null;
    }
    
    /**
     * Inizializza il cron e controlla se può essere eseguito
     */
    public function init() {
        try {
            // Verifica che il cron esista e sia attivo
            $stmt = $this->pdo->prepare("SELECT id, is_active, status FROM jta_cron WHERE file_name = ?");
            $stmt->execute([$this->file_name]);
            $cron = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cron) {
                throw new Exception("Cron {$this->file_name} non trovato nel database");
            }
            
            if (!$cron['is_active']) {
                return false;
                // throw new Exception("Cron {$this->file_name} è disattivato");
            }
            
            // Controlla se c'è già un'istanza in esecuzione
            if ($cron['status'] == 1) {
                throw new Exception("Cron {$this->file_name} è già in esecuzione");
            }
            
            $this->cron_id = $cron['id'];
            
            // Aggiorna status a "in esecuzione" e timestamp di inizio
            $stmt = $this->pdo->prepare("UPDATE jta_cron SET status = 1, last_start = NOW() WHERE id = ?");
            $stmt->execute([$this->cron_id]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            return false;
        }
    }
    
    /**
     * Finalizza il cron con successo
     */
    public function success() {
        if ($this->cron_id) {
            $stmt = $this->pdo->prepare("UPDATE jta_cron SET status = 0, last_end = NOW(), last_error = NULL WHERE id = ?");
            $stmt->execute([$this->cron_id]);
        }
    }
    
    /**
     * Finalizza il cron con errore
     */
    public function error($error_message) {
        if ($this->cron_id) {
            $stmt = $this->pdo->prepare("UPDATE jta_cron SET status = -1, last_end = NOW(), last_error = ? WHERE id = ?");
            $stmt->execute([$error_message, $this->cron_id]);
        }

        // Evita di inviare errori per dei cron non attivi
        // L'unico errore/eccezione che si puo' verificare sarebbe quello di cron disattivato.
        // if ($this->cron_id && $this->cron && !$this->cron['is_active']) {
        //     return;
        // }

        // Invia email di errore
        $this->sendErrorEmail($error_message);
    }
    
    /**
     * Invia email di errore
     */
    private function sendErrorEmail($error_message) {
        $subject = "Errore Cron: {$this->file_name}";
        $message = "Si è verificato un errore nel cron {$this->file_name}:\n\n";
        $message .= "Errore: {$error_message}\n";
        $message .= "Data/Ora: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Server: " . gethostname() . "\n";
        
        $headers = "From: cron@jungleteam.it\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        @mail(CRON_ERROR_EMAIL, $subject, $message, $headers);
    }
    
    /**
     * Log dell'errore
     */
    private function logError($message) {
        error_log("Cron {$this->file_name}: {$message}");
    }
}

/**
 * Funzione helper per eseguire un cron
 */
function executeCron($file_name, $callback) {
    $cron = new CronManager($file_name);
    
    if (!$cron->init()) {
        return false;
    }
    
    try {
        // Esegui la callback del cron
        $result = $callback();
        
        if ($result) {
            $cron->success();
            return true;
        } else {
            $cron->error("Cron completato con errori");
            return false;
        }
        
    } catch (Exception $e) {
        $cron->error($e->getMessage());
        return false;
    }
}

/**
 * Funzione helper per inviare messaggi WhatsApp
 */
function sendWhatsAppMessage($phone, $message) {
    try {
        $result = wa_send($message, $phone);
        return $result && isset($result['success']) && $result['success'];
    } catch (Exception $e) {
        error_log("Errore invio WhatsApp: " . $e->getMessage());
        return false;
    }
}

/**
 * Funzione helper per ottenere utenti con terapie attive
 */
function getUsersWithActiveTherapies() {
    $pdo = getConnection();
    
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Funzione helper per ottenere utenti con prodotti in scadenza
 */
function getUsersWithExpiringProducts() {
    $pdo = getConnection();
    
    $sql = "SELECT re.*, u.phone_number, u.name, u.surname, u.last_notification
            FROM jta_reminders_expiry re
            INNER JOIN jta_users u ON re.user_id = u.id
            WHERE u.phone_number IS NOT NULL 
            AND u.phone_number != ''";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
} 