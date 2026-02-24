<?php
require_once('_api_bootstrap.php');
setHeadersAPI();

$input = json_decode(file_get_contents("php://input"), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

$user = get_user_by_username($username);

if( ! $user || ! password_verify($password, $user['password']) ){
	echo json_encode([
		'code'    => 401,
		'status'  => false,
		'error'   => 'Invalid credentials',
		'message' => 'Dati di accesso non validi',
	]);
	exit;
}

update_user( $user['id'], [
	'last_access' => date('Y-m-d H:i:s')
] );

// Access token (1h)
$access_payload = [
	'sub'      => $user['id'],
	'username' => $user['slug_name'],
	'exp'      => time() + getJWTtimelife(),
];

$access_token = getJwtEncoded($access_payload);

$refresh_token = generateRefreshToken();
insertAuthRefreshToken( $user['id'], $refresh_token );

$pharma = get_fav_pharma_by_user_id($user['id']);
$can_give_points = ! UserPointsModel::hasEntryForDate($user['id'], $pharma['id'], 'login_daily');
if( $can_give_points ) UserPointsModel::addPoints($user['id'], $pharma['id'], 1, 'login_daily');

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => NULL,

	'user_id'       => $user['id'],
	'access_token'  => $access_token,
	'refresh_token' => $refresh_token,
]);
