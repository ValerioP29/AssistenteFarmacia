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

$input = json_decode(file_get_contents("php://input"), TRUE);

$reminder_id = $input['id'] ?? FALSE;

// Validazione ID
if( ! $reminder_id ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'ID promemoria mancante.',
	]);
	exit();
}

// Verifica che il promemoria esista e appartenga all'utente
$existing_reminder = get_reminder_expiry_by_id( $reminder_id, $user['id'] );
if( ! $existing_reminder ){
	echo json_encode([
		'code'    => 404,
		'status'  => false,
		'error'   => 'Not Found',
		'message' => 'Promemoria non trovato.',
	]);
	exit();
}

// Elimina il file associato se esiste
if ($existing_reminder['file'] && file_exists('uploads/' . $existing_reminder['file'])) {
    unlink('uploads/' . $existing_reminder['file']);
}

//------------------------------------------------

$deleted = delete_reminder_expiry( $reminder_id, $user['id'] );

if( ! $deleted ){
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Internal Server Error',
		'message' => 'Errore durante l\'eliminazione del promemoria.',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Promemoria scadenza eliminato con successo',
]); 