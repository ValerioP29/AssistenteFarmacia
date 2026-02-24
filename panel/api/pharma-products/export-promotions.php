<?php
/**
 * API Export Promozioni
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

try {
    $pharma_id = $_SESSION['pharmacy_id'];
    
    // Parametri filtri
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $category = trim($_GET['category'] ?? '');
    $discount = $_GET['discount'] ?? '';
    
    // Costruisci query con JOIN
    $sql = "SELECT pp.*, gp.category, gp.brand, gp.active_ingredient 
            FROM jta_pharma_prods pp 
            LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id 
            WHERE pp.pharma_id = ?";
    $params = [$pharma_id];
    
    // Aggiungi filtri
    if ($search) {
        $sql .= " AND (pp.sku LIKE ? OR pp.name LIKE ? OR pp.description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($status) {
        switch ($status) {
            case 'active':
                $sql .= " AND pp.is_on_sale = 1 AND DATE(pp.sale_start_date) <= CURDATE() AND DATE(pp.sale_end_date) >= CURDATE()";
                break;
            case 'inactive':
                $sql .= " AND pp.is_on_sale = 0";
                break;
            case 'expired':
                $sql .= " AND pp.is_on_sale = 1 AND DATE(pp.sale_end_date) < CURDATE()";
                break;
            case 'upcoming':
                $sql .= " AND pp.is_on_sale = 1 AND DATE(pp.sale_start_date) > CURDATE()";
                break;
        }
    }
    
    if ($category) {
        $sql .= " AND gp.category = ?";
        $params[] = $category;
    }
    
    if ($discount) {
        switch ($discount) {
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
    
    $sql .= " ORDER BY pp.name ASC";
    
    // Esegui query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Imposta headers per download CSV
    $filename = 'promozioni_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Crea output stream
    $output = fopen('php://output', 'w');
    
    // BOM per UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Intestazioni colonne
    $headers = [
        'SKU',
        'Nome Prodotto',
        'Categoria',
        'Brand',
        'Prezzo Originale',
        'Prezzo Scontato',
        'Sconto %',
        'Data Inizio',
        'Data Fine',
        'Stato Promozione',
        'Prodotto Attivo'
    ];
    
    // Scrivi intestazioni
    fputcsv($output, $headers, ';');
    
    // Scrivi dati
    foreach ($promotions as $promotion) {
        // Calcola sconto percentuale
        $discount_percent = 0;
        if ($promotion['price'] > 0 && $promotion['sale_price'] > 0) {
            $discount_percent = round(((float)$promotion['price'] - (float)$promotion['sale_price']) / (float)$promotion['price'] * 100, 1);
        }
        
        // Determina stato promozione
        $now = new DateTime();
        $start_date = new DateTime($promotion['sale_start_date']);
        $end_date = new DateTime($promotion['sale_end_date']);
        
        $promotion_status = 'Inattiva';
        if ($promotion['is_on_sale'] == 1) {
            if ($now->format('Y-m-d') < $start_date->format('Y-m-d')) {
                $promotion_status = 'In arrivo';
            } elseif ($now->format('Y-m-d') >= $start_date->format('Y-m-d') && $now->format('Y-m-d') <= $end_date->format('Y-m-d')) {
                $promotion_status = 'Attiva';
            } else {
                $promotion_status = 'Scaduta';
            }
        }
        
        $row = [
            $promotion['sku'] ?? '',
            $promotion['name'],
            $promotion['category'] ?? '',
            $promotion['brand'] ?? '',
            number_format((float)$promotion['price'], 2, ',', '.'),
            $promotion['sale_price'] ? number_format((float)$promotion['sale_price'], 2, ',', '.') : '',
            $discount_percent . '%',
            $promotion['sale_start_date'] ? date('d/m/Y', strtotime($promotion['sale_start_date'])) : '',
            $promotion['sale_end_date'] ? date('d/m/Y', strtotime($promotion['sale_end_date'])) : '',
            $promotion_status,
            $promotion['is_active'] == 1 ? 'Sì' : 'No'
        ];
        
        fputcsv($output, $row, ';');
    }
    
    // Chiudi file
    fclose($output);
    
} catch (Exception $e) {
    error_log("Errore export promozioni: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server'
    ]);
}
?> 