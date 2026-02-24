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

$pharma = getMyPharma();
if( ! $pharma ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Farmacia non valida.',
	]);
	exit;
}

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

$user_progress = ChallengeProgressModel::get($user['id'], $challenge['id']);

function get_week_progress_array(): array {
	$days = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
	$today = strtolower(date('D')); // esempio: 'mon', 'tue', ...
	
	// mappa inglese -> italiano abbreviato
	$map = [
		'mon' => 'lun',
		'tue' => 'mar',
		'wed' => 'mer',
		'thu' => 'gio',
		'fri' => 'ven',
		'sat' => 'sab',
		'sun' => 'dom'
	];

	$today_it = $map[$today];
	$found_today = false;

	$result = [];
	foreach ($days as $day) {
		if (!$found_today && $day === $today_it) {
			$found_today = true;
			$result[$day] = 0;
		} elseif ($found_today) {
			$result[$day] = 0;
		} else {
			$result[$day] = 1;
		}
	}

	return $result;
}

$challenge = normalize_challenge_data($challenge);
$user_progress = ChallengeProgressModel::normalizeProgress($user_progress);

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data'    => array_merge($challenge, $user_progress),
]);
