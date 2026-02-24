<?php
/**
 * API Export Prodotti Farmacia
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

try {
    // Ottieni farmacia corrente
    $pharmacy = getCurrentPharmacy();
    $pharmacyId = $pharmacy['id'] ?? 0;
    
    if (!$pharmacyId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Farmacia non trovata'
        ]);
        exit;
    }
    
    // Parametri di filtro
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $brand = trim($_GET['brand'] ?? '');
    $status = $_GET['status'] ?? '';
    
    // Costruisci query con JOIN
    $sql = "SELECT pp.*, gp.category, gp.brand, gp.active_ingredient, gp.dosage_form, gp.strength, gp.package_size 
            FROM jta_pharma_prods pp 
            LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
            WHERE pp.pharma_id = ?";
    $params = [$pharmacyId];
    
    // Aggiungi filtri
    if ($search) {
        $sql .= " AND (pp.sku LIKE ? OR pp.name LIKE ? OR pp.description LIKE ? OR gp.active_ingredient LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($category) {
        $sql .= " AND gp.category = ?";
        $params[] = $category;
    }
    
    if ($brand) {
        $sql .= " AND gp.brand = ?";
        $params[] = $brand;
    }
    
    if ($status !== '') {
        $sql .= " AND pp.is_active = ?";
        $params[] = intval($status);
    }
    
    $sql .= " ORDER BY pp.name ASC";
    
    // Esegui query
    $products = db_fetch_all($sql, $params);
    
    // Imposta headers per download CSV
    $filename = 'prodotti_farmacia_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output BOM per UTF-8
    echo "\xEF\xBB\xBF";
    
    // Apri output stream
    $output = fopen('php://output', 'w');
    
    // Headers CSV
    fputcsv($output, [
        'ID',
        'SKU',
        'Nome',
        'Descrizione',
        'Categoria',
        'Brand',
        'Principio Attivo',
        'Forma Farmaceutica',
        'Concentrazione',
        'Confezione',
        'Prezzo',
        'Prezzo Scontato',
        'Stato',

        'Data Creazione',
        'Data Aggiornamento'
    ]);
    
    // Dati prodotti
    foreach ($products as $product) {
        fputcsv($output, [
            $product['id'],
            $product['sku'],
            $product['name'],
            $product['description'],
            $product['category'],
            $product['brand'],
            $product['active_ingredient'],
            $product['dosage_form'],
            $product['strength'],
            $product['package_size'],
            $product['price'],
            $product['sale_price'],
            $product['is_active'] ? 'Attivo' : 'Inattivo',
            $product['created_at'],
            $product['updated_at']
        ]);
    }
    
    fclose($output);
    
    // Log attività
    logActivity('products_exported', [
        'pharma_id' => $pharmacyId,
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
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 