<?php
/**
 * API Filtri Prodotti
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
    // Ottieni categorie uniche
    $categories = db_fetch_all("SELECT DISTINCT category FROM jta_global_prods WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categoryList = array_column($categories, 'category');
    
    // Ottieni brand unici
    $brands = db_fetch_all("SELECT DISTINCT brand FROM jta_global_prods WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
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