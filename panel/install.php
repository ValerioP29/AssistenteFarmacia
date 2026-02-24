<?php
/**
 * Installazione Assistente Farmacia Panel
 * Script per configurare automaticamente il progetto
 */

// Configurazione iniziale
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Colori per output console
$colors = [
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'red' => "\033[31m",
    'cyan' => "\033[36m",
    'reset' => "\033[0m",
    'bold' => "\033[1m"
];

function coloredOutput($text, $color = 'reset') {
    global $colors;
    return $colors[$color] . $text . $colors['reset'];
}

// Header
echo coloredOutput("╔══════════════════════════════════════════════════════════════╗\n", 'cyan');
echo coloredOutput("║                ASSISTENTE FARMACIA PANEL                    ║\n", 'cyan');
echo coloredOutput("║                     Installazione                           ║\n", 'cyan');
echo coloredOutput("╚══════════════════════════════════════════════════════════════╝\n", 'cyan');
echo "\n";

// Verifica se è già installato
if (file_exists('config/database.php') && !isset($_GET['force'])) {
    echo coloredOutput("⚠️  Il progetto sembra già essere installato.\n", 'yellow');
    echo coloredOutput("   Aggiungi ?force=1 all'URL per forzare la reinstallazione.\n", 'yellow');
    echo "\n";
    exit;
}

// Verifica requisiti
echo coloredOutput("🔍 Verifica Requisiti:\n", 'bold');

// Verifica PHP
$phpVersion = PHP_VERSION;
$minPhpVersion = '7.4.0';
if (version_compare($phpVersion, $minPhpVersion, '>=')) {
    echo coloredOutput("   ✅ PHP Version: ", 'green') . $phpVersion . "\n";
} else {
    echo coloredOutput("   ❌ PHP Version: ", 'red') . $phpVersion . " (Richiesto: {$minPhpVersion}+)\n";
    exit;
}

// Verifica estensioni
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo coloredOutput("   ✅ Estensione: ", 'green') . $ext . "\n";
    } else {
        echo coloredOutput("   ❌ Estensione mancante: ", 'red') . $ext . "\n";
        exit;
    }
}

// Verifica permessi directory
$directories = ['config', 'includes', 'api', 'api/whatsapp', 'api/auth', 'api/dashboard', 'api/pharmacies', 'api/users', 'assets', 'assets/css', 'assets/js', 'assets/js/core', 'uploads', 'logs', 'classes', 'images'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo coloredOutput("   ✅ Directory creata: ", 'green') . $dir . "\n";
        } else {
            echo coloredOutput("   ❌ Errore creazione directory: ", 'red') . $dir . "\n";
            exit;
        }
    } else {
        echo coloredOutput("   ✅ Directory esistente: ", 'green') . $dir . "\n";
    }
}

echo "\n";

// Configurazione database
echo coloredOutput("🗄️  Configurazione Database:\n", 'bold');

