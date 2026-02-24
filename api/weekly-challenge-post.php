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

$now   = new DateTime();
$start = new DateTime('00:00');
$end   = new DateTime('00:15');
if ($now >= $start && $now <= $end) {
	echo json_encode([
		'code'    => 404,
		'status'  => FALSE,
		'error'   => 'Midnight Challenge Maintenance Mode',
		'message' => 'La sfida della settimana è in pausa, torna tra 15min.',
	]);
	exit;
}

//------------------------------------------------

$challenge = ChallengesModel::getCurrentWeek((int) $pharma['id']);
if( ! $challenge ){
	echo json_encode([
		'code'    => 404,
		'status'  => FALSE,
		'error'   => 'Not Found',
		'message' => 'La nuova sfida non è ancora pronta.',
	]);
	exit;
}

$is_updated = ChallengeProgressModel::updateProgress($user['id'], $challenge['id']);
if( ! $is_updated ){
	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Error',
		'message' => 'C\'è stato un imprevisto. Riprova.',
	]);
	exit;
}
$count_progress = ChallengeProgressModel::getCompletedDaysCount($user['id'], $challenge['id']);
$message = 'Salvato';

$can_give_points = ! UserPointsModel::hasEntryForDate($user['id'], $pharma['id'], 'challenge_daily');
if( $can_give_points ){
	UserPointsModel::addPoints($user['id'], $pharma['id'], $challenge['points'], 'challenge_daily');
	$message = '+'.$challenge['points'];

	if( $count_progress == 5 ){
		UserPointsModel::addPoints($user['id'], $pharma['id'], 5, 'challenge_threshold');
		$message .= ' +5';
	}
}

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => $message,
	'data'    => NULL,
]);
