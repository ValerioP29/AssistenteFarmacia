<?php
/**
 * Configurazione Database
 * Assistente Farmacia Panel
 */

// Configurazione Database (solo se non già definite)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'jt_assistente_farmacia');
if (!defined('DB_USER')) define('DB_USER', 'jta_master_user');
if (!defined('DB_PASS')) define('DB_PASS', 'Z4s097sJRusj1pjDj?$xJt');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Configurazione Applicazione (solo se non già definite)
if (!defined('APP_NAME')) define('APP_NAME', 'Assistente Farmacia Panel');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_URL')) define('APP_URL', 'https://app.assistentefarmacia.it/panel');
if (!defined('APP_PATH')) define('APP_PATH', __DIR__ . '/../');

// Configurazione Sessione (solo se non già definite)
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'assistente_farmacia');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 ora

// Configurazione Sicurezza (solo se non già definite)
if (!defined('HASH_COST')) define('HASH_COST', 12);
if (!defined('JWT_SECRET')) define('JWT_SECRET', 'your-secret-key-change-this-in-production');

// Configurazione Upload (solo se non già definite)
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', APP_PATH . 'uploads/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
if (!defined('ALLOWED_EXTENSIONS')) define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Configurazione Email (opzionale) - solo se non già definite
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 1025); // MailHog per sviluppo
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_FROM')) define('SMTP_FROM', 'noreply@assistentefarmacia.it');

// Configurazione WhatsApp (opzionale) - solo se non già definite
if (!defined('WHATSAPP_API_KEY')) define('WHATSAPP_API_KEY', '');
if (!defined('WHATSAPP_PHONE_ID')) define('WHATSAPP_PHONE_ID', '');
if (!defined('WHATSAPP_BASE_URL')) define('WHATSAPP_BASE_URL', 'https://waservice-pharma1.jungleteam.it');

// Configurazione Google Maps (opzionale) - solo se non già definite
if (!defined('GOOGLE_MAPS_API_KEY')) define('GOOGLE_MAPS_API_KEY', '');

// Configurazione Timezone
date_default_timezone_set('Europe/Rome');

// Configurazione Error Reporting
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configurazione produzione semplice
if (!defined('DEVELOPMENT_MODE')) {
    define('DEVELOPMENT_MODE', false);
}

// Error reporting per produzione
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Classe Database per la connessione
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception("Errore connessione database: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Errore query: " . $e->getMessage());
        }
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $fields = array_keys($data);
        $set = implode(' = ?, ', $fields) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        
        $params = array_values($data);
        $params = array_merge($params, $whereParams);
        
        return $this->query($sql, $params)->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }
}

/**
 * Funzione helper per ottenere la connessione database
 */
function db() {
    return Database::getInstance();
}

/**
 * Funzione helper per eseguire query
 */
function db_query($sql, $params = []) {
    return db()->query($sql, $params);
}

/**
 * Funzione helper per ottenere tutti i risultati
 */
function db_fetch_all($sql, $params = []) {
    return db()->fetchAll($sql, $params);
}

/**
 * Funzione helper per ottenere un singolo risultato
 */
function db_fetch_one($sql, $params = []) {
    return db()->fetchOne($sql, $params);
}
