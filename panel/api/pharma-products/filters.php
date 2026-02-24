<?php
/**
 * API Filtri Prodotti Farmacia
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

header('Content-Type: application/json');

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
    
    // Ottieni categorie uniche dai prodotti globali associati
    $categories = db_fetch_all("
        SELECT DISTINCT gp.category 
        FROM jta_pharma_prods pp 
        LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
        WHERE pp.pharma_id = ? AND gp.category IS NOT NULL AND gp.category != ''
        ORDER BY gp.category
    ", [$pharmacyId]);
    
    // Ottieni brand unici dai prodotti globali associati
    $brands = db_fetch_all("
        SELECT DISTINCT gp.brand 
        FROM jta_pharma_prods pp 
        LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
        WHERE pp.pharma_id = ? AND gp.brand IS NOT NULL AND gp.brand != ''
        ORDER BY gp.brand
    ", [$pharmacyId]);
    
    // Converti in array semplice
    $categoryList = array_column($categories, 'category');
    $brandList = array_column($brands, 'brand');
    
    echo json_encode([
        'success' => true,
        'categories' => $categoryList,
        'brands' => $brandList
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 