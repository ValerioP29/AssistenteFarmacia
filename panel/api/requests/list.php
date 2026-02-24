<?php
/**
 * API - Lista Richieste
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Verifica autenticazione
requireApiAuth(['admin', 'pharmacist']);

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    
    // Parametri di paginazione e filtri
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $request_type = isset($_GET['request_type']) ? $_GET['request_type'] : null;
    $pharma_id = isset($_GET['pharma_id']) ? (int)$_GET['pharma_id'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : null;
    
    // Costruisci query base
    $whereConditions = ["r.deleted_at IS NULL"];
    $params = [];
    
    if ($status !== null && $status !== '') {
        $whereConditions[] = "r.status = ?";
        $params[] = $status;
    }
    
    if ($request_type !== null && $request_type !== '') {
        $whereConditions[] = "r.request_type = ?";
        $params[] = $request_type;
    }
    
    if ($pharma_id !== null) {
        $whereConditions[] = "r.pharma_id = ?";
        $params[] = $pharma_id;
    }
    
    if ($search !== null && $search !== '') {
        $whereConditions[] = "(r.message LIKE ? OR p.business_name LIKE ? OR p.nice_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Query per contare totale
    $countSql = "SELECT COUNT(*) as total FROM jta_requests r 
                 LEFT JOIN jta_pharmas p ON r.pharma_id = p.id 
                 WHERE {$whereClause}";
    
    $totalResult = $db->fetchOne($countSql, $params);
    $total = $totalResult['total'];
    
    // Query principale
    $sql = "SELECT r.*, 
                   p.business_name as pharmacy_name,
                   p.nice_name as pharmacy_nice_name,
                   CONCAT(u.name, ' ', u.surname) as user_username,
                   u.phone_number as user_phone
            FROM jta_requests r
            LEFT JOIN jta_pharmas p ON r.pharma_id = p.id
            LEFT JOIN jta_users u ON r.user_id = u.id
            WHERE {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    $requests = $db->fetchAll($sql, $params);
    
    // Formatta i dati
    $formattedRequests = [];
    foreach ($requests as $request) {
        $metadata = json_decode($request['metadata'], true) ?: [];
        
        $formattedRequests[] = [
            'id' => $request['id'],
            'request_type' => $request['request_type'],
            'request_type_label' => getRequestTypeLabel($request['request_type']),
            'user_id' => $request['user_id'],
            'user_username' => $request['user_username'],
            'user_phone' => $request['user_phone'],
            'pharma_id' => $request['pharma_id'],
            'pharmacy_name' => $request['pharmacy_name'],
            'pharmacy_nice_name' => $request['pharmacy_nice_name'],
            'message' => $request['message'],
            'metadata' => $metadata,
            'status' => $request['status'],
            'status_label' => getStatusLabel($request['status']),
            'status_color' => getStatusColor($request['status']),
            'created_at' => $request['created_at'],
            'created_at_formatted' => date('d/m/Y H:i', strtotime($request['created_at']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedRequests,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

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
        0 => 'warning',    // Giallo per in attesa
        1 => 'info',       // Blu per in lavorazione
        2 => 'success',    // Verde per completata
        3 => 'danger',     // Rosso per rifiutata
        4 => 'secondary'   // Grigio per annullata
    ];
    return $colors[$status] ?? 'secondary';
}
?> 