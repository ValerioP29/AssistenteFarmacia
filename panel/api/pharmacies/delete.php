<?php
/**
 * API - Elimina Farmacia
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

// Verifica CSRF
$csrf_token = $_POST['csrf_token'] ?? '';

if (!isset($_SESSION['csrf_token'])) {
    generateCSRFToken();
}

if (!verifyCSRFToken($csrf_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido']);
    exit;
}

try {
    // Validazione input
    $pharmacy_id = (int)($_POST['pharmacy_id'] ?? 0);

    if (!$pharmacy_id) {
        echo json_encode(['success' => false, 'message' => 'ID farmacia richiesto']);
        exit;
    }

    // Verifica che la farmacia esista
    $pharmacy = db_fetch_one("SELECT id, nice_name FROM jta_pharmas WHERE id = ? AND status != 'deleted'", [$pharmacy_id]);
    if (!$pharmacy) {
        echo json_encode(['success' => false, 'message' => 'Farmacia non trovata']);
        exit;
    }

    // Verifica se ci sono utenti associati a questa farmacia
    $users_count = db_fetch_one("SELECT COUNT(*) as count FROM jta_users WHERE starred_pharma = ? AND status != 'deleted'", [$pharmacy_id]);
    if ($users_count['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Non è possibile eliminare una farmacia che ha utenti associati']);
        exit;
    }

    // Soft delete della farmacia
    $result = db()->update('jta_pharmas', 
        ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')], 
        'id = ?', 
        [$pharmacy_id]
    );

    if ($result) {
        // Log attività
        logActivity('pharmacy_deleted', [
            'admin_id' => $_SESSION['user_id'],
            'pharmacy_id' => $pharmacy_id,
            'pharmacy_name' => $pharmacy['nice_name']
        ]);

        echo json_encode([
            'success' => true, 
            'message' => 'Farmacia eliminata con successo'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione della farmacia']);
    }

} catch (Exception $e) {
    error_log("Errore eliminazione farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 