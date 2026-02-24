<?php
/**
 * API Lista Promozioni Farmacia
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
    
    // Parametri di paginazione e filtri
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $brand = trim($_GET['brand'] ?? '');
    $status = $_GET['status'] ?? '';
    
    // Filtri specifici per promozioni
    $promotion_status = $_GET['promotion_status'] ?? '';
    $discount_range = $_GET['discount_range'] ?? '';
    
    // Costruisci la query base con JOIN per ottenere dati del prodotto globale
    // Mostra SOLO prodotti con promozioni (is_on_sale IS NOT NULL)
    $sql = "SELECT pp.*, gp.category, gp.brand, gp.active_ingredient, gp.dosage_form, gp.strength, gp.package_size, gp.image as global_image 
            FROM jta_pharma_prods pp 
            LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
            WHERE pp.pharma_id = ? AND pp.is_on_sale IS NOT NULL";
    $params = [$pharmacyId];
    
    // Aggiungi filtri
    if ($search) {
        $sql .= " AND (pp.sku LIKE ? OR pp.name LIKE ? OR pp.description LIKE ? OR gp.active_ingredient LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($category) {
        $sql .= " AND gp.category = ?";
        $params[] = $category;
    }
    
    if ($brand) {
        $sql .= " AND gp.brand = ?";
        $params[] = $brand;
    }
    
    if ($status !== '') {
        $sql .= " AND pp.is_active = ?";
        $params[] = intval($status);
    }
    
    // Filtri per promozioni
    if ($promotion_status) {
        switch ($promotion_status) {
            case 'active':
                $sql .= " AND pp.is_on_sale = 1 AND pp.sale_start_date <= CURDATE() AND pp.sale_end_date >= CURDATE()";
                break;
            case 'inactive':
                $sql .= " AND pp.is_on_sale = 0";
                break;
            case 'expired':
                $sql .= " AND pp.is_on_sale = 1 AND pp.sale_end_date < CURDATE()";
                break;
        }
    }
    
    // Filtro per range di sconto
    if ($discount_range) {
        switch ($discount_range) {
            case '0-10':
                $sql .= " AND pp.sale_price > 0 AND ((pp.price - pp.sale_price) / pp.price) * 100 BETWEEN 0 AND 10";
                break;
            case '10-25':
                $sql .= " AND pp.sale_price > 0 AND ((pp.price - pp.sale_price) / pp.price) * 100 BETWEEN 10 AND 25";
                break;
            case '25-50':
                $sql .= " AND pp.sale_price > 0 AND ((pp.price - pp.sale_price) / pp.price) * 100 BETWEEN 25 AND 50";
                break;
            case '50+':
                $sql .= " AND pp.sale_price > 0 AND ((pp.price - pp.sale_price) / pp.price) * 100 > 50";
                break;
        }
    }
    
    // Conta totale record
    $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as subquery";
    $total = db_fetch_one($countSql, $params)['total'];
    
    // Aggiungi ordinamento e paginazione
    $sql .= " ORDER BY 
            pp.is_featured DESC,
            pp.is_on_sale DESC,
            (pp.sale_end_date IS NULL) ASC,
            pp.sale_end_date DESC,
            pp.name ASC
          LIMIT ? OFFSET ?";    $params[] = $limit;
    $params[] = $offset;
    
    // Esegui query
    $promotions = db_fetch_all($sql, $params);
    
    // Gestisci immagini per ogni promozione
    foreach ($promotions as &$promotion) {
        // Se il prodotto farmacia ha un'immagine personalizzata, usa quella
        if (!empty($promotion['image'])) {
            $promotion['image'] = $promotion['image'];
        }
        // Altrimenti usa l'immagine del prodotto globale
        elseif (!empty($promotion['global_image'])) {
            $promotion['image'] = $promotion['global_image'];
        }
        // Altrimenti nessuna immagine
        else {
            $promotion['image'] = null;
        }
        
        // Rimuovi il campo global_image per evitare confusione
        unset($promotion['global_image']);
    }
    
    // Calcola paginazione
    $totalPages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'promotions' => $promotions,
        'total' => intval($total),
        'current_page' => $page,
        'total_pages' => $totalPages,
        'per_page' => $limit,
        'filters' => [
            'search' => $search,
            'category' => $category,
            'brand' => $brand,
            'promotion_status' => $promotion_status,
            'discount_range' => $discount_range
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}
?> 