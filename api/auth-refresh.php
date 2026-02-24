<?php
require_once('_api_bootstrap.php');
setHeadersAPI();

$input = json_decode(file_get_contents("php://input"), true);
$refresh_token = $input['refresh_token'] ?? '';

$row = searchValidAuthRefreshToken($refresh_token);
// Invalida token usato
setAsInvalidAuthRefreshToken($refresh_token);

if( ! $row ){
	echo json_encode([
		'code'    => 401,
		'status'  => false,
		'error'   => 'Invalid or expired refresh token',
		'message' => 'Dati di accesso non validi',
	]);
	exit;
}

$user = get_user_by_id($row['user_id']);

if( ! $user ){
	echo json_encode([
		'code'    => 401,
		'status'  => false,
		'error'   => 'Invalid or expired refresh token',
		'message' => 'Dati di accesso non validi',
	]);
	exit;
}


// Nuovo refresh token
$new_refresh_token = generateRefreshToken();
insertAuthRefreshToken( $user['id'], $new_refresh_token );

// Access token
$access_payload = [
	'sub'      => $user['id'],
	'username' => $user['slug_name'],
	'exp'      => time() + getJWTtimelife(),
];
$access_token = getJwtEncoded($access_payload);

$pharma = get_fav_pharma_by_user_id($user['id']);
$can_give_points = ! UserPointsModel::hasEntryForDate($user['id'], $pharma['id'], 'login_daily');
if( $can_give_points ) UserPointsModel::addPoints($user['id'], $pharma['id'], 1, 'login_daily');

echo json_encode([
	'code' => 200,
	'status' => true,
	'message' => NULL,

	'user_id'       => $user['id'],
	'access_token'  => $access_token,
	'refresh_token' => $new_refresh_token
]);
