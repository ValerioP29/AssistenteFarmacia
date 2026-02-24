<?php
/**
 * API - Aggiorna Turno Farmacia
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

    // Validazione input
    $giorno_turno = sanitize($_POST['giorno_turno'] ?? '');
    
    // Validazione giorno turno
    $giorni_validi = ['', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
    if (!in_array($giorno_turno, $giorni_validi)) {
        echo json_encode(['success' => false, 'message' => 'Giorno di turno non valido']);
        exit;
    }

    // Aggiorna il turno nel database
    $result = db()->update('jta_pharmas', [
        'turno_giorno' => $giorno_turno,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$pharmacy_id]);

    if ($result) {
        // Log dell'attività
        logActivity('turno_updated', [
            'pharmacy_id' => $pharmacy_id,
            'turno_giorno' => $giorno_turno
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Turno aggiornato con successo'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Errore durante l\'aggiornamento del turno'
        ]);
    }

} catch (Exception $e) {
    error_log("Errore aggiornamento turno farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 