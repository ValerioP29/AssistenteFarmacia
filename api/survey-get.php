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

$now = date('Y-m-d H:i:s');

$survey_id  = isset($_GET['id']) ? intval($_GET['id']) : FALSE;
// $survey = $survey_id ? SurveysModel::findById($survey_id) : SurveysModel::findByDate($now);
if( $survey_id ){
	$survey = SurveysModel::findById($survey_id, (int) $pharma['id']);
}else{
	$surveys = SurveysModel::getAllOpen($now, (int) $pharma['id']);
	$survey = $surveys[0] ?? FALSE;
}

if( ! $survey ){
	echo json_encode([
		'code'    => 404,
		'status'  => false,
		'error  ' => 'Survey not found.',
		'message' => 'Sondaggio non trovato.',
	]);
	exit();
}

if( $survey['start_date'] && $now < $survey['start_date'] ){
	echo json_encode([
		'code'    => 410,
		'status'  => false,
		'error  ' => 'Survey not started.',
		'message' => 'Il sondaggio non è ancora disponibile.',
	]);
	exit();
}

if( $survey['end_date'] && $now > $survey['end_date'] ){
	echo json_encode([
		'code'    => 410,
		'status'  => false,
		'error  ' => 'Survey expired.',
		'message' => 'Il sondaggio è terminato.',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => null,
	'data'    => normalize_survey_data($survey),
]);
