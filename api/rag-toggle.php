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
$action = $_SERVER['REQUEST_METHOD'];

if ($action === 'GET') {
    // Restituisce lo stato attuale del RAG
    $status = defined('RAG_ENABLED') ? RAG_ENABLED : true;
    
    echo json_encode([
        'code' => 200,
        'status' => true,
        'data' => [
            'rag_enabled' => $status,
            'message' => $status ? 'RAG è attualmente abilitato' : 'RAG è attualmente disabilitato'
        ]
    ]);
    
} elseif ($action === 'POST') {
    // Cambia lo stato del RAG
    $enable = $input['enable'] ?? null;
    
    if ($enable === null) {
        echo json_encode([
            'code' => 400,
            'status' => false,
            'error' => 'Bad Request',
            'message' => 'Parametro "enable" richiesto (true/false)',
        ]);
        exit();
    }
    
    // Nota: Per un controllo dinamico completo, dovresti salvare in database o file
    // Questo è un esempio semplificato che richiede riavvio del server
    echo json_encode([
        'code' => 200,
        'status' => true,
        'data' => [
            'rag_enabled' => $enable,
            'message' => $enable ? 'RAG abilitato (riavvia il server per applicare)' : 'RAG disabilitato (riavvia il server per applicare)',
            'note' => 'Per un controllo dinamico completo, modifica la costante RAG_ENABLED in helpers/rag_helpers.php'
        ]
    ]);
    
} else {
    echo json_encode([
        'code' => 405,
        'status' => false,
        'error' => 'Method Not Allowed',
        'message' => 'Solo GET e POST sono supportati',
    ]);
} 