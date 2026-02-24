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

$pharma = getMyPharma();
if( ! $pharma ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Farmacia non valida.',
	]);
	exit();
}

$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 28;
if( $limit < 1 ) $limit = 1;
if( $limit > 28 ) $limit = 28;

// Recupera le pillole più recenti
$pills = PillsModel::getLatest($limit, (int) $pharma['id']);

$my_args = get_my_profiling_args();
if( ! empty($my_args) ){
	$pills = array_values(array_filter( $pills, function($_pill) use ($my_args) {
		if( ! in_array( $_pill['category'], get_profiling_categories() ) ) return TRUE;
		return in_array( $_pill['category'], $my_args );
	} ));
}

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data'    => array_map('normalize_pill_data', $pills),
]);