$dbHost = $_POST['db_host'] ?? 'localhost';
$dbName = $_POST['db_name'] ?? 'jt_assistente_farmacia';
$dbUser = $_POST['db_user'] ?? 'root';
$dbPass = $_POST['db_pass'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Test connessione database
    try {
        $dsn = "mysql:host={$dbHost};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo coloredOutput("   ✅ Connessione database riuscita\n", 'green');
        
        // Crea database se non esiste
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        echo coloredOutput("   ✅ Database creato/selezionato: ", 'green') . $dbName . "\n";
        
        // Crea tabelle base se non esistono
        $pdo->exec("USE `{$dbName}`");
        
        // Tabella utenti
        $pdo->exec("CREATE TABLE IF NOT EXISTS `jta_users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `email` varchar(100) NOT NULL,
            `role` enum('admin','pharmacist') NOT NULL DEFAULT 'pharmacist',
            `pharmacy_id` int(11) DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        // Tabella farmacie
        $pdo->exec("CREATE TABLE IF NOT EXISTS `jta_pharmas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `business_name` varchar(255) NOT NULL,
            `nice_name` varchar(100) NOT NULL,
            `email` varchar(100) NOT NULL,
            `phone_number` varchar(20) DEFAULT NULL,
            `city` varchar(100) DEFAULT NULL,
            `address` text DEFAULT NULL,
            `latlng` varchar(50) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `working_info` json DEFAULT NULL,
            `turno_giorno` varchar(10) DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `nice_name` (`nice_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        // Tabella log attività
        $pdo->exec("CREATE TABLE IF NOT EXISTS `jta_activity_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `action` varchar(100) NOT NULL,
            `details` json DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `action` (`action`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        // Tabella richieste
        $pdo->exec("CREATE TABLE IF NOT EXISTS `jta_requests` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `request_type` enum('event','service','promos','reservation') NOT NULL,
            `user_id` int(11) UNSIGNED NOT NULL,
            `pharma_id` int(11) UNSIGNED NOT NULL,
            `message` mediumtext NOT NULL,
            `metadata` longtext NOT NULL,
            `status` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `pharma_id` (`pharma_id`),
            KEY `user_id` (`user_id`),
            KEY `request_type` (`request_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        echo coloredOutput("   ✅ Tabelle database create\n", 'green');
        
        // Crea utente admin se non esiste
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM jta_users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $adminPassword = password_hash('password', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO jta_users (username, password, email, role) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', $adminPassword, 'admin@assistentefarmacia.it', 'admin']);
            echo coloredOutput("   ✅ Utente admin creato\n", 'green');
        }
        
        // Crea file di configurazione
        $configContent = "<?php
/**
 * Configurazione Database
 * Assistente Farmacia Panel
 */

// Configurazione Database (solo se non già definite)
if (!defined('DB_HOST')) define('DB_HOST', '{$dbHost}');
if (!defined('DB_NAME')) define('DB_NAME', '{$dbName}');
if (!defined('DB_USER')) define('DB_USER', '{$dbUser}');
if (!defined('DB_PASS')) define('DB_PASS', '{$dbPass}');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Configurazione Applicazione (solo se non già definite)
if (!defined('APP_NAME')) define('APP_NAME', 'Assistente Farmacia Panel');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.1.0');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost:8000');
if (!defined('APP_PATH')) define('APP_PATH', __DIR__ . '/../');

// Configurazione Sessione (solo se non già definite)
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'assistente_farmacia');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 ora

// Configurazione Sicurezza (solo se non già definite)
if (!defined('HASH_COST')) define('HASH_COST', 12);
if (!defined('JWT_SECRET')) define('JWT_SECRET', '" . bin2hex(random_bytes(32)) . "');

// Configurazione Upload (solo se non già definite)
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', APP_PATH . 'uploads/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
if (!defined('ALLOWED_EXTENSIONS')) define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Configurazione Email (opzionale) - solo se non già definite
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
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

// Carica configurazione sviluppo se presente e non già caricata
if (file_exists(__DIR__ . '/development.php') && !defined('DEVELOPMENT_MODE')) {
    require_once __DIR__ . '/development.php';
}

/**
 * Classe Database per la connessione
 */
class Database {
    private static \$instance = null;
    private \$connection;
    
    private function __construct() {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
            \$options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            \$this->connection = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            throw new Exception(\"Errore connessione database: \" . \$e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::\$instance === null) {
            self::\$instance = new self();
        }
        return self::\$instance;
    }
    
    public function getConnection() {
        return \$this->connection;
    }
    
    public function query(\$sql, \$params = []) {
        try {
            \$stmt = \$this->connection->prepare(\$sql);
            \$stmt->execute(\$params);
            return \$stmt;
        } catch (PDOException \$e) {
            throw new Exception(\"Errore query: \" . \$e->getMessage());
        }
    }
    
    public function fetchAll(\$sql, \$params = []) {
        return \$this->query(\$sql, \$params)->fetchAll();
    }
    
    public function fetchOne(\$sql, \$params = []) {
        return \$this->query(\$sql, \$params)->fetch();
    }
    
    public function insert(\$table, \$data) {
        \$fields = array_keys(\$data);
        \$placeholders = ':' . implode(', :', \$fields);
        \$sql = \"INSERT INTO {\$table} (\" . implode(', ', \$fields) . \") VALUES ({\$placeholders})\";
        
        \$this->query(\$sql, \$data);
        return \$this->connection->lastInsertId();
    }
    
    public function update(\$table, \$data, \$where, \$whereParams = []) {
        \$fields = array_keys(\$data);
        \$set = implode(' = ?, ', \$fields) . ' = ?';
        \$sql = \"UPDATE {\$table} SET {\$set} WHERE {\$where}\";
        
        \$params = array_values(\$data);
        \$params = array_merge(\$params, \$whereParams);
        
        return \$this->query(\$sql, \$params)->rowCount();
    }
    
    public function delete(\$table, \$where, \$params = []) {
        \$sql = \"DELETE FROM {\$table} WHERE {\$where}\";
        return \$this->query(\$sql, \$params)->rowCount();
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
function db_query(\$sql, \$params = []) {
    return db()->query(\$sql, \$params);
}

/**
 * Funzione helper per ottenere tutti i risultati
 */
function db_fetch_all(\$sql, \$params = []) {
    return db()->fetchAll(\$sql, \$params);
}

/**
 * Funzione helper per ottenere un singolo risultato
 */
function db_fetch_one(\$sql, \$params = []) {
    return db()->fetchOne(\$sql, \$params);
}
?>";

        if (file_put_contents('config/database.php', $configContent)) {
            echo coloredOutput("   ✅ File configurazione creato\n", 'green');
        } else {
            echo coloredOutput("   ❌ Errore creazione file configurazione\n", 'red');
            exit;
        }
        
        // Crea file development.php
        $developmentContent = "<?php
/**
 * Configurazione Sviluppo
 * Assistente Farmacia Panel
 */

// Modalità sviluppo
define('DEVELOPMENT_MODE', true);

// Configurazione Applicazione (solo se non già definite)
if (!defined('APP_URL')) define('APP_URL', 'http://localhost:8000');

// Configurazione Database (solo se non già definite)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'jt_assistente_farmacia');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', 'root');

// Configurazione Email (solo se non già definite)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_FROM')) define('SMTP_FROM', 'noreply@assistentefarmacia.it');

// Configurazione WhatsApp (solo se non già definite)
if (!defined('WHATSAPP_API_KEY')) define('WHATSAPP_API_KEY', '');
if (!defined('WHATSAPP_PHONE_ID')) define('WHATSAPP_PHONE_ID', '');
if (!defined('WHATSAPP_BASE_URL')) define('WHATSAPP_BASE_URL', 'https://waservice-pharma1.jungleteam.it');

// Configurazione Google Maps (solo se non già definite)
if (!defined('GOOGLE_MAPS_API_KEY')) define('GOOGLE_MAPS_API_KEY', '');

// Configurazione Error Reporting per sviluppo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/development.log');
?>";

        if (file_put_contents('config/development.php', $developmentContent)) {
            echo coloredOutput("   ✅ File configurazione sviluppo creato\n", 'green');
        } else {
            echo coloredOutput("   ⚠️  Errore creazione file sviluppo\n", 'yellow');
        }
        
        echo "\n";
        echo coloredOutput("🎉 Installazione completata con successo!\n", 'green');
        echo "\n";
        echo coloredOutput("📋 Credenziali di accesso:\n", 'bold');
        echo coloredOutput("   Username: admin\n", 'yellow');
        echo coloredOutput("   Password: password\n", 'yellow');
        echo "\n";
        echo coloredOutput("🌐 Per avviare il server:\n", 'bold');
        echo coloredOutput("   php start_server.php\n", 'cyan');
        echo "\n";
        echo coloredOutput("⚠️  IMPORTANTE: Rimuovi questo file install.php per sicurezza!\n", 'red');
        
    } catch (Exception $e) {
        echo coloredOutput("   ❌ Errore database: ", 'red') . $e->getMessage() . "\n";
        exit;
    }
} else {
    // Form di configurazione
    ?>
    <form method="POST" style="max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
        <h3>Configurazione Database</h3>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Host Database:</label>
            <input type="text" name="db_host" value="<?= htmlspecialchars($dbHost) ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Nome Database:</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($dbName) ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Username Database:</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($dbUser) ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Password Database:</label>
            <input type="password" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        
        <button type="submit" style="background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
            Installa
        </button>
    </form>
    <?php
}
?> 