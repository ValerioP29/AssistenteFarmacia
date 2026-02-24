<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if( ! $user ){
	echo json_encode([
		'code'    => 401,
		'status'  => FALSE,
		'error'   => 'Invalid or expired token',
		'message' => 'Accesso negato',
	]);
	exit();
}

//------------------------------------------------

$event_id = $_GET['id'] ?? NULL;

// Richiesta mal formata
if( ! $event_id ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}

$event = get_event_by_id( $event_id );
if( ! $event ){
	echo json_encode([
		'code'    => 404,
		'status'  => FALSE,
		'error'   => 'Not Found',
		'message' => 'Evento non trovato.',
	]);
	exit();
}

//------------------------------------------------

echo json_encode([
	'code'   => 200,
	'status' => true,
	'data'   => normalize_event_data($event),
]);
