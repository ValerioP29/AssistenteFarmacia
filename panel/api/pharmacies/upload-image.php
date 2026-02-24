<?php
/**
 * API - Upload Immagine Farmacia
 * Assistente Farmacia Panel
 */

// Avvia sessione
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

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
    // Controllo se è stato caricato un file
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'Nessun file caricato';
        if (isset($_FILES['image'])) {
            switch ($_FILES['image']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message = 'File troppo grande';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message = 'Upload parziale del file';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message = 'Nessun file selezionato';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message = 'Cartella temporanea mancante';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message = 'Errore scrittura file';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message = 'Estensione non permessa';
                    break;
            }
        }
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    $file = $_FILES['image'];
    $image_type = sanitize($_POST['image_type'] ?? ''); // avatar, cover, bot
    $pharmacy_id = (int)($_POST['pharmacy_id'] ?? 0);

    // Validazione tipo immagine
    if (!in_array($image_type, ['avatar', 'cover', 'bot'])) {
        echo json_encode(['success' => false, 'message' => 'Tipo immagine non valido']);
        exit;
    }

    // Validazione farmacia (se fornita)
    if ($pharmacy_id > 0) {
        $pharmacy = db_fetch_one("SELECT id FROM jta_pharmas WHERE id = ? AND status != 'deleted'", [$pharmacy_id]);
        if (!$pharmacy) {
            echo json_encode(['success' => false, 'message' => 'Farmacia non trovata']);
            exit;
        }
    }

    // Validazione file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Tipo file non supportato. Usa JPG, PNG, GIF o WebP']);
        exit;
    }

    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File troppo grande. Massimo 5MB']);
        exit;
    }

    // Verifica che sia un'immagine valida
    $image_info = getimagesize($file['tmp_name']);
    if (!$image_info) {
        echo json_encode(['success' => false, 'message' => 'File non è un\'immagine valida']);
        exit;
    }

    // Genera nome file unico
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $image_type . '_' . time() . '_' . uniqid() . '.' . $extension;
    $upload_path = '../../uploads/pharmacies/' . $filename;

    // Crea cartella se non esiste
    if (!is_dir('../../uploads/pharmacies/')) {
        mkdir('../../uploads/pharmacies/', 0755, true);
    }

    // Sposta il file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        echo json_encode(['success' => false, 'message' => 'Errore durante il salvataggio del file']);
        exit;
    }

    // Percorso relativo per il database
    $relative_path = 'uploads/pharmacies/' . $filename;

    // Se è fornito un pharmacy_id, aggiorna il database
    if ($pharmacy_id > 0) {
        $update_data = [
            $image_type === 'avatar' ? 'img_avatar' : 
            ($image_type === 'cover' ? 'img_cover' : 'img_bot') => $relative_path,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = db()->update('jta_pharmas', $update_data, 'id = ?', [$pharmacy_id]);
        
        if (!$result) {
            // Se l'aggiornamento fallisce, elimina il file
            unlink($upload_path);
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento del database']);
            exit;
        }
    }

    // Log attività
    logActivity('pharmacy_image_uploaded', [
        'admin_id' => $_SESSION['user_id'],
        'pharmacy_id' => $pharmacy_id,
        'image_type' => $image_type,
        'filename' => $filename
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Immagine caricata con successo',
        'filename' => $filename,
        'path' => $relative_path,
        'url' => $relative_path // URL per visualizzazione
    ]);

} catch (Exception $e) {
    error_log("Errore upload immagine farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 