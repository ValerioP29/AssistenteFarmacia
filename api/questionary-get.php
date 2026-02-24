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

// Recupera i dati del questionario dall'utente
$profiling_data = null;
if( ! empty($user['init_profiling']) ){
	$profiling_data = json_decode($user['init_profiling'], true);
}

if( ! $profiling_data ){
	echo json_encode([
		'code'    => 404,
		'status'  => false,
		'error'   => 'Not Found',
		'message' => 'Nessun dato del questionario trovato per questo utente.',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Dati del questionario recuperati con successo',
	'data'    => $profiling_data
]); 