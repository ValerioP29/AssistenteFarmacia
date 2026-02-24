<?php
require_once('_api_bootstrap.php');
setHeadersAPI();

//------------------------------------------------

$pharma_slug = $_GET['id'] ?? NULL;

// Richiesta mal formata
if( ! $pharma_slug ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}

$pharma = get_pharma_by_slug( $pharma_slug );

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
