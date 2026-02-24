<?php
/**
 * Script Import Massivo Prodotti Farmacia Giovinazzi
 * Assistente Farmacia Panel
 * 
 * Questo script importa tutti i prodotti dal file CSV nella farmacia Giovinazzi (ID: 1)
 * - Legge il file CSV con nome, codice e prezzo
 * - Cerca le immagini corrispondenti nella cartella generico
 * - Inserisce i prodotti in jta_global_prods con SKU GLOPROD + ID
 * - Inserisce i prodotti in jta_pharma_prods con il codice del CSV
 */

require_once 'config/database.php';
require_once 'includes/image_manager.php';

// Configurazione
$pharmacyId = 1; // ID farmacia Giovinazzi
$csvFile = 'import/prodotti-giovinazzi.csv';
$imagesDir = 'uploads/products/generico/';
$category = 'generico';

// Contatori
$totalProducts = 0;
$importedProducts = 0;
$skippedProducts = 0;
$productsWithImages = 0;
$productsWithoutImages = [];
$errors = [];

$startTime = microtime(true);
echo "=== IMPORT MASSIVO PRODOTTI FARMACIA GIOVINAZZI ===\n";
echo "Data e ora inizio: " . date('Y-m-d H:i:s') . "\n";
echo "Farmacia ID: {$pharmacyId}\n";
echo "File CSV: {$csvFile}\n";
echo "Categoria: {$category}\n\n";

// Verifica esistenza file CSV
if (!file_exists($csvFile)) {
    die("ERRORE: File CSV non trovato: {$csvFile}\n");
}

// Verifica esistenza directory immagini
if (!is_dir($imagesDir)) {
    die("ERRORE: Directory immagini non trovata: {$imagesDir}\n");
}

try {
    // Apri file CSV
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        die("ERRORE: Impossibile aprire il file CSV\n");
    }
    
    // Leggi header (se presente) e salta
    $header = fgetcsv($handle);
    
    echo "Inizio import...\n\n";
    
    // Leggi ogni riga del CSV
    while (($data = fgetcsv($handle)) !== false) {
        $totalProducts++;
        
        // Mostra progresso ogni 10 prodotti
        if ($totalProducts % 10 == 0) {
            $progress = round(($totalProducts / 7080) * 100, 1); // 7080 è il numero totale di righe nel CSV
            echo "\n[PROGRESSO] Processati {$totalProducts} prodotti ({$progress}%) - " . date('H:i:s') . "\n";
            echo "  Importati: {$importedProducts} | Saltati: {$skippedProducts} | Con immagini: {$productsWithImages} | Senza immagini: " . count($productsWithoutImages) . "\n\n";
        }
        
        // Verifica che ci siano almeno 3 colonne (nome, codice, prezzo)
        if (count($data) < 3) {
            $errors[] = "Riga {$totalProducts}: Dati insufficienti";
            $skippedProducts++;
            continue;
        }
        
        $productName = trim($data[0]);
        $productCode = trim($data[1]);
        $productPrice = floatval($data[2]);
        
        // Salta righe vuote
        if (empty($productName)) {
            $skippedProducts++;
            continue;
        }
        
        echo "[{$totalProducts}] Processando: {$productName} (Codice: {$productCode}, Prezzo: €{$productPrice})\n";
        
        try {
            // 1. Cerca immagine corrispondente
            $imagePath = findProductImage($productName, $imagesDir);
            
            // Registra prodotti senza immagini
            if (!$imagePath) {
                $productsWithoutImages[] = [
                    'name' => $productName,
                    'code' => $productCode,
                    'price' => $productPrice,
                    'row' => $totalProducts
                ];
                echo "  ⚠ Immagine non trovata per: {$productName}\n";
            } else {
                $productsWithImages++;
                echo "  ✓ Immagine trovata: " . basename($imagePath) . "\n";
            }
            
            // 2. Inserisci in jta_global_prods
            $globalProductId = insertGlobalProduct($productName, $imagePath, $category);
            
            // 3. Inserisci in jta_pharma_prods
            $pharmaProductId = insertPharmaProduct($globalProductId, $productName, $productCode, $productPrice, $imagePath, $pharmacyId);
            
            $importedProducts++;
            echo "  ✓ Importato con successo (Global ID: {$globalProductId}, Pharma ID: {$pharmaProductId})\n";
            
        } catch (Exception $e) {
            $errors[] = "Riga {$totalProducts} - {$productName}: " . $e->getMessage();
            $skippedProducts++;
            echo "  ✗ Errore: " . $e->getMessage() . "\n";
        }
        
        // Pausa per non sovraccaricare il database
        usleep(100000); // 0.1 secondi
    }
    
    fclose($handle);
    
} catch (Exception $e) {
    echo "ERRORE CRITICO: " . $e->getMessage() . "\n";
    exit(1);
}

// Riepilogo finale
echo "\n=== RIEPILOGO IMPORT ===\n";
echo "Prodotti totali nel CSV: {$totalProducts}\n";
echo "Prodotti importati con successo: {$importedProducts}\n";
echo "Prodotti saltati: {$skippedProducts}\n";
echo "Prodotti con immagini: {$productsWithImages}\n";
echo "Prodotti senza immagini: " . count($productsWithoutImages) . "\n";
echo "Errori: " . count($errors) . "\n";

