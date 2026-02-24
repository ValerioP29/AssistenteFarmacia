<?php
/**
 * API - Aggiorna Profilo Farmacia
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

    // Validazione input - solo i campi consentiti
    $email = sanitize($_POST['email'] ?? '');
    $phone_number = sanitize($_POST['phone_number'] ?? '');
    $business_name = sanitize($_POST['business_name'] ?? '');
    $nice_name = sanitize($_POST['nice_name'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $latlng = sanitize($_POST['latlng'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    // Validazioni
    if (empty($business_name)) {
        echo json_encode(['success' => false, 'message' => 'Ragione sociale è obbligatoria']);
        exit;
    }

    if (empty($nice_name)) {
        echo json_encode(['success' => false, 'message' => 'Nome farmacia è obbligatorio']);
        exit;
    }

    if (empty($city)) {
        echo json_encode(['success' => false, 'message' => 'Città è obbligatoria']);
        exit;
    }

    if (empty($address)) {
        echo json_encode(['success' => false, 'message' => 'Indirizzo è obbligatorio']);
        exit;
    }

    if ($email && !validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Email non valida']);
        exit;
    }

    // Controllo nome unico (escludendo la farmacia corrente)
    $duplicate_name = db_fetch_one("SELECT id FROM jta_pharmas WHERE nice_name = ? AND id != ? AND status != 'deleted'", [$nice_name, $pharmacy_id]);
    if ($duplicate_name) {
        echo json_encode(['success' => false, 'message' => 'Nome farmacia già esistente']);
        exit;
    }

    // Prepara dati per l'aggiornamento - solo i campi consentiti
    $update_data = [
        'email' => $email,
        'phone_number' => $phone_number,
        'business_name' => $business_name,
        'nice_name' => $nice_name,
        'city' => $city,
        'address' => $address,
        'latlng' => $latlng,
        'description' => $description,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Aggiorna la farmacia
    $result = db()->update('jta_pharmas', $update_data, 'id = ?', [$pharmacy_id]);

    if ($result) {
        // Log dell'attività
        logActivity('profile_updated', [
            'pharmacy_id' => $pharmacy_id,
            'fields_updated' => array_keys($update_data)
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Profilo aggiornato con successo'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Errore durante l\'aggiornamento del profilo'
        ]);
    }

} catch (Exception $e) {
    error_log("Errore aggiornamento profilo farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 