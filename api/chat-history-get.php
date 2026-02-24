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

$user_id = get_my_id();
$session_id = $_GET['sessionId'] ?? null;
$limit = (int)($_GET['limit'] ?? 50);

try {
	if( $session_id ){
		// Recupera lo storico di una sessione specifica
		$history = get_chat_history($user_id, $session_id, $limit);
		
		echo json_encode([
			'code'    => 200,
			'status'  => true,
			'message' => 'Storico sessione recuperato con successo',
			'data' => [
				'sessionId' => $session_id,
				'history' => $history,
				'total' => count($history)
			]
		]);
	} else {
		// Recupera le sessioni recenti dell'utente
		$recent_history = ChatHistoryModel::getRecentHistory($user_id, $limit);
		
		// Raggruppa per sessione
		$sessions = [];
		foreach( $recent_history as $entry ){
			$session_id = $entry['session_id'];
			if( ! isset($sessions[$session_id]) ){
				$sessions[$session_id] = [
					'sessionId' => $session_id,
					'lastMessage' => $entry['content'],
					'lastMessageRole' => $entry['role'],
					'lastMessageTime' => $entry['created_at'],
					'totalMessages' => 0,
					'messages' => []
				];
			}
			$sessions[$session_id]['messages'][] = $entry;
			$sessions[$session_id]['totalMessages']++;
		}
		
		// Ordina per ultimo messaggio
		usort($sessions, function($a, $b) {
			return strtotime($b['lastMessageTime']) - strtotime($a['lastMessageTime']);
		});
		
		echo json_encode([
			'code'    => 200,
			'status'  => true,
			'message' => 'Sessioni recenti recuperate con successo',
			'data' => [
				'sessions' => array_values($sessions),
				'total' => count($sessions)
			]
		]);
	}
	
} catch (Exception $e) {
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Internal Server Error',
		'message' => 'Errore nel recupero dello storico: ' . $e->getMessage(),
	]);
} 