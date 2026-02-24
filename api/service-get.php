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

$service_id = $_GET['id'] ?? NULL;

// Richiesta mal formata
if( ! $service_id ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}

$service = get_service_by_id( $service_id );
if( ! $service ){
	echo json_encode([
		'code'    => 404,
		'status'  => FALSE,
		'error'   => 'Not Found',
		'message' => 'Servizio non trovato.',
	]);
	exit();
}

//------------------------------------------------

echo json_encode([
	'code'   => 200,
	'status' => true,
	'data'   => normalize_service_data($service),
]);
