<?php
/**
 * API Ricerca Prodotti Globali
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

header('Content-Type: application/json');

try {
    // Verifica metodo
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Metodo non consentito'
        ]);
        exit;
    }
    
    // Leggi parametro di ricerca
    $query = trim($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'products' => []
        ]);
        exit;
    }
    
    // Ricerca prodotti globali attivi
    $sql = "SELECT id, sku, name, description, category, brand, active_ingredient, image 
            FROM jta_global_prods 
            WHERE is_active = 'active' 
            AND (sku LIKE ? OR name LIKE ? OR description LIKE ? OR active_ingredient LIKE ?)
            ORDER BY name ASC 
            LIMIT 10";
    
    $searchParam = "%{$query}%";
    $products = db_fetch_all($sql, [$searchParam, $searchParam, $searchParam, $searchParam]);
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 