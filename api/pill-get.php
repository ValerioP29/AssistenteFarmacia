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

// Recupera il parametro "giorno"
$date = $_GET['giorno'] ?? NULL;
// Recupera il parametro "id"
$id = $_GET['id'] ?? NULL;

// Richiesta mal formata
if ( ! $date && ! $id ) {
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
		// 'message' => 'Parametro "giorno" mancante o non valido. Formato richiesto: YYYY-MM-DD.',
	]);
	exit();
}

if ( $date && !is_valid_date($date) ) {
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}

if ( $date ) {
	$daily_pill = PillsModel::findByDate($date, (int) $pharma['id']);
}elseif ( $id ) {
	$daily_pill = PillsModel::findById($id, (int) $pharma['id']);
}else{
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}


if( ! $daily_pill ){
	echo json_encode([
		'code'    => 404,
		'status'  => FALSE,
		'error'   => 'Not Found',
		'message' => 'Pillola non trovata.',
	]);
	exit();
}

// $daily_pills = PillsModel::findGroupByDate($date);
// if( empty($daily_pills) ){
// 	echo json_encode([
// 		'code'    => 404,
// 		'status'  => FALSE,
// 		'error'   => 'Not Found',
// 		'message' => 'Pillola non trovata.',
// 	]);
// 	exit();
// }

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data'    => normalize_pill_data($daily_pill),
	// 'pills'   => array_map('normalize_pill_data', $daily_pills),
]);
