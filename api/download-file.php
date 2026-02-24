<?php
require_once('_api_bootstrap.php');

// Imposta solo gli headers CORS, non il Content-Type JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Gestisci autenticazione tramite header Authorization o parametro token
$token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    $token = $matches[1];
} else {
    $token = $_GET['token'] ?? null;
}

if (!$token) {
    echo json_encode([
        'code'    => 401,
        'status'  => false,
        'error'   => 'Missing token',
        'message' => 'Token mancante',
    ]);
    exit();
}

// Valida il token
$decoded = getJwtDecoded($token);
if (!$decoded) {
    echo json_encode([
        'code'    => 401,
        'status'  => false,
        'error'   => 'Invalid token',
        'message' => 'Token non valido o scaduto',
    ]);
    exit();
}

// Ottieni i dati utente direttamente dal token decodificato
$user_id = $decoded->sub ?? null;
if (!$user_id) {
    echo json_encode([
        'code'    => 401,
        'status'  => false,
        'error'   => 'Invalid token payload',
        'message' => 'Token non valido',
    ]);
    exit();
}

$user = get_user_by_id($user_id);
if (!$user) {
    echo json_encode([
        'code'    => 401,
        'status'  => false,
        'error'   => 'User not found',
        'message' => 'Utente non trovato',
    ]);
    exit();
}

//------------------------------------------------

$filepath = $_GET['file'] ?? null;

if( ! $filepath ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Parametro file mancante.',
	]);
	exit();
}

    // Verifica che il file appartenga all'utente
    // Supporta formato unificato e legacy per compatibilità
    $file_belongs_to_user = false;

    // Formato unificato: users/{user_id}/{filename}
    if (str_starts_with($filepath, 'users/' . $user['id'] . '/')) {
        $file_belongs_to_user = true;
    }
    
    // Formato legacy terapie: users/{user_id}/terapies/{filename}
    if (str_starts_with($filepath, 'users/' . $user['id'] . '/terapies/')) {
        $file_belongs_to_user = true;
    }
    
    // Formato legacy scadenze: users/{user_id}/expiry/{filename}
    if (str_starts_with($filepath, 'users/' . $user['id'] . '/expiry/')) {
        $file_belongs_to_user = true;
    }

if (!$file_belongs_to_user) {
    echo json_encode([
        'code'    => 403,
        'status'  => false,
        'error'   => 'Forbidden',
        'message' => 'Accesso negato al file.',
    ]);
    exit();
}

// Verifica che il file esista
// Gestisci entrambi i formati: con e senza uploads/ nel percorso
if (str_starts_with($filepath, 'uploads/')) {
    // Il percorso già include uploads/, usa direttamente
    $full_path = $filepath;
} else {
    // Il percorso non include uploads/, aggiungilo
    $full_path = getUploadedFilePath($filepath);
}

if (!file_exists($full_path)) {
    echo json_encode([
        'code'    => 404,
        'status'  => false,
        'error'   => 'Not Found',
        'message' => 'File non trovato.',
    ]);
    exit();
}

// Ottieni informazioni sul file
$file_info = getFileInfo($filepath);
if( ! $file_info ){
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Internal Server Error',
		'message' => 'Errore nel recupero del file.',
	]);
	exit();
}

// Imposta headers per il download
header('Content-Type: ' . $file_info['mime_type']);
header('Content-Disposition: attachment; filename="' . $file_info['name'] . '"');
header('Content-Length: ' . $file_info['size']);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Servi il file
readfile($full_path);
exit(); 