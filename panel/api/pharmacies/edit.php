<?php
/**
 * API - Modifica Farmacia
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
requireApiAuth(['admin']);

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
    // Validazione input
    $pharmacy_id = (int)($_POST['pharmacy_id'] ?? 0);
    $nice_name = sanitize($_POST['nice_name'] ?? '');
    $slug_url = sanitize($_POST['slug_url'] ?? '');
    $business_name = sanitize($_POST['business_name'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $phone_number = sanitize($_POST['phone_number'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    $latlng = sanitize($_POST['latlng'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $working_info = sanitize($_POST['working_info'] ?? '');
    $prompt = sanitize($_POST['prompt'] ?? '');
    $img_avatar = sanitize($_POST['img_avatar'] ?? '');
    $img_cover = sanitize($_POST['img_cover'] ?? '');
    $img_bot = sanitize($_POST['img_bot'] ?? '');

    // Validazioni
    if (!$pharmacy_id) {
        echo json_encode(['success' => false, 'message' => 'ID farmacia richiesto']);
        exit;
    }

    if (empty($nice_name)) {
        echo json_encode(['success' => false, 'message' => 'Nome farmacia è obbligatorio']);
        exit;
    }

    if (!in_array($status, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Stato non valido']);
        exit;
    }

    if ($email && !validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Email non valida']);
        exit;
    }

    // Verifica che la farmacia esista
    $existing_pharmacy = db_fetch_one("SELECT id, nice_name FROM jta_pharmas WHERE id = ? AND status != 'deleted'", [$pharmacy_id]);
    if (!$existing_pharmacy) {
        echo json_encode(['success' => false, 'message' => 'Farmacia non trovata']);
        exit;
    }

    // Controllo nome unico (escludendo la farmacia corrente)
    $duplicate_name = db_fetch_one("SELECT id FROM jta_pharmas WHERE nice_name = ? AND id != ? AND status != 'deleted'", [$nice_name, $pharmacy_id]);
    if ($duplicate_name) {
        echo json_encode(['success' => false, 'message' => 'Nome farmacia già esistente']);
        exit;
    }

    // Genera slug_url se non fornito
    if (empty($slug_url)) {
        $slug_url = generatePharmacySlugUrl($nice_name, $pharmacy_id);
    } else {
        // Controllo slug_url unico (escludendo la farmacia corrente)
        $duplicate_slug = db_fetch_one("SELECT id FROM jta_pharmas WHERE slug_url = ? AND id != ? AND status != 'deleted'", [$slug_url, $pharmacy_id]);
        if ($duplicate_slug) {
            echo json_encode(['success' => false, 'message' => 'URL personalizzato già esistente']);
            exit;
        }
    }

    // Prepara dati per l'aggiornamento
    $update_data = [
        'nice_name' => $nice_name,
        'slug_url' => $slug_url,
        'business_name' => $business_name,
        'address' => $address,
        'city' => $city,
        'phone_number' => $phone_number,
        'email' => $email,
        'status' => $status,
        'latlng' => $latlng,
        'description' => $description,
        'working_info' => $working_info,
        'prompt' => $prompt,
        'img_avatar' => $img_avatar,
        'img_cover' => $img_cover,
        'img_bot' => $img_bot,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Aggiorna farmacia
    $result = db()->update('jta_pharmas', $update_data, 'id = ?', [$pharmacy_id]);

    if ($result) {
        // Log attività
        logActivity('pharmacy_updated', [
            'admin_id' => $_SESSION['user_id'],
            'pharmacy_id' => $pharmacy_id,
            'pharmacy_name' => $nice_name
        ]);

        echo json_encode([
            'success' => true, 
            'message' => 'Farmacia modificata con successo'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante la modifica della farmacia']);
    }

} catch (Exception $e) {
    error_log("Errore modifica farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?>
