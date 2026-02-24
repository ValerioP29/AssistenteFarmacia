<?php
/**
 * API - Aggiungi Utente
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
    $name = sanitize($_POST['name'] ?? '');
    $surname = sanitize($_POST['surname'] ?? '');
    $slug_name = sanitize($_POST['slug_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone_number = sanitize($_POST['phone_number'] ?? '');
    $role = sanitize($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $starred_pharma = (int)($_POST['starred_pharma'] ?? 0);

    // Validazioni
    if (empty($name) || empty($surname) || empty($slug_name) || empty($role) || empty($password)) {
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

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'La password deve essere di almeno 6 caratteri']);
        exit;
    }

    // Controllo username unico
    $existing_username = db_fetch_one("SELECT id FROM jta_users WHERE slug_name = ? AND status != 'deleted'", [$slug_name]);
    if ($existing_username) {
        echo json_encode(['success' => false, 'message' => 'Username già esistente']);
        exit;
    }

    // Controllo email unica solo se fornita
    if (!empty($email)) {
        $existing_email = db_fetch_one("SELECT id FROM jta_users WHERE email = ? AND status != 'deleted'", [$email]);
        if ($existing_email) {
            echo json_encode(['success' => false, 'message' => 'Email già esistente']);
            exit;
        }
    }

    // Prepara dati per l'inserimento
    $user_data = [
        'name' => $name,
        'surname' => $surname,
        'slug_name' => $slug_name,
        'email' => $email,
        'phone_number' => $phone_number,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'starred_pharma' => $starred_pharma,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Inserisci utente
    $user_id = db()->insert('jta_users', $user_data);

    if ($user_id) {
        // Log attività
        logActivity('user_created', [
            'admin_id' => $_SESSION['user_id'],
            'new_user_id' => $user_id,
            'role' => $role
        ]);

        echo json_encode([
            'success' => true, 
            'message' => 'Utente creato con successo',
            'user_id' => $user_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante la creazione dell\'utente']);
    }

} catch (Exception $e) {
    error_log("Errore creazione utente: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server: ' . $e->getMessage()]);
}
?>
