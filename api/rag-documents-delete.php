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

$input = json_decode(file_get_contents("php://input"), true);

$document_id = $input['document_id'] ?? '';

if( empty(trim($document_id)) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'L\'ID del documento è obbligatorio',
	]);
	exit();
}

$response = rag_remove_document($document_id);

echo json_encode($response); 