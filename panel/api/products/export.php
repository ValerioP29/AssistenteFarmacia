<?php
/**
 * API Export Prodotti
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

try {
    // Parametri filtri
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $brand = trim($_GET['brand'] ?? '');
    $status = $_GET['status'] ?? '';
    
    // Costruisci la query
    $sql = "SELECT * FROM jta_global_prods WHERE 1=1";
    $params = [];
    
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
        $params[] = intval($status);
    }
    
    $sql .= " ORDER BY name ASC";
    
    // Esegui query
    $products = db_fetch_all($sql, $params);
    
    // Imposta headers per download CSV
    $filename = 'prodotti_globali_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Crea output stream
    $output = fopen('php://output', 'w');
    
    // BOM per UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Intestazioni CSV
    $headers = [
        'ID',
        'SKU',
        'Nome',
        'Descrizione',
        'Categoria',
        'Brand',
        'Principio Attivo',
        'Forma Farmaceutica',
        'Dosaggio',
        'Confezione',
        'Richiede Ricetta',
        'Stato',
        'Data Creazione',
        'Data Aggiornamento'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Dati prodotti
    foreach ($products as $product) {
        $row = [
            $product['id'],
            $product['sku'],
            $product['name'],
            $product['description'] ?? '',
            $product['category'] ?? '',
            $product['brand'] ?? '',
            $product['active_ingredient'] ?? '',
            $product['dosage_form'] ?? '',
            $product['strength'] ?? '',
            $product['package_size'] ?? '',
            $product['requires_prescription'] ? 'Sì' : 'No',
            $product['is_active'] ? 'Attivo' : 'Inattivo',
            $product['created_at'],
            $product['updated_at']
        ];
        
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    
    // Log attività
    logActivity('products_exported', [
        'count' => count($products),
        'filters' => [
            'search' => $search,
            'category' => $category,
            'brand' => $brand,
            'status' => $status
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Errore nell'export: " . $e->getMessage();
}

?> 