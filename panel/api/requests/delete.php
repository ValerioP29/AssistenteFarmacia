<?php
/**
 * API - Elimina Richiesta (Soft Delete)
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
    
    if (!isset($input['request_id'])) {
        throw new Exception('ID richiesta mancante');
    }
    
    $requestId = (int)$input['request_id'];
    $reason = $input['reason'] ?? 'Eliminata dall\'amministratore';
    
    $db = Database::getInstance();
    
    // Verifica che la richiesta esista e non sia già eliminata
    $request = $db->fetchOne("SELECT * FROM jta_requests WHERE id = ? AND deleted_at IS NULL", [$requestId]);
    if (!$request) {
        throw new Exception('Richiesta non trovata o già eliminata');
    }
    
    // Soft delete - imposta deleted_at
    $updateData = [
        'deleted_at' => date('Y-m-d H:i:s')
    ];
    
    $db->update('jta_requests', $updateData, 'id = ?', [$requestId]);
    
    // Aggiungi nota di eliminazione ai metadata
    $metadata = json_decode($request['metadata'], true) ?: [];
    $metadata['notes'][] = [
        'text' => "Richiesta eliminata: {$reason}",
        'status' => 'deleted',
        'updated_by' => $_SESSION['user_id'] ?? 'admin',
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $db->update('jta_requests', ['metadata' => json_encode($metadata)], 'id = ?', [$requestId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Richiesta eliminata con successo',
        'data' => [
            'request_id' => $requestId,
            'deleted_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 