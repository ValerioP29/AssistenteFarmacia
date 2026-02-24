<?php
/**
 * API - Rimuovi Logo Farmacia
 * Assistente Farmacia Panel
 */

// Avvia sessione
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

// Controllo metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Carica middleware di autenticazione
require_once '../../includes/auth_middleware.php';

// Controllo autenticazione e ruolo
requireApiAuth(['pharmacist']);

// Verifica CSRF
$csrf_token = $_POST['csrf_token'] ?? '';

if (!isset($_SESSION['csrf_token'])) {
    generateCSRFToken();
}

if (!verifyCSRFToken($csrf_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido']);
    exit;
}

try {
    // Ottieni la farmacia corrente
    $pharmacy = getCurrentPharmacy();
    if (!$pharmacy) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Farmacia non trovata']);
        exit;
    }

    $pharmacy_id = $pharmacy['id'];
    $current_logo = $pharmacy['logo'];

    // Verifica se esiste un logo
    if (empty($current_logo)) {
        echo json_encode(['success' => false, 'message' => 'Nessun logo da rimuovere']);
        exit;
    }

    // Elimina il file dal filesystem
    $file_path = '../../' . $current_logo;
    if (file_exists($file_path)) {
        if (!unlink($file_path)) {
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione del file']);
            exit;
        }
    }

    // Aggiorna il database
    $result = db()->update('jta_pharmas', 
        ['logo' => null, 'updated_at' => date('Y-m-d H:i:s')], 
        'id = ?', 
        [$pharmacy_id]
    );
    
    if ($result) {
        // Log dell'attività
        logActivity('logo_removed', [
            'pharmacy_id' => $pharmacy_id,
            'old_logo_path' => $current_logo
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Logo rimosso con successo'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento del database']);
    }

} catch (Exception $e) {
    error_log("Errore rimozione logo farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 