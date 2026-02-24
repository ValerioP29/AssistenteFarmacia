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

$type    = $input['type'] ?? FALSE;
$request = $input['request'] ?? FALSE;
if( ! in_array($type, ['service', 'promo']) ){
	$type = FALSE;
	$message = 'Puoi richiedere solo per servizi e promozioni.';
}

// Richiesta mal formata
if( ! $type OR ! $request ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => $message ?? 'Richiesta non valida.',
	]);
	exit();
}

$message = 'Richiesta ricevuta, grazie. Ti faremo sapere.';

$request = trim($request);
$request = preg_replace("/(\r?\n){3,}/", "\n\n", $request);
// $request = str_replace(['*', '_'], ['**', '__'], $request);

$request_response = 'Ti confermiamo che la farmacia è stata informata della tua richiesta. Ti avviseremo quando la tua richiesta sarà confermata.';

switch($type){
	case 'service': $human_type = 'servizi'; break;
	case 'promo': $human_type = 'promozioni'; break;
	$human_type = 'elementi';
}

$message = <<<EOT
Per $human_type non in elenco
$request

💬 $request_response
EOT;

//------------------------------------------------

$my_wa = get_my_wa();
$pharma = getMyPharma();

if( $type == 'promo' ) $type = 'promos';

$message = filter_comm_message( $message, get_my_id(), $pharma['id'], 'request--custom-'.$type.'' );

RequestModel::insert([
	'request_type' => $type,
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
