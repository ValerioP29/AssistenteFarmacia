<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
$decoded = protectFileWithJWT();

$user_id = $decoded->sub;
$user = get_user_by_id($user_id);
$pharma = getMyPharma();

if( ! $user ){
	echo json_encode([
		'code'    => 401,
		'status'  => false,
		'error'   => 'Invalid or expired token',
		'message' => 'Accesso negato',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Access granted',
	'message' => 'Access granted',
	'user'   => normalize_user_data($user),
	'pharma' => normalize_pharma_data($pharma),
]);
