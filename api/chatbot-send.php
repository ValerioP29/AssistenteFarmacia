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

$message = $input['message'] ?? NULL;
$quick_action = $input['quickAction'] ?? FALSE;
$image_data = $input['image'] ?? NULL;
$image_format = $input['imageFormat'] ?? NULL;
$session_id = $input['sessionId'] ?? NULL;

$use_rag = get_option('ai_rag_enabled', FALSE);
$use_quickaction = get_option('ai_quickaction_enabled', FALSE);
$use_history = get_option('ai_chat_history_enabled', TRUE); // Abilitato di default

$pharma = getMyPharma();

if( ! $use_quickaction ) $quick_action = FALSE;

// Gestione sessione e storico
if( ! $session_id ){
	$session_id = generate_chat_session_id();
}

$user_id = get_my_id();
$pharma_id = $pharma['id'] ?? 1;

// DEBUG: Log per verificare il sessionId
error_log("CHATBOT DEBUG - Session ID: " . ($session_id ?? 'NULL'));
error_log("CHATBOT DEBUG - User ID: " . ($user_id ?? 'NULL'));
error_log("CHATBOT DEBUG - Use History: " . ($use_history ? 'TRUE' : 'FALSE'));

// Parametri per lo storico
$history_params = null;
if( $use_history && $user_id && $pharma_id && $session_id ){
	$history_params = [
		'use_history' => true,
		'user_id' => $user_id,
		'pharma_id' => $pharma_id,
		'session_id' => $session_id
	];
	error_log("CHATBOT DEBUG - History params: " . json_encode($history_params));
} else {
	error_log("CHATBOT DEBUG - History params: NULL");
}

// QuickAction presente, ma mal formata
if( $quick_action && (
	(! isset($quick_action['type'], $quick_action['action'] )
	OR
	! in_array($quick_action['type'], ['request'])
) ) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida, riprova. Come posso aiutarti?',
	]);
	exit();
}

// QuickAction non presente, ma nemmeno il messaggio
if( ! $quick_action && trim($message) == '' ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Richiesta non valida, riprova. Come posso aiutarti?',
	]);
	exit();
}

// Validazione immagine se presente
$validated_image_data = null;
if( $image_data ){
	$image_validation = validateImageBase64($image_data, $image_format);

	if( ! $image_validation['valid'] ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Immagine non conforme: ' . $image_validation['error'],
		]);
		exit();
	}
	$validated_image_data = $image_data; // Usa il base64 originale
}

	if( $quick_action ){
		$user_prompt = $message;

		if( $quick_action['type'] == 'request' ){
			switch($quick_action['action'] ){
				case 'getPharmaHours': $user_prompt = 'Sai dirmi gli orari di apertura della farmacia?'; break;
				case 'getPharmaLocation': $user_prompt = 'Dove si trova la farmacia?'; break;
				case 'getPharmaServices': $user_prompt = 'Quali sono i servizi disponibili della farmacia?'; break;
				case 'getDrugInfo': $user_prompt = 'Dammi qualche informazione sulla Tachipirina'; break;
			}
		}

		if( $use_rag ){
			$response = hybrid_chatbot($user_prompt, ['use_rag' => $use_rag]);
		}else{
			// Se c'è un'immagine, usa il prompt ridotto
			if( $validated_image_data ){
				$system_prompt = get_openai_chatbot_prompt( $pharma, $user, $user_prompt, true );
				$response = openai_new_chatbot_request($user_prompt, $system_prompt, $validated_image_data, $history_params);
			} else {
				// Costruisco il PROMPT completo solo per testo
				$system_prompt = get_openai_chatbot_prompt( $pharma, $user, $user_prompt, false );
				$response = openai_new_chatbot_request($user_prompt, $system_prompt, $validated_image_data, $history_params);
			}
		}
	}else{
		$user_prompt = $message;

		if( $use_rag ){
			$response = hybrid_chatbot($user_prompt, ['use_rag' => $use_rag]);
		}else{
			// Se c'è un'immagine, usa il prompt ridotto
			if( $validated_image_data ){
				$system_prompt = get_openai_chatbot_prompt( $pharma, $user, $user_prompt, true );
				$response = openai_new_chatbot_request($user_prompt, $system_prompt, $validated_image_data, $history_params);
			} else {
				// Costruisco il PROMPT completo solo per testo
				$system_prompt = get_openai_chatbot_prompt( $pharma, $user, $user_prompt, false );
				$response = openai_new_chatbot_request($user_prompt, $system_prompt, $validated_image_data, $history_params);
			}
		}
	}



if( ! $response ){
	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Error',
		'message' => 'Errore imprevisto. Contatta l\'assistenza o riprova.',
	]);
	exit();
}

$can_give_points = ! UserPointsModel::hasEntryForDate($user['id'], $pharma['id'], 'chatbot_daily');
if( $can_give_points ){
	UserPointsModel::addPoints($user['id'], $pharma['id'], get_option('points_chatbot_daily', 5), 'chatbot_daily');
}

$final_response = [
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data' => [
		'id' => generateUniqueId(),
		'sessionId' => $session_id,
		'message' => $response['risposta_html'],
		'quickAction' => ( ! $use_quickaction ) ? [] : [
			// 'hint' => 'Vuoi sapere di più su Autan?',
			'actions' => [
				// [
				// 	'label'  => 'Sito ASL',
				// 	'type'   => 'navigation',
				// 	'target' => 'https://www.salutelazio.it/',
				// 	'action' => 'linkPage',
				// ],
				// [
				// 	'label'  => 'Vedi le promo',
				// 	'type'   => 'navigation',
				// 	'target' => 'promotions',
				// 	'action' => 'appPage',
				// ],
				// [
				// 	'label' => 'Acquista',
				// 	'type'  => 'action',
				// 	'value' => [
				// 		'id'    => 1,
				// 		'label' => 'Tachipirina'
				// 	],
				// 	'action' => 'buyPromo'
				// ],
			],
		],
	],
];



echo json_encode($final_response);
