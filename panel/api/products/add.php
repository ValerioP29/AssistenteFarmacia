<?php
/**
 * API Aggiungi Prodotto
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/image_manager.php';

// Verifica accesso admin per API
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Autenticazione richiesta'
    ]);
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Accesso negato - Solo admin'
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Metodo non consentito'
        ]);
        exit;
    }
    
    // Validazione campi obbligatori
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    
    if (empty($sku) || empty($name)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'SKU e Nome sono campi obbligatori'
        ]);
        exit;
    }
    
    // Verifica se SKU già esiste (solo prodotti attivi)
    $existingSku = db_fetch_one("SELECT id FROM jta_global_prods WHERE sku = ? AND is_active = 'active'", [$sku]);
    if ($existingSku) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'SKU già esistente'
        ]);
        exit;
    }
    
    // Gestione upload immagine
    $imagePath = null;
    $category = trim($_POST['category'] ?? 'generico');
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        try {
            $imagePath = processProductImage($_FILES['image'], $category);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Errore nel caricamento dell\'immagine: ' . $e->getMessage()
            ]);
            exit;
        }
    } else {
        // Genera immagine placeholder se non è stata caricata un'immagine
        // Controlla se GD è disponibile
        if (!extension_loaded('gd')) {
            $imagePath = null; // Non generare immagine se GD non è disponibile
        } else {
            try {
                $imagePath = generateProductPlaceholder($name, $category, $sku);
            } catch (Exception $e) {
                $imagePath = null; // Non bloccare se la generazione fallisce
            }
        }
    }
    
    // Prepara dati per inserimento
    $data = [
        'sku' => $sku,
        'name' => $name,
        'description' => trim($_POST['description'] ?? ''),
        'image' => $imagePath,
        'category' => trim($_POST['category'] ?? ''),
        'brand' => trim($_POST['brand'] ?? ''),
        'active_ingredient' => trim($_POST['active_ingredient'] ?? ''),
        'dosage_form' => trim($_POST['dosage_form'] ?? ''),
        'strength' => trim($_POST['strength'] ?? ''),
        'package_size' => trim($_POST['package_size'] ?? ''),
        'requires_prescription' => isset($_POST['requires_prescription']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 'active' : 'inactive'
    ];
    
    // Inserisci nel database
    $productId = db()->insert('jta_global_prods', $data);
    
    // Log attività
    try {
        logActivity('product_added', [
            'product_id' => $productId,
            'sku' => $sku,
            'name' => $name
        ]);
    } catch (Exception $e) {
        // Non bloccare se il log fallisce
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Prodotto creato con successo',
        'product_id' => $productId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}

?> 