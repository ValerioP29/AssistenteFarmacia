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

$data = [
	'points'        => (int) $user['points_current_month'],
	'goal'          => 500,
	'rewardText'    => 'Un\'esclusiva sorpresa da scoprire in farmacia...',
	'rewardNote'    => '* Fino ad esaurimento scorte...',
	'rewardImage'   => rtrim(site_url(), '/') . '/uploads/images/week-challenge.jpg',
	'points_legend' => get_points_legend(),
];

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data'    => $data,
]);
