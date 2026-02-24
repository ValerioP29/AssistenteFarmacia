<?php
/**
 * API Ricerca Prodotti Farmacia per Promozioni
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
    
    if (!$pharma_id) {
        throw new Exception('ID farmacia non valido');
    }
    
    // Parametri di ricerca
    $search = trim($_GET['q'] ?? '');
    $limit = intval($_GET['limit'] ?? 10);
    
    if (strlen($search) < 2) {
        echo json_encode([
            'success' => true,
            'products' => []
        ]);
        exit;
    }
    
    // Cerca prodotti nella farmacia (solo prodotti che non hanno mai avuto promozioni)
    $sql = "SELECT pp.id, pp.name, pp.description, pp.price, pp.sku, 
                   COALESCE(pp.image, gp.image) as image,
                   gp.brand, gp.category, gp.active_ingredient
            FROM jta_pharma_prods pp
            LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id
            WHERE pp.pharma_id = ? 
            AND pp.is_on_sale IS NULL
            AND (pp.name LIKE ? OR pp.description LIKE ? OR pp.sku LIKE ? 
                 OR gp.brand LIKE ? OR gp.category LIKE ? OR gp.active_ingredient LIKE ?)
            ORDER BY pp.name ASC
            LIMIT ?";
    
    $searchTerm = "%{$search}%";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $pharma_id,
        $searchTerm, $searchTerm, $searchTerm,
        $searchTerm, $searchTerm, $searchTerm,
        $limit
    ]);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatta i risultati
    $formattedProducts = array_map(function($product) {
        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'] ?? '',
            'price' => $product['price'],
            'sku' => $product['sku'],
            'image' => $product['image'] ?? null,
            'brand' => $product['brand'] ?? 'N/A',
            'category' => $product['category'] ?? 'N/A',
            'active_ingredient' => $product['active_ingredient'] ?? 'N/A'
        ];
    }, $products);
    
    echo json_encode([
        'success' => true,
        'products' => $formattedProducts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 