<?php
/**
 * API Template Import Prodotti
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

try {
    // Imposta headers per download CSV
    $filename = 'template_prodotti_globali.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Crea output stream
    $output = fopen('php://output', 'w');
    
    // BOM per UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Intestazioni CSV
    $headers = [
        'SKU',
        'Nome',
        'Descrizione',
        'Categoria',
        'Brand',
        'Principio Attivo',
        'Forma Farmaceutica',
        'Dosaggio',
        'Confezione',
        'Richiede Ricetta',
        'Stato'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Esempi di dati
    $examples = [
        [
            'PAR001',
            'Paracetamolo',
            'Antidolorifico e antipiretico',
            'Antidolorifici',
            'Generico',
            'Paracetamolo',
            'Compresse',
            '500mg',
            '20 compresse',
            'No',
            'Attivo'
        ],
        [
            'IBU001',
            'Ibuprofene',
            'Antinfiammatorio non steroideo',
            'Antinfiammatori',
            'Generico',
            'Ibuprofene',
            'Compresse',
            '400mg',
            '20 compresse',
            'No',
            'Attivo'
        ],
        [
            'VIT001',
            'Vitamina C',
            'Integratore vitaminico',
            'Integratori',
            'Generico',
            'Acido ascorbico',
            'Compresse',
            '1000mg',
            '30 compresse',
            'No',
            'Attivo'
        ]
    ];
    
    foreach ($examples as $example) {
        fputcsv($output, $example, ';');
    }
    
    fclose($output);
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Errore nel download del template: " . $e->getMessage();
}
?> 