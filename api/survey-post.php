<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if (!$user) {
	echo json_encode([
		'code' => 401,
		'status' => false,
		'message' => 'Accesso negato'
	]);
	exit();
}

$input = json_decode(file_get_contents("php://input"), true);

$survey_id = isset($input['survey_id']) ? (int)$input['survey_id'] : null;
$profile   = isset($input['profile']) ? strtoupper(trim($input['profile'])) : null;
$counts    = isset($input['counts']) && is_array($input['counts']) ? $input['counts'] : null;

if (!$survey_id || !$profile || !$counts) {
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}

$valid_profiles = ['A','B','C','D'];
if (!in_array($profile, $valid_profiles)) {
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida.',
	]);
	exit();
}

$pharma = getMyPharma();
$pharma_id = isset($pharma['id']) ? (int) $pharma['id'] : null;
if( ! $pharma_id ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Farmacia non valida.',
	]);
	exit();
}
$user_id   = (int) $user['id'];

global $pdo; 

try {
	$sql = "INSERT INTO jta_surveys 
		(pharma_id, user_id, survey_id, profile, counts, created_at, updated_at)
		VALUES (:pharma_id, :user_id, :survey_id, :profile, :counts, NOW(), NOW())";

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':pharma_id' => $pharma_id,
		':user_id'   => $user_id,
		':survey_id' => $survey_id,
		':profile'   => $profile,
		':counts'    => json_encode($counts, JSON_UNESCAPED_UNICODE),
	]);

	$points = get_option('point--survey_weekly', 10);
	$can_give_points = ! UserPointsModel::hasEntryForWeek($user['id'], $pharma['id'], 'survey_weekly');
	if( $can_give_points ) UserPointsModel::addPoints($user['id'], $pharma['id'], $points, 'survey_weekly');

	echo json_encode([
		'code' => 200,
		'status' => true,
		'message' => $can_give_points ? ('+'.$points) : 'Risultati del sondaggio salvati correttamente',
		'data' => [
			'id' => $pdo->lastInsertId(),
			'profile' => $profile,
			'counts' => $counts
		]
	]);
} catch (Exception $e) {
	write_log('Errore jta_surveys: '.$e->getMessage());
	echo json_encode([
		'code' => 500,
		'status' => false,
		'message' => 'Errore durante il salvataggio del sondaggio.'
	]);
}
