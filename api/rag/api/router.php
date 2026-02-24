<?php
/**
 * Router API per RAG Engine
 * 
 * Gestisce tutte le richieste API e le instrada agli endpoint appropriati
 */

// Includi il bootstrap del progetto principale
require_once __DIR__ . '/../../_api_bootstrap.php';

// Gestisci CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Determina l'endpoint
$endpoint = '';

// Prova a ottenere l'endpoint dal path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Rimuovi il percorso base se presente
$basePath = '/api';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Rimuovi slash iniziale e finale
$path = trim($path, '/');

// Rimuovi "router.php" dal path se presente
$path = str_replace('router.php', '', $path);
$path = trim($path, '/');

// Priorità al parametro GET endpoint
if (isset($_GET['endpoint']) && !empty($_GET['endpoint'])) {
    $endpoint = $_GET['endpoint'];
} elseif (!empty($path)) {
    // Fallback al path se non c'è parametro GET
    $endpoint = $path;
} else {
    $endpoint = '';
}

// Debug: log dell'endpoint (commentato per produzione)
// error_log("RAG Router - Endpoint: '$endpoint', Method: " . $_SERVER['REQUEST_METHOD']);
// error_log("RAG Router - Request URI: " . $_SERVER['REQUEST_URI']);
// error_log("RAG Router - Path: " . $path);

// Instrada la richiesta
switch ($endpoint) {
    case 'chat':
        require_once __DIR__ . '/endpoints/chat.php';
        break;
        
    case 'documents':
        require_once __DIR__ . '/endpoints/documents.php';
        break;
        
    case '':
    case 'help':
        // Documentazione API
        echo json_encode([
            'success' => true,
            'message' => 'RAG Engine API',
            'endpoints' => [
                'POST /api/chat' => [
                    'description' => 'Esegue una query RAG',
                    'parameters' => [
                        'question' => 'string (obbligatorio) - La domanda dell\'utente',
                        'use_rag' => 'boolean (opzionale) - Usa RAG o solo GPT',
                        'debug' => 'boolean (opzionale) - Abilita modalità debug',
                        'max_chunks' => 'integer (opzionale) - Numero massimo di chunk',
                        'max_tokens' => 'integer (opzionale) - Limite token',
                        'config' => 'object (opzionale) - Configurazione personalizzata'
                    ]
                ],
                'GET /api/documents' => [
                    'description' => 'Lista tutti i documenti',
                    'parameters' => [
                        'config' => 'string (opzionale) - Configurazione personalizzata in JSON'
                    ]
                ],
                'POST /api/documents' => [
                    'description' => 'Carica un nuovo documento',
                    'parameters' => [
                        'content' => 'string (obbligatorio) - Contenuto del documento',
                        'filename' => 'string (opzionale) - Nome del file',
                        'metadata' => 'object (opzionale) - Metadati aggiuntivi'
                    ]
                ],
                'DELETE /api/documents' => [
                    'description' => 'Rimuove un documento',
                    'parameters' => [
                        'id' => 'string (obbligatorio) - ID del documento da rimuovere'
                    ]
                ]
            ]
        ], JSON_PRETTY_PRINT);
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint non trovato',
            'available_endpoints' => ['chat', 'documents', 'help']
        ], JSON_PRETTY_PRINT);
        break;
} 