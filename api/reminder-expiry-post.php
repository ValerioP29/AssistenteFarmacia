<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if( ! $user ){
	echo json_encode([
		'code'    => 401,
		'status'  => false,
		'error'   => 'Invalid or expired token',
		'message' => 'Accesso negato',
	]);
	exit();
}

//------------------------------------------------

// Gestione multipart/form-data per supportare il caricamento di file
$input = [];
if ($_SERVER['CONTENT_TYPE'] && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    // Dati da multipart/form-data
    $input = $_POST;
    
    // Gestione del file caricato
    $uploaded_file = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['file'];
    }
} else {
    // Dati da JSON
    $input = json_decode(file_get_contents("php://input"), TRUE);
}

$product_name = $input['productName'] ?? FALSE;
$expiry_date  = $input['expiryDate'] ?? FALSE;
$alerts       = $input['alerts'] ?? FALSE;
$notes        = $input['notes'] ?? NULL;

// Gestione del formato alerts per compatibilità con multipart/form-data
if (is_string($alerts)) {
    // Se alerts è una stringa, la convertiamo in array
    $alerts = json_decode($alerts, true);
} elseif (!is_array($alerts)) {
    // Se non è né stringa né array, creiamo un array vuoto
    $alerts = [];
}

// Validazione campi obbligatori
if( ! $product_name || ! $expiry_date || ! $alerts ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Campi obbligatori mancanti.',
	]);
	exit();
}

// Validazione data di scadenza (deve essere futura)
if( $expiry_date <= date('Y-m-d') ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'La data di scadenza deve essere futura.',
	]);
	exit();
}

// Validazione alerts - almeno uno deve essere selezionato
if( ! is_array($alerts) || ( ! $alerts['alert30'] && ! $alerts['alert15'] && ! $alerts['alert7'] && ! $alerts['alert1'] ) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Seleziona almeno un avviso di promemoria.',
	]);
	exit();
}

// Gestione del file caricato
$file_path = null;
if ($uploaded_file) {
    // Validazione del file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($uploaded_file['type'], $allowed_types)) {
        echo json_encode([
            'code'    => 400,
            'status'  => false,
            'error'   => 'Bad Request',
            'message' => 'Tipo di file non supportato. Utilizza solo immagini JPG, PNG o GIF.',
        ]);
        exit();
    }
    
    if ($uploaded_file['size'] > $max_size) {
        echo json_encode([
            'code'    => 400,
            'status'  => false,
            'error'   => 'Bad Request',
            'message' => 'File troppo grande. Dimensione massima: 5MB.',
        ]);
        exit();
    }
    
    // Salvataggio del file
    $upload_dir = 'uploads/users/' . $user['id'] . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    $file_name = 'expiry_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
        echo json_encode([
            'code'    => 500,
            'status'  => false,
            'error'   => 'Internal Server Error',
            'message' => 'Errore durante il salvataggio del file.',
        ]);
        exit();
    }
    
    // Salva il percorso relativo per il database (senza uploads/)
    $file_path = 'users/' . $user['id'] . '/' . $file_name;
}

//------------------------------------------------

$reminder_data = [
	'productName' => $product_name,
	'expiryDate'  => $expiry_date,
	'alerts'      => $alerts,
	'notes'       => $notes,
	'file'        => $file_path
];

$reminder_id = create_reminder_expiry( $user['id'], $reminder_data );

if( ! $reminder_id ){
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Internal Server Error',
		'message' => 'Errore durante la creazione del promemoria.',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Promemoria scadenza aggiunto con successo',
	'data'    => ['id' => $reminder_id],
]); 