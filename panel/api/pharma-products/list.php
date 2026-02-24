<?php
/**
 * API Lista Prodotti Farmacia
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

header('Content-Type: application/json');

function normalizeTagsForResponse($rawTags): array {
    if ($rawTags === null) {
        return [];
    }

    $tags = [];
    if (is_string($rawTags)) {
        $rawTags = trim($rawTags);
        if ($rawTags === '') {
            return [];
        }

        $decoded = json_decode($rawTags, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $tags = $decoded;
        } else {
            $tags = explode(',', $rawTags);
        }
    } elseif (is_array($rawTags)) {
        $tags = $rawTags;
    } else {
        $tags = [$rawTags];
    }

    $normalized = [];
    foreach ($tags as $tag) {
        if (is_array($tag) || is_object($tag)) {
            continue;
        }

        $value = strtolower(trim((string)$tag));
        if ($value === '') {
            continue;
        }

        $normalized[$value] = true;
    }

    return array_keys($normalized);
}

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
    $show_only_promotions = $_GET['show_only_promotions'] ?? false;
    
    // Filtro per mostrare solo prodotti con promozioni (se richiesto)
    if ($show_only_promotions) {
        $sql .= " AND pp.is_on_sale IS NOT NULL";
    }
    
    // Costruisci la query base con JOIN per ottenere dati del prodotto globale
    // Mostra tutti i prodotti della farmacia
    $sql = "SELECT pp.*, gp.category, gp.brand, gp.active_ingredient, gp.dosage_form, gp.strength, gp.package_size, gp.image as global_image 
            FROM jta_pharma_prods pp 
            LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
            WHERE pp.pharma_id = ?";
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
    $sql .= " ORDER BY pp.name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Esegui query
    $products = db_fetch_all($sql, $params);
    

    
    // Gestisci immagini per ogni prodotto
    foreach ($products as &$product) {
        // Se il prodotto farmacia ha un'immagine personalizzata, usa quella
        if (!empty($product['image'])) {
            $product['image'] = $product['image'];
        }
        // Altrimenti usa l'immagine del prodotto globale
        elseif (!empty($product['global_image'])) {
            $product['image'] = $product['global_image'];
        }
        // Altrimenti nessuna immagine
        else {
            $product['image'] = null;
        }
        
        // Rimuovi il campo global_image per evitare confusione
        unset($product['global_image']);

        // Normalizza tags in array
        $product['id'] = (int)($product['id'] ?? 0);
        $product['pharma_id'] = (int)($product['pharma_id'] ?? 0);
        $product['product_id'] = array_key_exists('product_id', $product) && $product['product_id'] !== null
            ? (int)$product['product_id']
            : null;
        $product['name'] = (string)($product['name'] ?? '');
        $product['sku'] = isset($product['sku']) ? (string)$product['sku'] : null;
        $product['price'] = isset($product['price']) ? (float)$product['price'] : null;
        $product['sale_price'] = isset($product['sale_price']) && $product['sale_price'] !== null ? (float)$product['sale_price'] : null;
        $product['image'] = isset($product['image']) && $product['image'] !== '' ? $product['image'] : null;
        $product['is_active'] = (int)($product['is_active'] ?? 0);
        $product['is_featured'] = (int)($product['is_featured'] ?? 0);
        $product['tags'] = normalizeTagsForResponse($product['tags'] ?? null);
    }
    
    // Calcola paginazione
    $totalPages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => intval($total),
        'current_page' => $page,
        'total_pages' => $totalPages,
        'per_page' => $limit,
        'filters' => [
            'search' => $search,
            'category' => $category,
            'brand' => $brand,
            'promotion_status' => $promotion_status,
            'discount_range' => $discount_range,
            'show_only_promotions' => $show_only_promotions
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
