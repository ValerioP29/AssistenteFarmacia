<?php
/**
 * API Ricerca Prodotti per Autocompletamento
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/functions.php';

// Verifica autenticazione API e accesso farmacista
requireApiAuth(['admin', 'pharmacist']);

header('Content-Type: application/json');

try {
    $search = $_GET['q'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 10), 20); // Massimo 20 risultati
    
    if (empty($search) || strlen($search) < 2) {
        echo json_encode([
            'success' => true,
            'products' => []
        ]);
        exit;
    }
    
    // Ottieni farmacia corrente
    $pharmacy = getCurrentPharmacy();
    $pharmacyId = $pharmacy['id'] ?? 0;
    
    if (!$pharmacyId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Farmacia non trovata'
        ]);
        exit;
    }
    
    // Costruisci la query base con JOIN per ottenere dati del prodotto globale
    $sql = "SELECT pp.*, gp.category, gp.brand, gp.active_ingredient, gp.dosage_form, gp.strength, gp.package_size, gp.image as global_image 
            FROM jta_pharma_prods pp 
            LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
            WHERE pp.pharma_id = ? AND pp.is_active = 1";
    $params = [$pharmacyId];
    
    // Aggiungi ricerca
    $searchParam = "%{$search}%";
    $sql .= " AND (pp.sku LIKE ? OR pp.name LIKE ? OR pp.description LIKE ? OR gp.active_ingredient LIKE ? OR gp.brand LIKE ? OR gp.category LIKE ?)";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    
    // Aggiungi ordinamento e limite
    $sql .= " ORDER BY pp.name ASC LIMIT ?";
    $params[] = $limit;
    
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
    }
    
    // Formatta i risultati per l'autocompletamento
    $formattedProducts = array_map(function($product) {
        return [
            'id' => $product['id'],
            'text' => $product['name'] . ' - ' . ($product['brand'] ?? 'N/A') . ' (' . ($product['category'] ?? 'N/A') . ')',
            'name' => $product['name'],
            'description' => $product['description'],
            'brand' => $product['brand'] ?? 'N/A',
            'category' => $product['category'] ?? 'N/A',
            'price' => $product['price'],
            'image' => $product['image'],
            'sku' => $product['sku'],
            'active_ingredient' => $product['active_ingredient'] ?? '',
            'dosage_form' => $product['dosage_form'] ?? '',
            'strength' => $product['strength'] ?? '',
            'package_size' => $product['package_size'] ?? ''
        ];
    }, $products);
    
    echo json_encode([
        'success' => true,
        'products' => $formattedProducts
    ]);
    
} catch (Exception $e) {
    error_log("Errore API ricerca prodotti: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
} catch (Error $e) {
    error_log("Errore fatale API ricerca prodotti: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
} 