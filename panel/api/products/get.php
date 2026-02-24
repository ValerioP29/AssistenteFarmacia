<?php
/**
 * API Get Prodotto Singolo
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/functions.php';

// Verifica autenticazione API e accesso farmacista
requireApiAuth(['admin', 'pharmacist']);

header('Content-Type: application/json');

try {
    $product_id = $_GET['id'] ?? null;
    
    if (!$product_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID prodotto richiesto'
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
    
    // Determina se è un prodotto globale o farmacia
    $isGlobalProduct = false;
    
    // Prima prova a cercare come prodotto globale (per admin)
    if (isAdmin()) {
        $globalProduct = db_fetch_one("SELECT * FROM jta_global_prods WHERE id = ?", [$product_id]);
        if ($globalProduct) {
            $isGlobalProduct = true;
            $product = $globalProduct;
        }
    }
    
    // Se non è un prodotto globale o non è admin, cerca come prodotto farmacia
    if (!$isGlobalProduct) {
        // Costruisci la query base con JOIN per ottenere dati del prodotto globale
        $sql = "SELECT pp.*, gp.category, gp.brand, gp.active_ingredient, gp.dosage_form, gp.strength, gp.package_size, gp.image as global_image 
                FROM jta_pharma_prods pp 
                LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
                WHERE pp.id = ? AND pp.pharma_id = ? AND pp.is_active = 1";
        $params = [$product_id, $pharmacyId];
        
        // Esegui query
        $product = db_fetch_one($sql, $params);
        
        if (!$product) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Prodotto non trovato'
            ]);
            exit;
        }
        
        // Gestisci immagine per prodotti farmacia
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
    }
    
    // Se non è stato trovato nessun prodotto
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Prodotto non trovato'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
    
} catch (Exception $e) {
    error_log("Errore API get prodotto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
} catch (Error $e) {
    error_log("Errore fatale API get prodotto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
} 