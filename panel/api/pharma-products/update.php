<?php
/**
 * API Aggiornamento Promozioni
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Se non è JSON, usa POST
    if (!$input) {
        $input = $_POST;
    }
    
    $id = $input['id'] ?? null;
    $sale_price = $input['sale_price'] ?? null;
    $discount_type = $input['discount_type'] ?? 'amount';
    $percentage_discount = isset($input['percentage_discount']) ? floatval($input['percentage_discount']) : null;
    $sale_start_date = $input['sale_start_date'] ?? null;
    $sale_end_date = $input['sale_end_date'] ?? null;
    $is_on_sale = $input['is_on_sale'] ?? null;
    $is_featured = $input['is_featured'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID promozione richiesto']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, price, sale_price AS existing_sale_price FROM jta_pharma_prods WHERE id = ? AND pharma_id = ?");
    $stmt->execute([$id, $pharma_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Promozione non trovata']);
        exit;
    }

    if ($discount_type === 'percentage' && $percentage_discount !== null && $percentage_discount >= 0 && $percentage_discount <= 100) {
        $sale_price = round($product['price'] * (1 - $percentage_discount / 100), 2);
    } else {
        $percentage_discount = null; 
    }


    
    // Costruzione query di aggiornamento
    $updateFields = [];
    $params = [];
    
    if ($sale_price !== null) {
        $updateFields[] = "sale_price = ?";
        $params[] = $sale_price;
    }
    if ($discount_type !== null) {
         $updateFields[] = "discount_type = ?";
         $params[] = $discount_type;
    }
        $updateFields[] = "percentage_discount = ?"; $params[] = $percentage_discount;
    
    if ($sale_start_date !== null) {
        $updateFields[] = "sale_start_date = ?";
        $params[] = $sale_start_date;
    }
    if ($sale_end_date !== null) {
        $updateFields[] = "sale_end_date = ?";
        $params[] = $sale_end_date;
    }
    if ($is_on_sale !== null) {
        $updateFields[] = "is_on_sale = ?";
        $params[] = $is_on_sale;
    }
    if ($is_featured !== null) {
        $updateFields[] = "is_featured = ?";
        $params[] = in_array($is_featured, ['1', 1, true, 'true'], true) ? 1 : 0;
    }

    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'message' => 'Nessun campo da aggiornare']);
        exit;
    }
    
    // Aggiungi ID alla fine dei parametri
    $params[] = $id;
    $params[] = $pharma_id;
    
    $sql = "UPDATE jta_pharma_prods SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ? AND pharma_id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        // Log dell'attività
        $action_details = [
            'promotion_id' => $id,
            'updated_fields' => array_keys(array_filter([
                'sale_price' => $sale_price !== null,
                'discount_type' => $discount_type !== null,
                'percentage_discount' => $percentage_discount !== null,
                'sale_start_date' => $sale_start_date !== null,
                'sale_end_date' => $sale_end_date !== null,
                'is_on_sale' => $is_on_sale !== null,
                'is_featured' => $is_featured !== null, 
            ]))
        ];
        
        logActivity('promotion_updated', $action_details);
        
        echo json_encode([
            'success' => true,
            'message' => 'Promozione aggiornata con successo'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Errore durante l\'aggiornamento'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Errore API update promozioni: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Errore fatale API update promozioni: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore fatale del server'
    ]);
}
?> 