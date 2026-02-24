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

// Recupera l'ID utente dal parametro GET
$target_user_id = $_GET['user_id'] ?? NULL;

// Se non viene specificato un ID, usa quello dell'utente autenticato
if( ! $target_user_id ){
	$target_user_id = $user['id'];
}

// Verifica che l'utente autenticato stia verificando i propri dati o abbia i permessi
if( $target_user_id != $user['id'] ){
	echo json_encode([
		'code'    => 403,
		'status'  => false,
		'error'   => 'Forbidden',
		'message' => 'Non puoi verificare lo stato del questionario di altri utenti.',
	]);
	exit();
}

// Recupera i dati dell'utente target
$target_user = get_user_by_id( $target_user_id );
if( ! $target_user ){
	echo json_encode([
		'code'    => 404,
		'status'  => false,
		'error'   => 'Not Found',
		'message' => 'Utente non trovato.',
	]);
	exit();
}

// Verifica se il campo init_profiling è popolato
$has_profiling = ! empty($target_user['init_profiling']);

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Stato questionario recuperato con successo',
	'data'    => [
		'has_profiling' => $has_profiling,
		'user_id'       => $target_user_id
	]
]); 