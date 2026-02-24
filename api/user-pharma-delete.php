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

$input = json_decode(file_get_contents("php://input"), true);
$pharma_id = $input['id'] ?? NULL;
$pharma = get_pharma_by_id($pharma_id);

if( ! $pharma ){
	echo json_encode([
		'code'    => 404,
		'status'  => FALSE,
		'error'   => 'Not Found',
		'message' => 'Farmacia nontrovata',
	]);
	exit();
}

$user_id = get_my_id();
$curr_fav_pharma = get_fav_pharma_by_user_id( $user_id );

if( $curr_fav_pharma['id'] == $pharma_id ){
	echo json_encode([
		'code'    => 401,
		'status'  => FALSE,
		'error'   => 'Invalid action',
		'message' => 'Non puoi eliminare la tua farmacia preferita.',
	]);
	exit();
}

$result = deleteUserPharmaRel( $user_id, $pharma_id );

if( ! $result ){
	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Errore imprevisto. Riprova.',
		'message' => 'Errore imprevisto. Riprova.',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => 'Farmacia rimossa dai preferiti.',
]);
