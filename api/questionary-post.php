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

$input = json_decode(file_get_contents("php://input"), TRUE);

// Validazione campi obbligatori
$user_id = $input['user_id'] ?? NULL;
$genere = $input['genere'] ?? NULL;
$fascia_eta = $input['fascia_eta'] ?? NULL;
$lifestyle = $input['lifestyle'] ?? NULL;
$argomenti = $input['argomenti'] ?? NULL;

// Verifica che l'utente stia salvando i propri dati
if( $user_id != $user['id'] ){
	echo json_encode([
		'code'    => 403,
		'status'  => false,
		'error'   => 'Forbidden',
		'message' => 'Non puoi salvare dati per altri utenti.',
	]);
	exit();
}

// Validazione campi obbligatori
if( ! $genere || ! $fascia_eta || ! $lifestyle || ! $argomenti ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Campi obbligatori mancanti: genere, fascia_eta, lifestyle, argomenti.',
	]);
	exit();
}

// Validazione genere
$valid_generi = ['Maschio', 'Femmina', 'Altro'];
if( ! in_array($genere, $valid_generi) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Genere non valido. Valori ammessi: Maschio, Femmina, Altro.',
	]);
	exit();
}

// Validazione fascia età
$valid_fasce_eta = ['18-30', '30-50', '50-70', '70+'];
if( ! in_array($fascia_eta, $valid_fasce_eta) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Fascia età non valida. Valori ammessi: 18-30, 30-50, 50-70, 70+.',
	]);
	exit();
}

// Validazione lifestyle
$valid_lifestyles = ['Molto equilibrato', 'Abbastanza equilibrato', 'Poco equilibrato'];
if( ! in_array($lifestyle, $valid_lifestyles) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Lifestyle non valido. Valori ammessi: Molto equilibrato, Abbastanza equilibrato, Poco equilibrato.',
	]);
	exit();
}

// Validazione argomenti (deve essere un array)
if( ! is_array($argomenti) || empty($argomenti) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Argomenti deve essere un array non vuoto.',
	]);
	exit();
}

// Validazione argomenti specifici
$valid_argomenti = [
	'Alimentazione e Nutrizione',
	'Benessere Fisico e Movimento',
	'Gestione dello Stress e del Sonno',
	'Salute e Prevenzione',
	'Cura della Pelle e Beauty Routine',
	'Supporto Cognitivo e Memoria',
	'Benessere Naturale',
	'Mamma e Bambino',
];

foreach( $argomenti as $argomento ){
	if( ! in_array($argomento, $valid_argomenti) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Argomento non valido: ' . $argomento . '. Valori ammessi: ' . implode(', ', $valid_argomenti),
		]);
		exit();
	}
}

//------------------------------------------------

// Prepara i dati per il salvataggio
$profiling_data = [
	'user_id'     => $user_id,
	'genere'      => $genere,
	'fascia_eta'  => $fascia_eta,
	'lifestyle'   => $lifestyle,
	'argomenti'   => $argomenti,
	'created_at'  => date('Y-m-d H:i:s')
];

// Salva i dati nel database
$success = update_user_init_profiling( $user['id'], $profiling_data );

if( ! $success ){
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Internal Server Error',
		'message' => 'Errore durante il salvataggio dei dati del questionario.',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Dati del questionario salvati con successo',
	'data'    => $profiling_data
]); 