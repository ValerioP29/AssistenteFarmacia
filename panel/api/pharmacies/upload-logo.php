<?php
/**
 * API - Upload Logo Farmacia
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

    // Verifica se è stato caricato un file
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Nessun file caricato o errore nel caricamento']);
        exit;
    }

    $file = $_FILES['logo'];
    
    // Validazioni del file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Verifica tipo file
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Tipo di file non consentito. Usa JPG, PNG o GIF']);
        exit;
    }
    
    // Verifica dimensione
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
    
    // Crea directory se non esiste
    $upload_dir = '../../uploads/pharmacies/logos/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Errore nella creazione della directory']);
            exit;
        }
    }
    
    // Genera nome file unico
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'pharmacy_' . $pharmacy_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    

    
    // Sposta il file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Errore durante il salvataggio del file']);
        exit;
    }
    
    // Percorso relativo per il database
    $relative_path = 'uploads/pharmacies/logos/' . $filename;
    
    // Elimina il vecchio logo se esiste
    if (!empty($pharmacy['logo']) && file_exists('../../' . $pharmacy['logo'])) {
        unlink('../../' . $pharmacy['logo']);
    }
    
    // Aggiorna il database
    $result = db()->update('jta_pharmas', 
        ['logo' => $relative_path, 'updated_at' => date('Y-m-d H:i:s')], 
        'id = ?', 
        [$pharmacy_id]
    );
    
    if ($result) {
        // Log dell'attività
        logActivity('logo_uploaded', [
            'pharmacy_id' => $pharmacy_id,
            'logo_path' => $relative_path
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Logo caricato con successo',
            'logo_path' => $relative_path
        ]);
    } else {
        // Elimina il file se l'aggiornamento del database fallisce
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        echo json_encode(['success' => false, 'message' => 'Errore durante il salvataggio nel database']);
    }

} catch (Exception $e) {
    error_log("Errore upload logo farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 