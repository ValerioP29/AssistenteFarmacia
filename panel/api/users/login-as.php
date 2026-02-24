<?php
/**
 * API - Accedi come Utente
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

// Se non c'è un token CSRF nella sessione, ne genera uno nuovo
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

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'ID utente mancante']);
        exit;
    }

    // Verifica esistenza utente
    $user = db_fetch_one("SELECT * FROM jta_users WHERE id = ? AND status != 'deleted'", [$user_id]);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
        exit;
    }

    // Verifica che l'utente non sia admin
    if ($user['role'] === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Non puoi accedere come amministratore']);
        exit;
    }

    // Salva i dati dell'admin corrente
    $admin_id = $_SESSION['user_id'];
    $admin_role = $_SESSION['user_role'];
    $admin_name = $_SESSION['user_name'];

    // Imposta la sessione come l'utente selezionato
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['slug_name'];
    $_SESSION['pharmacy_id'] = $user['starred_pharma'] ?? 1;
    
    // Flag per indicare che è un accesso "come"
    $_SESSION['login_as'] = true;
    $_SESSION['original_admin_id'] = $admin_id;
    $_SESSION['original_admin_name'] = $admin_name;

    // Aggiorna ultimo accesso dell'utente
    db()->update('jta_users', ['last_access' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

    // Log attività
    logActivity('login_as_user', [
        'admin_id' => $admin_id,
        'target_user_id' => $user['id'],
        'target_user_role' => $user['role']
    ]);

    echo json_encode([
        'success' => true, 
        'message' => 'Accesso effettuato come ' . $user['name'] . ' ' . $user['surname'],
        'user_name' => $user['name'] . ' ' . $user['surname'],
        'user_role' => $user['role']
    ]);

} catch (Exception $e) {
    error_log("Errore login as user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 