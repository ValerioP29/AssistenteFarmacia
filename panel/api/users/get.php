<?php
/**
 * API - Recupera Dati Utente
 * Assistente Farmacia Panel
 */

// Avvia sessione
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

// Controllo metodo
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Carica middleware di autenticazione
require_once '../../includes/auth_middleware.php';

// Controllo autenticazione e ruolo
requireApiAuth(['admin']);

// Verifica parametro user_id
$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID utente richiesto']);
    exit;
}

try {
    // Recupera dati utente
    $user = db_fetch_one("
        SELECT u.id, u.slug_name, u.name, u.surname, u.email, u.phone_number, u.role, u.status, u.starred_pharma, u.created_at, u.updated_at, u.last_access, p.nice_name as pharmacy_name 
        FROM jta_users u 
        LEFT JOIN jta_pharmas p ON u.starred_pharma = p.id 
        WHERE u.id = ? AND u.role != 'admin' AND u.status != 'deleted'
    ", [$user_id]);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
        exit;
    }

    // Rimuovi la password per sicurezza
    unset($user['password']);

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (Exception $e) {
    error_log("Errore recupero utente: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?>
