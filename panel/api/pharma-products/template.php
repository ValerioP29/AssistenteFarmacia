<?php
/**
 * API Template Import Promozioni
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

// Imposta headers per download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_promozioni.csv"');

// Crea output stream
$output = fopen('php://output', 'w');

// BOM per UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Intestazioni colonne
$headers = [
    'SKU_Prodotto',
    'Nome_Prodotto', 
    'Prezzo_Originale',
    'Prezzo_Scontato',
    'Data_Inizio_Promozione (YYYY-MM-DD)',
    'Data_Fine_Promozione (YYYY-MM-DD)',
    'Promozione_Attiva'
];

// Scrivi intestazioni
fputcsv($output, $headers, ';');

// Esempi di dati
$examples = [
    [
        'PAR001-FARM1',
        'Paracetamolo 500mg',
        '8.50',
        '6.80',
        '2024-01-01',
        '2024-01-31',
        '1'
    ],
    [
        'IBU001-FARM1',
        'Ibuprofene 400mg',
        '12.30',
        '9.85',
        '2024-01-15',
        '2024-02-15',
        '1'
    ],
    [
        'VIT001-FARM1',
        'Vitamina C 1000mg',
        '15.90',
        '11.90',
        '2024-02-01',
        '2024-02-29',
        '0'
    ]
];

// Scrivi esempi
foreach ($examples as $example) {
    fputcsv($output, $example, ';');
}

// Chiudi file
fclose($output);
?> 