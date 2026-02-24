<?php
/**
 * API Import Prodotti
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

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
    
    // Verifica se è stato caricato un file
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nessun file caricato o errore nel caricamento'
        ]);
        exit;
    }
    
    $file = $_FILES['file'];
    $updateExisting = isset($_POST['update_existing']) && $_POST['update_existing'] == '1';
    
    // Verifica estensione file
    $allowedExtensions = ['csv'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Solo file CSV sono supportati'
        ]);
        exit;
    }
    
    // Verifica dimensione file (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'File troppo grande. Massimo 10MB.'
        ]);
        exit;
    }
    
    // Leggi file CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore nella lettura del file'
        ]);
        exit;
    }
    
    // Salta intestazioni
    $headers = fgetcsv($handle, 0, ';');
    if (!$headers) {
        fclose($handle);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'File CSV non valido'
        ]);
        exit;
    }
    
    // Mappa colonne
    $columnMap = [
        'sku' => array_search('SKU', $headers),
        'name' => array_search('Nome', $headers),
        'description' => array_search('Descrizione', $headers),
        'category' => array_search('Categoria', $headers),
        'brand' => array_search('Brand', $headers),
        'active_ingredient' => array_search('Principio Attivo', $headers),
        'dosage_form' => array_search('Forma Farmaceutica', $headers),
        'strength' => array_search('Dosaggio', $headers),
        'package_size' => array_search('Confezione', $headers),
        'requires_prescription' => array_search('Richiede Ricetta', $headers),
        'is_active' => array_search('Stato', $headers)
    ];
    
    // Verifica colonne obbligatorie
    if ($columnMap['sku'] === false || $columnMap['name'] === false) {
        fclose($handle);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Colonne SKU e Nome sono obbligatorie'
        ]);
        exit;
    }
    
    $imported = 0;
    $updated = 0;
    $errors = [];
    $rowNumber = 1; // Inizia da 1 perché abbiamo saltato le intestazioni
    
    // Inizia transazione
    db()->getConnection()->beginTransaction();
    
    try {
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;
            
            // Salta righe vuote
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Estrai dati
            $sku = trim($row[$columnMap['sku']] ?? '');
            $name = trim($row[$columnMap['name']] ?? '');
            
            if (empty($sku) || empty($name)) {
                $errors[] = "Riga {$rowNumber}: SKU e Nome sono obbligatori";
                continue;
            }
            
            // Prepara dati
            $data = [
                'sku' => $sku,
                'name' => $name,
                'description' => trim($row[$columnMap['description']] ?? ''),
                'category' => trim($row[$columnMap['category']] ?? ''),
                'brand' => trim($row[$columnMap['brand']] ?? ''),
                'active_ingredient' => trim($row[$columnMap['active_ingredient']] ?? ''),
                'dosage_form' => trim($row[$columnMap['dosage_form']] ?? ''),
                'strength' => trim($row[$columnMap['strength']] ?? ''),
                'package_size' => trim($row[$columnMap['package_size']] ?? ''),
                'requires_prescription' => strtolower(trim($row[$columnMap['requires_prescription']] ?? '')) === 'sì' ? 1 : 0,
                'is_active' => strtolower(trim($row[$columnMap['is_active']] ?? '')) === 'attivo' ? 'active' : 'inactive'
            ];
            
            // Verifica se prodotto esiste (solo prodotti attivi)
            $existingProduct = db_fetch_one("SELECT id FROM jta_global_prods WHERE sku = ? AND is_active = 'active'", [$sku]);
            
            if ($existingProduct) {
                if ($updateExisting) {
                    // Aggiorna prodotto esistente
                    db()->update('jta_global_prods', $data, 'id = ?', [$existingProduct['id']]);
                    $updated++;
                } else {
                    $errors[] = "Riga {$rowNumber}: SKU '{$sku}' già esistente";
                }
            } else {
                // Inserisci nuovo prodotto
                db()->insert('jta_global_prods', $data);
                $imported++;
            }
        }
        
        // Commit transazione
        db()->getConnection()->commit();
        
        fclose($handle);
        
        // Log attività
        logActivity('products_imported', [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => count($errors),
            'filename' => $file['name']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Import completato",
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        // Rollback in caso di errore
        db()->getConnection()->rollBack();
        fclose($handle);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore durante l\'import: ' . $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage()
    ]);
}

?> 