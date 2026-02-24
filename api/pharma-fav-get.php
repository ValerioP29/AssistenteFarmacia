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

$user_id = get_my_id();
$fav_pharma = get_fav_pharma_by_user_id( $user_id );

echo json_encode([
	'code'   => 200,
	'status' => true,
	'message' => NULL,
	'data'   => normalize_pharma_data($pharma),
]);
