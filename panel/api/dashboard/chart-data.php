<?php
/**
 * API Dati Grafico Dashboard
 * Assistente Farmacia Panel
 */

// Carica configurazione
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Imposta header JSON
header('Content-Type: application/json');

// Verifica metodo
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Carica middleware di autenticazione
require_once '../../includes/auth_middleware.php';

// Verifica autenticazione e permessi
requireApiAuth(['admin', 'pharmacist']);

try {
    // Ottieni parametri
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    
    // Limita a un massimo di 90 giorni
    $days = min($days, 90);
    
    // Ottieni dati grafico
    $chartData = getChartData($days);
    
    // Risposta di successo
    echo json_encode($chartData);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore nel recupero dei dati del grafico'
    ]);
}
?> 