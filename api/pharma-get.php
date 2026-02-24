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

$pharma_id = $_GET['id'] ?? NULL;

// Richiesta mal formata
if( ! $pharma_id ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}

$pharma = get_pharma_by_id( $pharma_id );
if( ! $pharma ){
	echo json_encode([
		'code'    => 404,
		'status'  => FALSE,
		'error'   => 'Not Found',
		'message' => 'Farmacia non trovata.',
	]);
	exit();
}

//------------------------------------------------

echo json_encode([
	'code'   => 200,
	'status' => true,
	'data'   => normalize_pharma_data($pharma),
]);
