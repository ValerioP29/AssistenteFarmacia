<?php
require_once('_api_bootstrap.php');
require_once('helpers/checkup_helpers.php');
setHeadersAPI();

$decoded = protectFileWithJWT();
$user = get_my_data();

if( ! $user ){
	echo json_encode([
		'code'    => 401,
		'status'  => false,
		'error'   => 'Invalid or expired token',
		'message' => 'Accesso negato',
	]);
	exit();
}

//------------------------------------------------

// Ricevi il payload JSON
$raw_input = file_get_contents("php://input");
$input = json_decode($raw_input, true);

// Validazione input usando il helper
$validation = validate_checkup_input($input);
if( ! $validation['valid'] ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => implode(', ', $validation['errors']),
	]);
	exit();
}

$prompt = $input['prompt'];
$image_data = $input['image'] ?? NULL;
$image_format = $input['imageFormat'] ?? NULL;
$checkup_type = $input['checkupType'] ?? NULL;

// Analizza l'immagine usando il helper
$response = analyze_checkup_image($prompt, $image_data, $image_format);

if( ! $response ){
	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Error',
		'message' => 'Errore nell\'analisi dell\'immagine. Riprova.',
	]);
	exit();
}

// Prepara la risposta usando il helper
$final_response = format_checkup_response($prompt, $response, !empty($image_data));

$valid_checkup_ids = [
	'mani',
	'occhi',
	'viso',
	'capelli',
	'labbra',
	'armocromia',
];
if( ! empty($checkup_type) && in_array($checkup_type, $valid_checkup_ids) ){
	$pharma = getMyPharma();
	$can_give_points = ! UserPointsModel::hasEntryForDate($user['id'], $pharma['id'], 'checkup_daily--'.$checkup_type);
	if( $can_give_points ){
		UserPointsModel::addPoints($user['id'], $pharma['id'], 1, 'checkup_daily--'.$checkup_type);
		$final_response['message'] = '+1';
	}
}

echo json_encode($final_response); 