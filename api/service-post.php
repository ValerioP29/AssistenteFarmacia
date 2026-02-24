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

$service_id = $input['id'] ?? NULL;
$datetime   = $input['datetime'] ?? FALSE;
$request    = $input['request'] ?? FALSE;

// Richiesta mal formata
if( ! ( ( $service_id && $datetime ) OR $request ) ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}

if( $service_id ){
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

	$title = $service['title'];
	$human_date = date('d-m-Y H:i', strtotime($datetime));
	$request_response = 'Ti confermiamo che la farmacia è stata informata della tua richiesta. Ti avviseremo quando la tua richiesta sarà confermata.';

$message = <<<EOT
📅 Prenotazione Servizio
🛎️ Servizio: $title
📆 Data e Ora: $human_date

💬 $request_response
EOT;

}elseif( $request){
	$request = trim($request);
	$request = preg_replace("/(\r?\n){3,}/", "\n\n", $request);
	// $request = str_replace(['*', '_'], ['**', '__'], $request);

	$request_response = 'Ti confermiamo che la farmacia è stata informata della tua richiesta. Ti avviseremo quando la tua richiesta sarà confermata.';

$message = <<<EOT
📅 Per servizio non in elenco
$request

💬 $request_response
EOT;
}

//------------------------------------------------

$my_wa = get_my_wa();
$pharma = getMyPharma();

if( $service_id ){
	$message = filter_comm_message( $message, get_my_id(), $pharma['id'], 'request--service' );
}elseif( $request ){
	$message = filter_comm_message( $message, get_my_id(), $pharma['id'], 'request--custom-service' );
}

RequestModel::insert([
	'request_type' => 'service',
	'user_id'      => get_my_id(),
	'pharma_id'    => $pharma['id'],
	'message'      => $message,
]);


$wa_response = app_wa_send( $message );



echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => $request_response,
]);
exit();