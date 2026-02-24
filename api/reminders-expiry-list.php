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

$reminders = get_reminders_expiry_by_user_id( $user['id'] );

echo json_encode([
	'code'   => 200,
	'status' => true,
	'data'   => array_map( function($_reminder){
		return normalize_reminder_expiry_data($_reminder);
	}, $reminders ),
]); 