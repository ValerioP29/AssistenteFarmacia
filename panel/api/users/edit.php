<?php
/**
 * API - Modifica Utente
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
    $user_id = (int)($_POST['user_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $surname = sanitize($_POST['surname'] ?? '');
    $slug_name = sanitize($_POST['slug_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone_number = sanitize($_POST['phone_number'] ?? '');
    $role = sanitize($_POST['role'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';
    $starred_pharma = (int)($_POST['starred_pharma'] ?? 0);

    // Validazioni
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'ID utente richiesto']);
        exit;
    }

    if (empty($name) || empty($surname) || empty($slug_name) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'Tutti i campi obbligatori devono essere compilati']);
        exit;
    }

    // Validazione email solo se fornita
    if (!empty($email) && !validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Email non valida']);
        exit;
    }

    if (!in_array($role, ['pharmacist', 'user'])) {
        echo json_encode(['success' => false, 'message' => 'Ruolo non valido']);
        exit;
    }

    if (!in_array($status, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Stato non valido']);
        exit;
    }

    if ($password && strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'La password deve essere di almeno 6 caratteri']);
        exit;
    }

    // Verifica che l'utente esista
    $existing_user = db_fetch_one("SELECT id, slug_name, email FROM jta_users WHERE id = ? AND status != 'deleted'", [$user_id]);
    if (!$existing_user) {
        echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
        exit;
    }

    // Controllo username unico (escludendo l'utente corrente)
    $duplicate_username = db_fetch_one("SELECT id FROM jta_users WHERE slug_name = ? AND id != ? AND status != 'deleted'", [$slug_name, $user_id]);
    if ($duplicate_username) {
        echo json_encode(['success' => false, 'message' => 'Username già esistente']);
        exit;
    }

    // Controllo email unica (escludendo l'utente corrente) solo se fornita
    if (!empty($email)) {
        $duplicate_email = db_fetch_one("SELECT id FROM jta_users WHERE email = ? AND id != ? AND status != 'deleted'", [$email, $user_id]);
        if ($duplicate_email) {
            echo json_encode(['success' => false, 'message' => 'Email già esistente']);
            exit;
        }
    }

    // Prepara dati per l'aggiornamento
    $update_data = [
        'name' => $name,
        'surname' => $surname,
        'slug_name' => $slug_name,
        'email' => $email,
        'phone_number' => $phone_number,
        'role' => $role,
        'status' => $status,
        'starred_pharma' => $starred_pharma,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Aggiungi password solo se fornita
    if ($password) {
        $update_data['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    // Aggiorna utente
    $result = db()->update('jta_users', $update_data, 'id = ?', [$user_id]);

    if ($result) {
        // Log attività
        logActivity('user_updated', [
            'admin_id' => $_SESSION['user_id'],
            'updated_user_id' => $user_id,
            'role' => $role
        ]);

        echo json_encode([
            'success' => true, 
            'message' => 'Utente modificato con successo'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante la modifica dell\'utente']);
    }

} catch (Exception $e) {
    error_log("Errore modifica utente: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?>
