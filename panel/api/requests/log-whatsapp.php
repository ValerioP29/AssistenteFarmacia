<?php
/**
 * API - Log Messaggio WhatsApp
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Verifica autenticazione
requireApiAuth(['admin', 'pharmacist']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['request_id']) || !isset($input['message']) || !isset($input['whatsapp_data'])) {
        throw new Exception('Parametri mancanti');
    }
    
    $requestId = (int)$input['request_id'];
    $message = $input['message'];
    $whatsappData = $input['whatsapp_data'];
    
    $db = Database::getInstance();
    
    // Verifica che la richiesta esista
    $request = $db->fetchOne("SELECT * FROM jta_requests WHERE id = ? AND deleted_at IS NULL", [$requestId]);
    if (!$request) {
        throw new Exception('Richiesta non trovata');
    }
    
    // Aggiungi log del messaggio WhatsApp ai metadata
    $metadata = json_decode($request['metadata'], true) ?: [];
    
    if (!isset($metadata['whatsapp_messages'])) {
        $metadata['whatsapp_messages'] = [];
    }
    
    $metadata['whatsapp_messages'][] = [
        'message' => $message,
        'whatsapp_data' => $whatsappData,
        'sent_by' => $_SESSION['user_id'] ?? 'admin',
        'sent_at' => date('Y-m-d H:i:s')
    ];
    
    // Aggiorna i metadata
    $db->update('jta_requests', ['metadata' => json_encode($metadata)], 'id = ?', [$requestId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Messaggio WhatsApp loggato con successo'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 