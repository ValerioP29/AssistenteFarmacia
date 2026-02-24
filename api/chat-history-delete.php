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

$raw_input = file_get_contents("php://input");
$input = json_decode($raw_input, true);

$session_id = $input['sessionId'] ?? null;
$delete_all = $input['deleteAll'] ?? false;

$user_id = get_my_id();

try {
	if( $delete_all ){
		// Elimina tutto lo storico dell'utente
		$success = ChatHistoryModel::deleteUserHistory($user_id);
		
		if( $success ){
			echo json_encode([
				'code'    => 200,
				'status'  => true,
				'message' => 'Tutto lo storico è stato eliminato con successo',
				'data' => [
					'deleted' => 'all'
				]
			]);
		} else {
			echo json_encode([
				'code'    => 500,
				'status'  => false,
				'error'   => 'Delete Error',
				'message' => 'Errore nell\'eliminazione dello storico',
			]);
		}
	} elseif( $session_id ){
		// Elimina una sessione specifica
		$success = delete_chat_session($user_id, $session_id);
		
		if( $success ){
			echo json_encode([
				'code'    => 200,
				'status'  => true,
				'message' => 'Sessione eliminata con successo',
				'data' => [
					'deleted' => 'session',
					'sessionId' => $session_id
				]
			]);
		} else {
			echo json_encode([
				'code'    => 500,
				'status'  => false,
				'error'   => 'Delete Error',
				'message' => 'Errore nell\'eliminazione della sessione',
			]);
		}
	} else {
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Parametri mancanti: sessionId o deleteAll',
		]);
	}
	
} catch (Exception $e) {
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Internal Server Error',
		'message' => 'Errore nell\'eliminazione: ' . $e->getMessage(),
	]);
} 