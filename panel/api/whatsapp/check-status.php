<?php
/**
 * API: Controlla stato connessione WhatsApp
 * Assistente Farmacia Panel
 */

require_once 'functions.php';
header('Content-Type: application/json');

// Controlla se l'utente è autenticato
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Utente non autenticato'
    ]);
    exit;
}

$resp = whatsapp_is_connected();

echo json_encode([
    'success' => $resp['success'],
    'message' => $resp['message'],
]); 