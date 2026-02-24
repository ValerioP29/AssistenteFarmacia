<?php
/**
 * API Logout
 * Assistente Farmacia Panel
 */

// Avvia sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carica configurazione
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Imposta header JSON
header('Content-Type: application/json');

// Verifica metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Verifica CSRF token (opzionale per il logout)
$headers = getallheaders();
$csrf_token = $headers['X-CSRF-Token'] ?? '';

// Se il token è fornito, verificalo; altrimenti procedi comunque
if (!empty($csrf_token) && !verifyCSRFToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido']);
    exit;
}

try {
    // Log attività
    if (isLoggedIn()) {
        logActivity('logout', ['user_id' => $_SESSION['user_id']]);
    }
    
    // Distruggi sessione
    session_destroy();
    
    // Risposta di successo
    echo json_encode([
        'success' => true,
        'message' => 'Logout effettuato con successo'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore durante il logout'
    ]);
}
?> 