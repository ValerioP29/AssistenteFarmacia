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

$input = json_decode(file_get_contents("php://input"), TRUE);
$request_id = $input['id'] ?? NULL;
$request = RequestModel::findById($request_id);

if( empty($request) ){
	echo json_encode([
		'code'    => 200,
		'status'  => FALSE,
		'error'   => 'Not found',
		'message' => 'Richiesta non trovata.',
	]);
	exit();
}

$user_id = get_my_id();
$pharma = getMyPharma();

if( $request['user_id'] != $user['id'] OR $request['pharma_id'] != $pharma['id'] OR ! in_array($request['request_type'], ['promos', 'reservation']) ){
	echo json_encode([
		'code'    => 401,
		'status'  => FALSE,
		'error'   => 'Invalid action',
		'message' => 'Ordine non valido.',
	]);
	exit();
}

if( ! in_array($request['status'], [RequestModel::PENDING]) ){
	echo json_encode([
		'code'    => 401,
		'status'  => FALSE,
		'error'   => 'Invalid action',
		'message' => 'Questo ordine non può essere annullato.',
	]);
	exit();
}

//------------------------------------------------

RequestModel::delete($request_id);
$request_response = 'Prenotazione annullata con successo!';

//------------------------------------------------

$my_wa = get_my_wa();

$message = "⚠️ Hai annullato la seguente prenotazione.\n\n".$request['message']."";
$message = filter_comm_message( $message, get_my_id(), $pharma['id'], 'cancel-request--order' );

$wa_response = app_wa_send( $message );

echo json_encode([
	'code'      => 200,
	'status'    => TRUE,
	'message'   => $request_response,
]);