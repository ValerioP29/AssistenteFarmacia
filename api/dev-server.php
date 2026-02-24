<?php
/**
 * Router per server di sviluppo PHP con supporto .htaccess e CORS
 * 
 * Uso: php -S localhost:8000 -t . dev-server.php
 * 
 * Questo script funziona come router per il server PHP built-in
 * e simula il comportamento di Apache con .htaccess
 */

// CORS Headers (simula .htaccess)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");

// Gestione richieste OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Gestione Authorization header (come nel .htaccess)
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION'];
}

// Percorso del file richiesto
$request_uri = $_SERVER['REQUEST_URI'];
$file_path = __DIR__ . parse_url($request_uri, PHP_URL_PATH);

// Se il file esiste, servilo
if (file_exists($file_path) && is_file($file_path)) {
    return false; // Lascia che PHP serva il file
}

// Altrimenti, reindirizza a index.php o gestisci come necessario
if (file_exists(__DIR__ . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    include __DIR__ . '/index.php';
} else {
    http_response_code(404);
    echo "File not found: " . htmlspecialchars($request_uri);
}
?> 