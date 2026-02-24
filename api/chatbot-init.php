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

// $input = json_decode(file_get_contents("php://input"), true);

$use_rag = get_option('ai_rag_enabled', FALSE);
$use_quickaction = get_option('ai_quickaction_enabled', FALSE);

if( $use_quickaction ){
	$resp_quickaction = [
		// [
		// 	'label'  => 'Sito ASL',
		// 	'type'   => 'navigation',
		// 	'target' => 'https://www.salutelazio.it/',
		// 	'action' => 'linkPage',
		// ],
		// [
		// 	'label'  => 'Vedi gli eventi',
		// 	'type'   => 'navigation',
		// 	'target' => 'events',
		// 	'action' => 'appPage',
		// ],
		[
			'label'  => '🕓 Orari Farmacia',
			'type'   => 'request',
			'action' => 'getPharmaHours',
		],
		[
			'label'  => '📍 Dove Siamo',
			'type'   => 'request',
			'action' => 'getPharmaLocation',
		],
		// [
		// 	'label'  => '📋 Servizi Disponibili',
		// 	'type'   => 'request',
		// 	'action' => 'getPharmaServices',
		// ],
		// [
		// 	'label'  => 'Info Tachipirina',
		// 	'type'   => 'request',
		// 	'value'  => '5',
		// 	'action' => 'getDrugInfo',
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
		// [
		// 	'label'  => 'Acquista',
		// 	'type'   => 'request',
		// 	'value'  => '',
		// 	'action' => 'getPharmaHours',
		// ],
	];

	echo json_encode([
		'code'    => 200,
		'status'  => TRUE,
		'message' => NULL,
		'data' => [
			'quickAction' => [
				'hint' => 'Ciao! Sono l\'assistente virtuale della tua farmacia.<br>Come posso aiutarti? Se vuoi puoi scegliere una delle opzioni qui sotto.',
				'actions' => $resp_quickaction,
			],
		],
	]);

}else{
	echo json_encode([
		'code'    => 200,
		'status'  => true,
		'message' => 'Ciao! Sono l\'assistente virtuale della tua farmacia.<br>Come posso aiutarti?',
		'data' => [
			'quickAction' => [
				'hint' => 'Ciao! Sono l\'assistente virtuale della tua farmacia.<br>Come posso aiutarti?',
			],
		],
	]);
}
