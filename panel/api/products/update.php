<?php
/**
 * API Aggiorna Prodotto
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/image_manager.php';
require_once '../../includes/product_approval_manager.php';

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
    $id = intval($_POST['id'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    
    if ($id <= 0 || empty($sku) || empty($name)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID, SKU e Nome sono campi obbligatori'
        ]);
        exit;
    }
    
    // Verifica se prodotto esiste
    $existingProduct = db_fetch_one("SELECT * FROM jta_global_prods WHERE id = ?", [$id]);
    if (!$existingProduct) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Prodotto non trovato'
        ]);
        exit;
    }
    
    // Verifica permessi directory upload
    $uploadDir = __DIR__ . '/../../uploads/products/';
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        error_log("Upload directory not writable: $uploadDir");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore configurazione server: directory upload non accessibile'
        ]);
        exit;
    }
    
    // Verifica se SKU già esiste (solo prodotti attivi, escludendo il prodotto corrente)
    $existingSku = db_fetch_one("SELECT id FROM jta_global_prods WHERE sku = ? AND id != ? AND is_active = 'active'", [$sku, $id]);
    if ($existingSku) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'SKU già esistente'
        ]);
        exit;
    }
    
    // Gestione upload immagine
    $imagePath = $existingProduct['image']; // Mantieni immagine esistente
    $category = trim($_POST['category'] ?? $existingProduct['category'] ?? 'generico');
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        try {
            // Debug: log informazioni sul file
            error_log("Uploading image: " . json_encode($_FILES['image']));
            
            // Elimina immagine precedente se esiste
            if (!empty($existingProduct['image'])) {
                deleteProductImage($existingProduct['image']);
            }
            
            // Processa nuova immagine
            $imagePath = processProductImage($_FILES['image'], $category);
            
            // Debug: log percorso immagine generato
            error_log("New image path: $imagePath");
            
        } catch (Exception $e) {
            error_log("Image upload error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Errore nel caricamento dell\'immagine: ' . $e->getMessage()
            ]);
            exit;
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Se c'è un errore nell'upload (diverso da "nessun file")
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite PHP)',
            UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
            UPLOAD_ERR_PARTIAL => 'Upload parziale',
            UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
            UPLOAD_ERR_CANT_WRITE => 'Errore scrittura su disco',
            UPLOAD_ERR_EXTENSION => 'Estensione PHP bloccata'
        ];
        
        $errorMessage = $uploadErrors[$_FILES['image']['error']] ?? 'Errore sconosciuto nell\'upload';
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nell\'upload dell\'immagine: ' . $errorMessage
        ]);
        exit;
    } elseif (empty($existingProduct['image'])) {
        // Genera immagine placeholder se non c'è un'immagine esistente
        try {
            $imagePath = generateProductPlaceholder($name, $category, $sku);
            error_log("Generated placeholder image: $imagePath");
        } catch (Exception $e) {
            error_log("Placeholder generation error: " . $e->getMessage());
            // Non bloccare l'aggiornamento se fallisce la generazione del placeholder
        }
    }
    
    // Gestisci stato del prodotto
    $newStatus = isset($_POST['is_active']) ? 'active' : 'inactive';
    $oldStatus = $existingProduct['is_active'];
    
    // Prepara dati per aggiornamento
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
        'is_active' => $newStatus
    ];
    
    // Verifica se ci sono effettivamente modifiche
    $hasChanges = false;
    foreach ($data as $key => $value) {
        if ($existingProduct[$key] != $value) {
            $hasChanges = true;
            break;
        }
    }
    
    // Se non ci sono modifiche, restituisci successo comunque
    if (!$hasChanges) {
        echo json_encode([
            'success' => true,
            'message' => 'Prodotto aggiornato (nessuna modifica necessaria)'
        ]);
        exit;
    }
    
    // Aggiorna nel database
    $affected = db()->update('jta_global_prods', $data, 'id = ?', [$id]);
    
    if ($affected === 0) {
        // Log per debug
        error_log("Update failed for product ID: $id");
        error_log("Data: " . json_encode($data));
        error_log("Existing: " . json_encode($existingProduct));
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nell\'aggiornamento del prodotto'
        ]);
        exit;
    }
    
    // Gestisci attivazione/disattivazione automatica prodotti farmacia
    if ($oldStatus === 'pending_approval' && $newStatus === 'active') {
        // Prodotto approvato: attiva automaticamente i prodotti farmacia collegati
        try {
            if (function_exists('activatePharmaProductsForGlobalProduct')) {
                $activationResult = activatePharmaProductsForGlobalProduct($id);
                $message = $activationResult ? 
                    'Prodotto approvato e prodotti farmacia collegati attivati automaticamente' : 
                    'Prodotto approvato (errore nell\'attivazione automatica prodotti farmacia)';
            } else {
                $message = 'Prodotto approvato (funzione attivazione automatica non disponibile)';
            }
        } catch (Exception $e) {
            error_log("Error activating pharma products: " . $e->getMessage());
            $message = 'Prodotto approvato (errore nell\'attivazione automatica prodotti farmacia)';
        }
    } elseif ($oldStatus === 'active' && $newStatus === 'inactive') {
        // Prodotto disattivato: disattiva automaticamente i prodotti farmacia collegati
        try {
            if (function_exists('deactivatePharmaProductsForGlobalProduct')) {
                $deactivationResult = deactivatePharmaProductsForGlobalProduct($id);
                $message = $deactivationResult ? 
                    'Prodotto disattivato e prodotti farmacia collegati disattivati automaticamente' : 
                    'Prodotto disattivato (errore nella disattivazione automatica prodotti farmacia)';
            } else {
                $message = 'Prodotto disattivato (funzione disattivazione automatica non disponibile)';
            }
        } catch (Exception $e) {
            error_log("Error deactivating pharma products: " . $e->getMessage());
            $message = 'Prodotto disattivato (errore nella disattivazione automatica prodotti farmacia)';
        }
    } else {
        $message = 'Prodotto aggiornato con successo';
    }
    
    // Log attività
    try {
        if (function_exists('logActivity')) {
            logActivity('product_updated', [
                'product_id' => $id,
                'sku' => $sku,
                'name' => $name,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
        }
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        // Non bloccare l'operazione se il log fallisce
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}

?> 