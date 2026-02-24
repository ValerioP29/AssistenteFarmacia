<?php
/**
 * API Lista Prodotti Globali
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso admin per API
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Autenticazione richiesta'
    ]);
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Accesso negato - Solo admin'
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    // Parametri di paginazione e filtri
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $brand = trim($_GET['brand'] ?? '');
    $status = $_GET['status'] ?? '';
    
    // Costruisci la query base
    $sql = "SELECT * FROM jta_global_prods WHERE 1=1";
    $params = [];
    
    // Aggiungi filtri
    if ($search) {
        $sql .= " AND (sku LIKE ? OR name LIKE ? OR description LIKE ? OR active_ingredient LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    if ($brand) {
        $sql .= " AND brand = ?";
        $params[] = $brand;
    }
    
    if ($status !== '') {
        $sql .= " AND is_active = ?";
        $params[] = $status;
    }
    
    // Conta totale record
    $countSql = str_replace("SELECT *", "SELECT COUNT(*)", $sql);
    $total = db_fetch_one($countSql, $params)['COUNT(*)'];
    
    // Aggiungi ordinamento e paginazione
    $sql .= " ORDER BY name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Esegui query
    $products = db_fetch_all($sql, $params);
    
    // Calcola paginazione
    $totalPages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => intval($total),
        'current_page' => $page,
        'total_pages' => $totalPages,
        'per_page' => $limit,
        'filters' => [
            'search' => $search,
            'category' => $category,
            'brand' => $brand,
            'status' => $status
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 