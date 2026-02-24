<?php
/**
 * API - Aggiorna Stato Richiesta
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
    
    if (!isset($input['request_id']) || !isset($input['status'])) {
        throw new Exception('Parametri mancanti: request_id e status sono obbligatori');
    }
    
    $requestId = (int)$input['request_id'];
    $status = (int)$input['status'];
    $note = $input['note'] ?? '';
    
    // Validazione status
    $validStatuses = [0, 1, 2, 3, 4];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Stato non valido');
    }
    
    $db = Database::getInstance();
    
    // Verifica che la richiesta esista
    $request = $db->fetchOne("SELECT * FROM jta_requests WHERE id = ? AND deleted_at IS NULL", [$requestId]);
    if (!$request) {
        throw new Exception('Richiesta non trovata');
    }
    
    // Aggiorna lo stato
    $updateData = [
        'status' => $status
    ];
    
    $db->update('jta_requests', $updateData, 'id = ?', [$requestId]);
    
    // Se la richiesta è stata completata (status = 2), inserisci i punti nel log
    if ($status == 2) {
        insertUserPointsLog($request['user_id'], $request['pharma_id'], $request['request_type']);
    }
    
    // Aggiungi nota ai metadata se fornita
    if (!empty($note)) {
        $metadata = json_decode($request['metadata'], true) ?: [];
        $metadata['notes'][] = [
            'text' => $note,
            'status' => $status,
            'updated_by' => $_SESSION['user_id'] ?? 'admin',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $db->update('jta_requests', ['metadata' => json_encode($metadata)], 'id = ?', [$requestId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Stato richiesta aggiornato con successo',
        'data' => [
            'request_id' => $requestId,
            'status' => $status,
            'status_label' => getStatusLabel($status),
            'status_color' => getStatusColor($status)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Funzioni helper per le etichette
 */
function getStatusLabel($status) {
    $labels = [
        0 => 'In attesa',
        1 => 'In lavorazione',
        2 => 'Completata',
        3 => 'Rifiutata',
        4 => 'Annullata'
    ];
    return $labels[$status] ?? 'Sconosciuto';
}

function getStatusColor($status) {
    $colors = [
        0 => 'warning',
        1 => 'info',
        2 => 'success',
        3 => 'danger',
        4 => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
}
?> 