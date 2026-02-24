<?php
/**
 * API Creazione Promozione
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
    
    // Leggi dati dal form
    $product_id = intval($_POST['product_id'] ?? 0);
    $sale_price = isset($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $discount_type = $_POST['discount_type'] ?? 'amount';
    $percentage_discount = isset($_POST['percentage_discount']) ? floatval($_POST['percentage_discount']) : null;
    $sale_start_date = !empty($_POST['sale_start_date']) ? $_POST['sale_start_date'] : null;
    $sale_end_date = !empty($_POST['sale_end_date']) ? $_POST['sale_end_date'] : null;
    $is_on_sale = (!empty($_POST['is_on_sale']) && $_POST['is_on_sale'] == '1') ? 1 : 0;
    $is_featured = (!empty($_POST['is_featured']) && $_POST['is_featured'] == '1') ? 1 : 0;

    
    // Validazione
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Prodotto non selezionato']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, name, price FROM jta_pharma_prods WHERE id = ? AND pharma_id = ?");
    $stmt->execute([$product_id, $pharma_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Prodotto non trovato nella tua farmacia']);
        exit;
    }

    if ($discount_type === 'percentage' && $percentage_discount !== null && $percentage_discount >= 0 && $percentage_discount <= 100) {
        $sale_price = round($product['price'] * (1 - $percentage_discount / 100), 2);
    } else {
        $percentage_discount = null; 
    }

    if ( $sale_price === NULL || $sale_price < 0) {
        echo json_encode(['success' => false, 'message' => 'Prezzo scontato obbligatorio e deve essere maggiore di zero']);
        exit;
    }
    
    // Verifica che il prezzo scontato sia inferiore al prezzo originale
    if ($sale_price > $product['price']) {
        echo json_encode(['success' => false, 'message' => 'Il prezzo scontato deve essere inferiore o uguale al prezzo originale']);
        exit;
    }
    
    if (!$sale_start_date || !$sale_end_date) {
        echo json_encode(['success' => false, 'message' => 'Date inizio e fine promozione obbligatorie']);
        exit;
    }
    
    if ($sale_start_date >= $sale_end_date) {
        echo json_encode(['success' => false, 'message' => 'La data di fine deve essere successiva alla data di inizio']);
        exit;
    }
    
    // Aggiorna il prodotto con i dati della promozione
    $stmt = $pdo->prepare("UPDATE jta_pharma_prods SET 
        sale_price = ?, 
        discount_type = ?, 
        percentage_discount = ?, 
        sale_start_date = ?, 
        sale_end_date = ?, 
        is_on_sale = ?, 
        is_featured = ?
        WHERE id = ? AND pharma_id = ?");
    
    $result = $stmt->execute([
        $sale_price,
        $discount_type,
        $percentage_discount,
        $sale_start_date,
        $sale_end_date,
        $is_on_sale,
        $is_featured,
        $product_id,
        $pharma_id
    ]);
    
    if ($result) {
        // Log dell'attività
        logActivity('promotion_created', [
            'product_id' => $product_id,
            'pharma_id' => $pharma_id,
            'product_name' => $product['name'],
            'sale_price' => $sale_price,
            'original_price' => $product['price'],
            'discount_type' => $discount_type,
            'percentage_discount' => $percentage_discount,
            'sale_start_date' => $sale_start_date,
            'sale_end_date' => $sale_end_date,
            'is_on_sale' => $is_on_sale,
            'is_featured' => $is_featured
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Promozione creata con successo!',
            'product_id' => $product_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiornamento della promozione']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 