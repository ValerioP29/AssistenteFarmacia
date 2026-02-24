<?php
/**
 * API Aggiungi/Modifica Prodotto Farmacia
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/image_manager.php';

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
    
    // Leggi dati dal form
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $productId = intval($_POST['product_id'] ?? 0); // ID del prodotto globale associato
    $createGlobalProduct = isset($_POST['create_global_product']) && $_POST['create_global_product'] === '1';
    
    // Gestione immagine
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $category = $_POST['global_category'] ?? 'generico';
        $imagePath = processProductImage($_FILES['image'], $category);
        if (!$imagePath) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Errore nel caricamento dell\'immagine'
            ]);
            exit;
        }
    }
    
    // Campi promozioni - gestione differenziata per nuovi prodotti e modifiche
    $isOnSale = null; // Default NULL per nuovi prodotti
    $saleStartDate = null;
    $saleEndDate = null;
    
    // Se è un aggiornamento, mantieni i valori esistenti se non specificati
    if ($id > 0) {
        $existingProduct = db_fetch_one("SELECT is_on_sale, sale_start_date, sale_end_date FROM jta_pharma_prods WHERE id = ? AND pharma_id = ?", [$id, $pharmacyId]);
        if ($existingProduct) {
            $isOnSale = isset($_POST['is_on_sale']) ? 1 : ($existingProduct['is_on_sale'] ?? null);
            $saleStartDate = !empty($_POST['sale_start_date']) ? $_POST['sale_start_date'] : $existingProduct['sale_start_date'];
            $saleEndDate = !empty($_POST['sale_end_date']) ? $_POST['sale_end_date'] : $existingProduct['sale_end_date'];
        }
    } else {
        // Per nuovi prodotti, usa i valori dal form se specificati
        $isOnSale = isset($_POST['is_on_sale']) ? 1 : null;
        $saleStartDate = !empty($_POST['sale_start_date']) ? $_POST['sale_start_date'] : null;
        $saleEndDate = !empty($_POST['sale_end_date']) ? $_POST['sale_end_date'] : null;
    }
    
    // Validazione
    if (empty($name)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nome prodotto è obbligatorio'
        ]);
        exit;
    }
    
    if ($price < 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Prezzo deve essere maggiore di zero'
        ]);
        exit;
    }
    

    
    // Se è un aggiornamento, verifica che il prodotto esista e appartenga alla farmacia
    if ($id > 0) {
        $existingProduct = db_fetch_one("SELECT id FROM jta_pharma_prods WHERE id = ? AND pharma_id = ?", [$id, $pharmacyId]);
        if (!$existingProduct) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Prodotto non trovato'
            ]);
            exit;
        }
    } else {
        // Per nuovi prodotti o promozioni
        if (!$createGlobalProduct && $productId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Devi selezionare un prodotto dal catalogo globale o creare un nuovo prodotto nel catalogo'
            ]);
            exit;
        }
        
        // Se stiamo creando una promozione (productId > 0), verifica che il prodotto esista nella farmacia
        if ($productId > 0 && !$createGlobalProduct) {
            $existingPharmaProduct = db_fetch_one("SELECT id FROM jta_pharma_prods WHERE product_id = ? AND pharma_id = ?", [$productId, $pharmacyId]);
            if ($existingPharmaProduct) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Questo prodotto è già presente nella tua farmacia'
                ]);
                exit;
            }
        }
    }
    
    // Se stiamo creando un nuovo prodotto globale
    if ($createGlobalProduct) {
        $globalSku = trim($_POST['global_sku'] ?? '');
        $globalCategory = trim($_POST['global_category'] ?? '');
        $globalBrand = trim($_POST['global_brand'] ?? '');
        $globalActiveIngredient = trim($_POST['global_active_ingredient'] ?? '');
        $globalDosageForm = trim($_POST['global_dosage_form'] ?? '');
        $globalStrength = trim($_POST['global_strength'] ?? '');
        $globalPackageSize = trim($_POST['global_package_size'] ?? '');
        $globalRequiresPrescription = isset($_POST['global_requires_prescription']) ? 1 : 0;
        
        // Validazione SKU globale
        if (empty($globalSku)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'SKU Globale è obbligatorio'
            ]);
            exit;
        }
        
        // Verifica se SKU globale esiste già (solo prodotti attivi)
        $existingGlobalSku = db_fetch_one("SELECT id FROM jta_global_prods WHERE sku = ? AND is_active = 'active'", [$globalSku]);
        if ($existingGlobalSku) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'SKU Globale già esistente nel catalogo'
            ]);
            exit;
        }
        
        // Crea nuovo prodotto globale
        $globalProductData = [
            'sku' => $globalSku,
            'name' => $name,
            'description' => $description,
            'category' => $globalCategory,
            'brand' => $globalBrand,
            'active_ingredient' => $globalActiveIngredient,
            'dosage_form' => $globalDosageForm,
            'strength' => $globalStrength,
            'package_size' => $globalPackageSize,
            'requires_prescription' => $globalRequiresPrescription,
            'is_active' => 'pending_approval' // Stato "Da Approvare"
        ];
        
        $productId = db()->insert('jta_global_prods', $globalProductData);
        
        if (!$productId) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella creazione del prodotto globale'
            ]);
            exit;
        }
        
        // Log attività
        logActivity('global_product_created', [
            'product_id' => $productId,
            'pharma_id' => $pharmacyId,
            'sku' => $globalSku,
            'name' => $name,
            'status' => 'pending_approval'
        ]);
        
        // Crea anche il record nella farmacia con stato inattivo
        $pharmaProductData = [
            'pharma_id' => $pharmacyId,
            'product_id' => $productId, // Associa al prodotto globale appena creato
            'name' => $name,
            'sku' => $sku,
            'description' => $description,
            'price' => $price,
            'sale_price' => $salePrice,
            'is_active' => $isActive, 
            'is_on_sale' => $isOnSale,
            'sale_start_date' => $saleStartDate,
            'sale_end_date' => $saleEndDate
        ];
        
        // Aggiungi immagine se caricata
        if ($imagePath) {
            $pharmaProductData['image'] = $imagePath;
        }
        
        $pharmaProductId = db()->insert('jta_pharma_prods', $pharmaProductData);
        
        if (!$pharmaProductId) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Errore nella creazione del prodotto farmacia'
            ]);
            exit;
        }
        
        // Log attività per prodotto farmacia
        logActivity('pharma_product_created_pending', [
            'pharma_product_id' => $pharmaProductId,
            'global_product_id' => $productId,
            'pharma_id' => $pharmacyId,
            'sku' => $sku,
            'name' => $name,
            'status' => 'pending_approval'
        ]);
        
        // Imposta il productId per la risposta finale
        $productId = $pharmaProductId;
    }
    
    // Verifica se SKU è unico per questa farmacia (solo prodotti attivi, escludendo il prodotto corrente se in modifica)
    if (!empty($sku)) {
        $skuCheckSql = "SELECT pp.id FROM jta_pharma_prods pp 
                        WHERE pp.sku = ? AND pp.pharma_id = ? AND pp.is_active = 1";
        $skuCheckParams = [$sku, $pharmacyId];
        
        if ($id > 0) {
            $skuCheckSql .= " AND pp.id != ?";
            $skuCheckParams[] = $id;
        }
        
        $existingSku = db_fetch_one($skuCheckSql, $skuCheckParams);
        if ($existingSku) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'SKU già esistente per questa farmacia'
            ]);
            exit;
        }
    }
    
    // Prepara dati per inserimento/aggiornamento
    $data = [
        'pharma_id' => $pharmacyId,
        'product_id' => $productId > 0 ? $productId : null,
        'name' => $name,
        'sku' => $sku,
        'description' => $description,
        'price' => $price,
        'is_active' => $isActive,
    ];

    if (isset($_POST['sale_price']) && $_POST['sale_price'] !== '') {
    $data['sale_price'] = str_replace(',', '.', $_POST['sale_price']);
    }

    if (isset($_POST['sale_start_date']) && $_POST['sale_start_date'] !== '') {
        $data['sale_start_date'] = $_POST['sale_start_date'];
    }

    if (isset($_POST['sale_end_date']) && $_POST['sale_end_date'] !== '') {
        $data['sale_end_date'] = $_POST['sale_end_date'];
    }

    if (isset($_POST['is_on_sale'])) {
        $data['is_on_sale'] = $_POST['is_on_sale'] ? 1 : 0;
    }

    
    // Aggiungi immagine se caricata
    if ($imagePath) {
        $data['image'] = $imagePath;
    }
    
    // Inserisci o aggiorna (solo se non è stato creato un prodotto globale)
    if (!$createGlobalProduct) {
        if ($id > 0) {
            // Aggiornamento
            $affected = db()->update('jta_pharma_prods', $data, 'id = ?', [$id]);
            
            if ($affected === 0) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nessuna modifica apportata'
                ]);
                exit;
            }
            
            $productId = $id;
            $action = 'product_updated';
            
            // Log attività
            logActivity($action, [
                'product_id' => $productId,
                'pharma_id' => $pharmacyId,
                'name' => $name,
                'sku' => $sku
            ]);
        } else {
            // Per nuovi prodotti, deve essere associato a un prodotto globale
            if ($productId <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Devi selezionare un prodotto dal catalogo globale o creare un nuovo prodotto nel catalogo'
                ]);
                exit;
            }
            
            // Se stiamo creando una promozione, ottieni i dati del prodotto globale
            if ($productId > 0) {
                $globalProduct = db_fetch_one("SELECT * FROM jta_global_prods WHERE id = ?", [$productId]);
                if (!$globalProduct) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Prodotto globale non trovato'
                    ]);
                    exit;
                }
                
                // Usa i dati del prodotto globale per creare la promozione
                $data['name'] = $globalProduct['name'];
                $data['description'] = $globalProduct['description'];
                $data['price'] = $globalProduct['price'] ?? $price; // Usa il prezzo del globale se disponibile
                $data['sku'] = $globalProduct['sku'] . '-FARM' . $pharmacyId; // SKU unico per farmacia
            }
            
            // Inserimento prodotto associato a globale
            $newProductId = db()->insert('jta_pharma_prods', $data);
            $action = 'product_added';
            
            // Log attività
            logActivity($action, [
                'product_id' => $newProductId,
                'pharma_id' => $pharmacyId,
                'name' => $data['name'],
                'sku' => $data['sku']
            ]);
            
            $productId = $newProductId;
        }
    }
    
    $message = '';
    if ($id > 0) {
        $message = 'Prodotto aggiornato con successo';
    } elseif ($createGlobalProduct) {
        $message = 'Prodotto creato nel catalogo globale e nella farmacia. Entrambi sono in attesa di approvazione dell\'admin.';
    } else {
        $message = 'Prodotto creato con successo';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
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