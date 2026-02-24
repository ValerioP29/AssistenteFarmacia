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

$reminder_id = $_GET['id'] ?? FALSE;

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

//------------------------------------------------

$reminder = get_reminder_therapy_by_id( $reminder_id, $user['id'] );

if( ! $reminder ){
	echo json_encode([
		'code'    => 404,
		'status'  => false,
		'error'   => 'Not Found',
		'message' => 'Promemoria non trovato.',
	]);
	exit();
}

echo json_encode([
	'code'   => 200,
	'status' => true,
	'data'   => normalize_reminder_therapy_data($reminder),
]); 