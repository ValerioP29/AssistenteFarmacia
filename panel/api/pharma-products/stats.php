<?php
/**
 * API Statistiche Promozioni
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Ottieni farmacia corrente
    $pharmacy = getCurrentPharmacy();
    $pharma_id = $pharmacy['id'] ?? $_SESSION['pharmacy_id'] ?? null;
    
    // Verifica che pharma_id sia valido
    if (!$pharma_id) {
        throw new Exception('ID farmacia non valido');
    }
    
    // Promozioni attive (is_on_sale = 1 e data corrente tra inizio e fine)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM jta_pharma_prods 
        WHERE pharma_id = ? 
        AND is_on_sale = 1 
        AND sale_start_date <= CURDATE() 
        AND sale_end_date >= CURDATE()
    ");
    $stmt->execute([$pharma_id]);
    $active_promotions = $stmt->fetchColumn();
    
    // Promozioni inattive (is_on_sale = 0)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM jta_pharma_prods 
        WHERE pharma_id = ? 
        AND is_on_sale = 0
    ");
    $stmt->execute([$pharma_id]);
    $inactive_promotions = $stmt->fetchColumn();
    
    // Promozioni scadute (is_on_sale = 1 ma data fine passata)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM jta_pharma_prods 
        WHERE pharma_id = ? 
        AND is_on_sale = 1 
        AND sale_end_date < CURDATE()
    ");
    $stmt->execute([$pharma_id]);
    $expired_promotions = $stmt->fetchColumn();
    
    // Sconto medio delle promozioni attive
    $stmt = $pdo->prepare("
        SELECT AVG(
            CASE 
                WHEN price > 0 AND sale_price > 0 
                THEN ((price - sale_price) / price) * 100 
                ELSE 0 
            END
        ) as avg_discount
        FROM jta_pharma_prods 
        WHERE pharma_id = ? 
        AND is_on_sale = 1 
        AND sale_start_date <= CURDATE() 
        AND sale_end_date >= CURDATE()
        AND price > 0 
        AND sale_price > 0
    ");
    $stmt->execute([$pharma_id]);
    $average_discount = round($stmt->fetchColumn(), 1);
    
    echo json_encode([
        'success' => true,
        'active_promotions' => (int)$active_promotions,
        'inactive_promotions' => (int)$inactive_promotions,
        'expired_promotions' => (int)$expired_promotions,
        'average_discount' => (float)$average_discount
    ]);
    
} catch (Exception $e) {
    error_log("Errore API stats promozioni: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Errore fatale API stats promozioni: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore fatale del server'
    ]);
}
?> 