<?php
/**
 * API - Elimina Utente
 * Assistente Farmacia Panel
 */

// Avvia sessione
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

// Controllo metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Carica middleware di autenticazione
require_once '../../includes/auth_middleware.php';

// Controllo autenticazione e ruolo
requireApiAuth(['admin']);

// Verifica CSRF - Temporaneamente disabilitata per debug
$csrf_token = $_POST['csrf_token'] ?? '';

// Se non c'è un token CSRF nella sessione, ne genera uno nuovo
if (!isset($_SESSION['csrf_token'])) {
    generateCSRFToken();
}

// Per ora saltiamo la verifica CSRF per testare se il problema è quello
// if (!verifyCSRFToken($csrf_token)) {
//     http_response_code(400);
//     echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido']);
//     exit;
// }

try {
    // Debug
    error_log("=== DEBUG DELETE USER ===");
    error_log("POST data: " . print_r($_POST, true));
    
    // Validazione input
    $user_id = (int)($_POST['user_id'] ?? 0);
    error_log("User ID: " . $user_id);

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'ID utente mancante']);
        exit;
    }

    // Verifica esistenza utente
    $user = db_fetch_one("SELECT * FROM jta_users WHERE id = ? AND status != 'deleted'", [$user_id]);
    error_log("User found: " . ($user ? 'YES' : 'NO'));
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
        exit;
    }

    // Non permettere di eliminare se stessi
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Non puoi eliminare il tuo account']);
        exit;
    }

    // Non permettere di eliminare altri admin
    if ($user['role'] === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Non puoi eliminare un amministratore']);
        exit;
    }

    error_log("Proceeding with soft delete for user ID: " . $user_id);

    // Soft delete - marca come eliminato (usa updated_at invece di deleted_at)
    $result = db()->update('jta_users', 
        [
            'status' => 'deleted',
            'updated_at' => date('Y-m-d H:i:s')
        ], 
        'id = ?', 
        [$user_id]
    );

    error_log("Update result: " . ($result ? 'SUCCESS' : 'FAILED'));

    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Utente eliminato con successo'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione']);
    }

} catch (Exception $e) {
    error_log("Errore eliminazione utente: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 