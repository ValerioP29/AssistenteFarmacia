<?php
/**
 * API Import Prodotti Farmacia
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
    
    // Verifica metodo
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
    $updateExisting = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
    
    // Verifica tipo file
    $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Tipo di file non supportato. Utilizza CSV o Excel.'
        ]);
        exit;
    }
    
    // Verifica dimensione file (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'File troppo grande. Dimensione massima: 5MB'
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
    
    // Salta header
    $headers = fgetcsv($handle);
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
        'price' => array_search('Prezzo', $headers),
        'sale_price' => array_search('Prezzo Scontato', $headers),
        'is_active' => array_search('Stato (1=Attivo, 0=Inattivo)', $headers),

    ];
    
    // Verifica colonne obbligatorie
    if ($columnMap['name'] === false) {
        fclose($handle);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Colonna "Nome" mancante nel file'
        ]);
        exit;
    }
    
    $imported = 0;
    $updated = 0;
    $errors = [];
    
    // Inizia transazione
    db()->beginTransaction();
    
    try {
        $rowNumber = 1; // Per tracciare errori
        
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            // Salta righe vuote
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Estrai dati
            $data = [
                'pharma_id' => $pharmacyId,
                'name' => trim($row[$columnMap['name']] ?? ''),
                'sku' => trim($row[$columnMap['sku']] ?? ''),
                'description' => trim($row[$columnMap['description']] ?? ''),
                'price' => floatval($row[$columnMap['price']] ?? 0),
                'sale_price' => !empty($row[$columnMap['sale_price']]) ? floatval($row[$columnMap['sale_price']]) : null,
                'is_active' => intval($row[$columnMap['is_active']] ?? 1),

            ];
            
            // Validazione
            if (empty($data['name'])) {
                $errors[] = "Riga {$rowNumber}: Nome prodotto obbligatorio";
                continue;
            }
            
            if ($data['price'] <= 0) {
                $errors[] = "Riga {$rowNumber}: Prezzo deve essere maggiore di zero";
                continue;
            }
            

            
            // Verifica se prodotto esiste (per aggiornamento, solo prodotti attivi)
            $existingProduct = null;
            if (!empty($data['sku'])) {
                $existingProduct = db_fetch_one(
                    "SELECT pp.id FROM jta_pharma_prods pp 
                     WHERE pp.sku = ? AND pp.pharma_id = ? AND pp.is_active = 1", 
                    [$data['sku'], $pharmacyId]
                );
            }
            
            if ($existingProduct && $updateExisting) {
                // Aggiornamento
                $affected = db()->update('jta_pharma_prods', $data, 'id = ?', [$existingProduct['id']]);
                if ($affected > 0) {
                    $updated++;
                }
            } elseif (!$existingProduct) {
                // Inserimento nuovo
                $productId = db()->insert('jta_pharma_prods', $data);
                if ($productId) {
                    $imported++;
                }
            } else {
                $errors[] = "Riga {$rowNumber}: SKU già esistente e aggiornamento non abilitato";
            }
        }
        
        fclose($handle);
        
        // Commit transazione
        db()->commit();
        
        // Log attività
        logActivity('products_imported', [
            'pharma_id' => $pharmacyId,
            'imported' => $imported,
            'updated' => $updated,
            'errors' => count($errors)
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Import completato: {$imported} importati, {$updated} aggiornati",
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        // Rollback in caso di errore
        db()->rollback();
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