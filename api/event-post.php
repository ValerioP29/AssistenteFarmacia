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

$event_id   = $input['id'] ?? NULL;
$datetime   = $input['datetime'] ?? FALSE;
$request    = $input['request'] ?? FALSE;

// Richiesta mal formata
if( ! ( ( $event_id && $datetime ) OR $request ) ){
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
		'message' => 'Servizio non trovato.',
	]);
	exit();
}

//------------------------------------------------

$my_wa = get_my_wa();
$pharma = getMyPharma();

$event_date = date('d/m/Y', strtotime($event['datetime_start']));

$request_response = 'Ti confermiamo che la farmacia è stata informata della tua richiesta. Ti avviseremo quando la tua richiesta sarà confermata.';
$message = "Hai prenotato per: {$event['title']}\n\n📅 {$event_date}\n⏰ Orario scelto: {$datetime}\n\n{$request_response}";

$message = filter_comm_message( $message, get_my_id(), $pharma['id'], 'request--event' );

$request_id = RequestModel::insert([
	'request_type' => 'event',
	'user_id'      => get_my_id(),
	'pharma_id'    => $pharma['id'],
	'message'      => $message,
]);

if( ! $request_id ){
	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Error',
		'message' => 'La prenotazione evento non è stata salvata. Riprova.',
	]);
	exit;
}

$wa_response = app_wa_send( $message );

$points_awarded = 0;
$points_value = (int) get_option('point--event_booking', 10);
if( $points_value > 0 ){
	$added = UserPointsModel::addPoints((int) $user['id'], (int) $pharma['id'], $points_value, 'event_booking');
	if( $added ) $points_awarded = $points_value;
}


echo json_encode([
	'code'      => 200,
	'status'    => TRUE,
	'message'   => $request_response,
	'data'      => [
		'request_id' => (int) $request_id,
		'points_awarded' => $points_awarded,
	],
]);
