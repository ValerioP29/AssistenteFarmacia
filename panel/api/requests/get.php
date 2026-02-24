<?php
/**
 * API - Dettagli Richiesta
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Verifica autenticazione
requireApiAuth(['admin', 'pharmacist']);

header('Content-Type: application/json');

/**
 * Funzioni helper per le etichette
 */
function getRequestTypeLabel($type) {
    $labels = [
        'event' => 'Evento',
        'service' => 'Servizio',
        'promos' => 'Promozione',
        'reservation' => 'Prenotazione'
    ];
    return $labels[$type] ?? $type;
}

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

try {
    $requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$requestId) {
        throw new Exception('ID richiesta mancante');
    }
    
    $db = Database::getInstance();
    
    // Query per ottenere i dettagli della richiesta
    $sql = "SELECT r.*, 
                   p.business_name as pharmacy_name,
                   p.nice_name as pharmacy_nice_name,
                   p.email as pharmacy_email,
                   p.phone_number as pharmacy_phone,
                   p.city as pharmacy_city,
                   p.address as pharmacy_address,
                   CONCAT(u.name, ' ', u.surname) as user_username,
                   u.email as user_email,
                   u.phone_number as user_phone
            FROM jta_requests r
            LEFT JOIN jta_pharmas p ON r.pharma_id = p.id
            LEFT JOIN jta_users u ON r.user_id = u.id
            WHERE r.id = ? AND r.deleted_at IS NULL";
    
    $request = $db->fetchOne($sql, [$requestId]);
    
    if (!$request) {
        throw new Exception('Richiesta non trovata');
    }
    
    // Decodifica metadata
    $metadata = json_decode($request['metadata'], true) ?: [];
    
            // Formatta la risposta
        $formattedRequest = [
            'id' => $request['id'],
            'request_type' => $request['request_type'],
            'request_type_label' => getRequestTypeLabel($request['request_type']),
            'user' => [
                'id' => $request['user_id'],
                'username' => $request['user_username'],
                'phone_number' => $request['user_phone']
            ],
            'pharmacy' => [
                'id' => $request['pharma_id'],
                'name' => $request['pharmacy_name'],
                'nice_name' => $request['pharmacy_nice_name'],
                'email' => $request['pharmacy_email'],
                'phone' => $request['pharmacy_phone'],
                'city' => $request['pharmacy_city'],
                'address' => $request['pharmacy_address']
            ],
            'message' => $request['message'],
            'metadata' => $metadata,
            'status' => (int)$request['status'],
            'status_label' => getStatusLabel($request['status']),
            'status_color' => getStatusColor($request['status']),
            'created_at' => $request['created_at'],
            'created_at_formatted' => date('d/m/Y H:i', strtotime($request['created_at'])),
            'notes' => $metadata['notes'] ?? []
        ];
    
    echo json_encode([
        'success' => true,
        'data' => $formattedRequest
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 