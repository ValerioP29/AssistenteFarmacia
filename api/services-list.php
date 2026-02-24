<?php
require_once('_api_bootstrap.php');
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

$pharma = getMyPharma();
$services = get_services((int) $pharma['id']);

echo json_encode([
	'code'   => 200,
	'status' => true,
	'data'   => array_map( function($_service){
		return normalize_service_data($_service);
	}, $services ),
]);
