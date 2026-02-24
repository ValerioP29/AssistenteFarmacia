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

$pharma = getMyPharma();

//------------------------------------------------

$latest_orders = RequestModel::getByUserAndPharma(
	$user['id'],
	$pharma['id'],
	['promos', 'reservation'],
	NULL,
	100
);

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data'    => array_map( 'normalize_request_data', $latest_orders ),
]);
