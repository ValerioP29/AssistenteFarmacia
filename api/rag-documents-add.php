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

$content = $input['content'] ?? '';
$filename = $input['filename'] ?? '';
$metadata = $input['metadata'] ?? [];

if( empty(trim($content)) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Il contenuto del documento è obbligatorio',
	]);
	exit();
}

$response = rag_add_document($content, $filename, $metadata);

echo json_encode($response); 