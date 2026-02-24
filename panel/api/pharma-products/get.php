<?php
/**
 * API Ottieni Prodotto Farmacia
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
    
    // Leggi ID prodotto
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID prodotto non valido'
        ]);
        exit;
    }
    
    // Query per ottenere prodotto con dati del prodotto globale
    $sql = "SELECT pp.*, gp.category, gp.brand, gp.active_ingredient, gp.dosage_form, gp.strength, gp.package_size, gp.image as global_image 
            FROM jta_pharma_prods pp 
            LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
            WHERE pp.id = ? AND pp.pharma_id = ?";
    
    $product = db_fetch_one($sql, [$id, $pharmacyId]);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Prodotto non trovato'
        ]);
        exit;
    }
    
    // Gestisci immagine del prodotto
    if (!empty($product['image'])) {
        $product['image'] = $product['image'];
    }
    elseif (!empty($product['global_image'])) {
        $product['image'] = $product['global_image'];
    }
    else {
        $product['image'] = null;
    }
    
    // Rimuovi il campo global_image per evitare confusione
    unset($product['global_image']);
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 