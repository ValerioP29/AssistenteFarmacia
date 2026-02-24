<?php
/**
 * API - Aggiorna Orari Farmacia
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

    // Giorni della settimana
    $days = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
    $working_hours = [];

    // Processa i dati per ogni giorno
    foreach ($days as $day) {
        $day_data = [
            'closed' => isset($_POST[$day . '_chiuso']),
            'morning_open' => sanitize($_POST[$day . '_mattina_apertura'] ?? ''),
            'morning_close' => sanitize($_POST[$day . '_mattina_chiusura'] ?? ''),
            'afternoon_open' => sanitize($_POST[$day . '_pomeriggio_apertura'] ?? ''),
            'afternoon_close' => sanitize($_POST[$day . '_pomeriggio_chiusura'] ?? '')
        ];

        // Validazione orari
        if (!$day_data['closed']) {
            // Se non è chiuso, verifica che gli orari siano validi
            if (empty($day_data['morning_open']) || empty($day_data['morning_close'])) {
                echo json_encode(['success' => false, 'message' => "Orari mattina obbligatori per $day"]);
                exit;
            }

            if (empty($day_data['afternoon_open']) || empty($day_data['afternoon_close'])) {
                echo json_encode(['success' => false, 'message' => "Orari pomeriggio obbligatori per $day"]);
                exit;
            }

            // Verifica che l'apertura sia prima della chiusura
            if (strtotime($day_data['morning_open']) >= strtotime($day_data['morning_close'])) {
                echo json_encode(['success' => false, 'message' => "Orari mattina non validi per $day"]);
                exit;
            }

            if (strtotime($day_data['afternoon_open']) >= strtotime($day_data['afternoon_close'])) {
                echo json_encode(['success' => false, 'message' => "Orari pomeriggio non validi per $day"]);
                exit;
            }
        }

        $working_hours[$day] = $day_data;
    }

    // Converti in JSON per salvare nel database
    $working_hours_json = json_encode($working_hours, JSON_UNESCAPED_UNICODE);

    // Aggiorna gli orari nel database
    $result = db()->update('jta_pharmas', [
        'working_info' => $working_hours_json,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$pharmacy_id]);

    if ($result) {
        // Log dell'attività
        logActivity('hours_updated', [
            'pharmacy_id' => $pharmacy_id,
            'working_hours' => $working_hours
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Orari aggiornati con successo'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Errore durante l\'aggiornamento degli orari'
        ]);
    }

} catch (Exception $e) {
    error_log("Errore aggiornamento orari farmacia: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?> 