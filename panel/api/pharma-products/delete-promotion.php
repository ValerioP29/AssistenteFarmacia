<?php
/**
 * API Eliminazione Promozione
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Ottieni farmacia corrente
    $pharmacy = getCurrentPharmacy();
    $pharma_id = $pharmacy['id'] ?? $_SESSION['pharmacy_id'] ?? null;
    
    if (!$pharma_id) {
        throw new Exception('ID farmacia non valido');
    }
    
    // Leggi dati dal JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = intval($input['id'] ?? 0);
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID prodotto richiesto']);
        exit;
    }
    
    // Verifica che il prodotto esista nella farmacia e sia una promozione
    $stmt = $pdo->prepare("SELECT id, name, is_on_sale FROM jta_pharma_prods WHERE id = ? AND pharma_id = ? AND is_on_sale IS NOT NULL");
    $stmt->execute([$product_id, $pharma_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Promozione non trovata']);
        exit;
    }
    
    // Elimina la promozione impostando i campi a NULL e is_on_sale a -1 (nessuna promozione)
    $stmt = $pdo->prepare("UPDATE jta_pharma_prods SET 
        sale_price = NULL, 
        sale_start_date = NULL, 
        sale_end_date = NULL, 
        is_on_sale = NULL
        WHERE id = ? AND pharma_id = ?");
    
    $result = $stmt->execute([$product_id, $pharma_id]);
    
    if ($result) {
        // Log dell'attività
        logActivity('promotion_deleted', [
            'product_id' => $product_id,
            'pharma_id' => $pharma_id,
            'product_name' => $product['name']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Promozione eliminata con successo!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'eliminazione della promozione']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 