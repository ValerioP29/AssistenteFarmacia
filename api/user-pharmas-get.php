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
$user_pharmas = get_pharmas_followed_by_user_id( $user_id );
$others_pharmas = array_values(array_filter( $user_pharmas, function($_ph) use($fav_pharma) {
	return $_ph['id'] != $fav_pharma['id'];
} ));

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data'    => [
		'preferred' => normalize_pharma_data($fav_pharma),
		'followed' => array_map(function($_pharma){ return normalize_pharma_data($_pharma); }, $others_pharmas),
	],
]);
