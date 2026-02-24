<?php
require_once('_api_bootstrap.php');
setHeadersAPI();

// $decoded = protectFileWithJWT();
// $user = get_my_data();

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

$pharma = get_pharma_by_id( $pharma_slug );

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

$pharma_data = normalize_pharma_data($pharma);
$profile_file = 'pharma_profiles/'.$pharma_slug.'.php';

if( ! file_exists($profile_file) ){
	echo json_encode([
		'code'    => 404,
		'status'  => FALSE,
		'error'   => 'Not Found',
		'message' => 'Farmacia non trovata.',
	]);
	exit();
}

ob_start();
include_once($profile_file);
$pharma_data['profile'] = ob_get_clean();
$pharma_data['turni'] = [];

if( $pharma_slug == 1 ){
	$pharma_data['turni'] = [ '2025-09-03', '2025-09-13', '2025-09-23', '2025-10-03', '2025-10-13', '2025-10-23', '2025-11-02', '2025-11-12', '2025-11-22', '2025-12-02', '2025-12-12', '2025-12-22' ];
}

echo json_encode([
	'code'   => 200,
	'status' => true,
	'data'   => $pharma_data,
]);
