<?php
/**
 * API - Recupera Dati Farmacia
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

try {
    // Recupera ID farmacia
    $pharmacy_id = (int)($_GET['pharmacy_id'] ?? 0);
    
    if (!$pharmacy_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID farmacia richiesto']);
        exit;
    }

    // Recupera dati farmacia
    $pharmacy = db_fetch_one("
        SELECT * FROM jta_pharmas 
        WHERE id = ? AND status != 'deleted'
    ", [$pharmacy_id]);

    if (!$pharmacy) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Farmacia non trovata']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'pharmacy' => $pharmacy
    ]);

} catch (Exception $e) {
    error_log("Errore recupero farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?>