// Resoconto prodotti senza immagini
if (!empty($productsWithoutImages)) {
    echo "\n=== PRODOTTI SENZA IMMAGINI ===\n";
    echo "Totale prodotti senza immagini: " . count($productsWithoutImages) . "\n\n";
    
    // Raggruppa per prime 10 lettere del nome per facilitare la ricerca
    $groupedProducts = [];
    foreach ($productsWithoutImages as $product) {
        $prefix = substr($product['name'], 0, 10);
        if (!isset($groupedProducts[$prefix])) {
            $groupedProducts[$prefix] = [];
        }
        $groupedProducts[$prefix][] = $product;
    }
    
    foreach ($groupedProducts as $prefix => $products) {
        echo "--- Prodotti che iniziano con '{$prefix}' ---\n";
        foreach ($products as $product) {
            echo "  • {$product['name']} (Codice: {$product['code']}, Prezzo: €{$product['price']}, Riga: {$product['row']})\n";
        }
        echo "\n";
    }
    
    // Salva anche in file CSV per riferimento futuro
    $missingImagesFile = 'import/prodotti_senza_immagini_' . date('Y-m-d_H-i-s') . '.csv';
    $handle = fopen($missingImagesFile, 'w');
    if ($handle) {
        fputcsv($handle, ['Nome Prodotto', 'Codice', 'Prezzo', 'Riga CSV']);
        foreach ($productsWithoutImages as $product) {
            fputcsv($handle, [$product['name'], $product['code'], $product['price'], $product['row']]);
        }
        fclose($handle);
        echo "Lista prodotti senza immagini salvata in: {$missingImagesFile}\n";
    }
}

if (!empty($errors)) {
    echo "\n=== ERRORI DETTAGLIATI ===\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
}

$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);
$avgTimePerProduct = $totalProducts > 0 ? round($executionTime / $totalProducts, 3) : 0;

echo "\n=== TEMPI DI ESECUZIONE ===\n";
echo "Inizio: " . date('Y-m-d H:i:s', $startTime) . "\n";
echo "Fine: " . date('Y-m-d H:i:s', $endTime) . "\n";
echo "Tempo totale: {$executionTime} secondi\n";
echo "Tempo medio per prodotto: {$avgTimePerProduct} secondi\n";
echo "Prodotti al secondo: " . ($executionTime > 0 ? round($totalProducts / $executionTime, 1) : 0) . "\n\n";

echo "Import completato alle: " . date('Y-m-d H:i:s') . "\n";

/**
 * Trova l'immagine corrispondente al nome del prodotto
 */
function findProductImage($productName, $imagesDir) {
    // Normalizza il nome del prodotto per la ricerca
    $normalizedName = normalizeProductName($productName);
    
    // Cerca file con estensioni comuni
    $extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    foreach ($extensions as $ext) {
        $imageFile = $imagesDir . $normalizedName . '.' . $ext;
        if (file_exists($imageFile)) {
            return 'uploads/products/generico/' . basename($imageFile);
        }
    }
    
    // Se non trova l'immagine esatta, cerca corrispondenze parziali
    $files = glob($imagesDir . '*' . $extensions[0]);
    foreach ($files as $file) {
        $filename = basename($file, '.' . pathinfo($file, PATHINFO_EXTENSION));
        $normalizedFilename = normalizeProductName($filename);
        
        // Verifica se il nome del prodotto è contenuto nel nome del file o viceversa
        if (stripos($normalizedFilename, $normalizedName) !== false || 
            stripos($normalizedName, $normalizedFilename) !== false) {
            return 'uploads/products/generico/' . basename($file);
        }
    }
    
    // Nessuna immagine trovata
    return null;
}

/**
 * Normalizza il nome del prodotto per la ricerca delle immagini
 */
function normalizeProductName($name) {
    // Rimuovi caratteri speciali e spazi extra
    $normalized = preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = trim($normalized);
    
    return strtoupper($normalized);
}

/**
 * Inserisce un prodotto nella tabella jta_global_prods
 */
function insertGlobalProduct($name, $imagePath, $category) {
    // Genera SKU unico
    $sku = 'GLOPROD' . time() . rand(100, 999);
    
    // Prepara dati per inserimento
    $data = [
        'sku' => $sku,
        'name' => $name,
        'description' => '', // Descrizione vuota
        'image' => $imagePath,
        'category' => $category,
        'brand' => 'Generico',
        'active_ingredient' => '',
        'dosage_form' => '',
        'strength' => '',
        'package_size' => '',
        'requires_prescription' => 0,
        'is_active' => 'active'
    ];
    
    // Inserisci nel database
    $productId = db()->insert('jta_global_prods', $data);
    
    if (!$productId) {
        throw new Exception("Impossibile inserire prodotto globale");
    }
    
    return $productId;
}

/**
 * Inserisce un prodotto nella tabella jta_pharma_prods
 */
function insertPharmaProduct($globalProductId, $name, $code, $price, $imagePath, $pharmacyId) {
    // Prepara dati per inserimento
    $data = [
        'pharma_id' => $pharmacyId,
        'product_id' => $globalProductId,
        'name' => $name,
        'sku' => $code, // Usa il codice del CSV
        'description' => '', // Descrizione vuota
        'price' => $price,
        'sale_price' => null,
        'num_items' => 0,
        'is_active' => 1,
        'is_on_sale' => 0,
        'sale_start_date' => null,
        'sale_end_date' => null
    ];
    
    // Aggiungi immagine se trovata
    if ($imagePath) {
        $data['image'] = $imagePath;
    }
    
    // Inserisci nel database
    $productId = db()->insert('jta_pharma_prods', $data);
    
    if (!$productId) {
        throw new Exception("Impossibile inserire prodotto farmacia");
    }
    
    return $productId;
}

?> 