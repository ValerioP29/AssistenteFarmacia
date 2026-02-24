<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if (!$user) {
	echo json_encode([
		'code'    => 401,
		'status'  => false,
		'error'   => 'Invalid or expired token',
		'message' => 'Accesso negato',
	]);
	exit();
}

$fresh = get_user_by_id((int)$user['id']);
if (!$fresh) {
	echo json_encode([
		'code'    => 404,
		'status'  => false,
		'error'   => 'Not Found',
		'message' => 'Utente non trovato',
	]);
	exit();
}

echo json_encode([
	'code'   => 200,
	'status' => true,
	'data'   => normalize_user_profile_data($fresh),
]);