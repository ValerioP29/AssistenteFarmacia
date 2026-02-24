<?php
/**
 * API Elimina Prodotto Farmacia
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
    
    // Verifica metodo
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Metodo non consentito'
        ]);
        exit;
    }
    
    // Leggi input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID prodotto non valido'
        ]);
        exit;
    }
    
    // Verifica se prodotto esiste e appartiene alla farmacia
    $product = db_fetch_one("SELECT * FROM jta_pharma_prods WHERE id = ? AND pharma_id = ?", [$id, $pharmacyId]);
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Prodotto non trovato'
        ]);
        exit;
    }
    
    // Elimina dal database
    $affected = db()->delete('jta_pharma_prods', 'id = ?', [$id]);
    
    if ($affected === 0) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nell\'eliminazione del prodotto'
        ]);
        exit;
    }
    
    // Log attività
    logActivity('product_deleted', [
        'product_id' => $id,
        'pharma_id' => $pharmacyId,
        'sku' => $product['sku'],
        'name' => $product['name']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Prodotto eliminato con successo'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 