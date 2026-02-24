<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if (!$user) {
	echo json_encode([
		'code'    => 401,
		'status'  => false,
		'error'   => 'Invalid or expired token',
		'message' => 'Accesso negato',
	]);
	exit();
}

//------------------------------------------------

// Gestione dati da multipart/form-data o JSON
$input_data = [];
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
	$input_data = json_decode(file_get_contents("php://input"), true);
} else {
	$input_data = $_POST;
}

// Consensi
if (isset($input_data['consents'])) {
    $consents = $input_data['consents'] ?? [];

    $acceptMarketing = !empty($consents['accept_marketing']) ? 1 : 0;

    $result = update_user($user['id'], ['accept_marketing' => $acceptMarketing]);
    $message = 'Consensi aggiornati';
}


// Per password
elseif (isset($input_data['password'])) {
	$pwd = (string)$input_data['password'];

	if (mb_strlen($pwd, 'UTF-8') < 6) {
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'La password deve avere almeno 6 caratteri',
		]);
		exit;
	}

	if (!is_strong_password($pwd)) {
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'La password deve contenere almeno 1 maiuscola, 1 minuscola, 1 numero e 1 simbolo. Non sono ammessi spazi, lettere accentate ed emoji',
		]);
		exit;
	}

	$result = update_user($user['id'], ['password' => $pwd]);
	$message = 'Password aggiornata';

} elseif (isset($input_data['basic'])) {
	$basic = $input_data['basic'];

	$name    = ucwords(sanitize_string(trim($basic['name']    ?? '')));
	$surname = ucwords(sanitize_string(trim($basic['surname'] ?? '')));
	$email   = mb_strtolower(trim($basic['email'] ?? ''));

	$current = get_user_by_id($user['id']);

	$toUpdate = [];

	if (!empty($name)) {
		if (empty($current['name'])) {
			$toUpdate['name'] = mb_substr($name, 0, 100);
		} else {
			echo json_encode([
				'code'    => 409,
				'status'  => false,
				'error'   => 'Conflict',
				'message' => 'Il nome è già presente e non può essere modificato.',
			]);
			exit;
		}
	}

	if (!empty($surname)) {
		if (empty($current['surname'])) {
			$toUpdate['surname'] = mb_substr($surname, 0, 100);
		} else {
			echo json_encode([
				'code'    => 409,
				'status'  => false,
				'error'   => 'Conflict',
				'message' => 'Il cognome è già presente e non può essere modificato.',
			]);
			exit;
		}
	}

	if (!empty($email)) {
		if (empty($current['email'])) {
			if (!is_valid_email($email)) {
				echo json_encode([
					'code'    => 400,
					'status'  => false,
					'error'   => 'Bad Request',
					'message' => 'Email non valida',
				]);
				exit;
			}

			$existing = get_user_by_email($email);
			if ($existing && (int)$existing['id'] !== (int)$user['id']) {
				echo json_encode([
					'code'    => 409,
					'status'  => false,
					'error'   => 'Conflict',
					'message' => 'Non puoi utilizzare questo indirizzo email.',
				]);
				exit;
			}

			$toUpdate['email'] = mb_substr($email, 0, 255);
		} else {
			echo json_encode([
				'code'    => 409,
				'status'  => false,
				'error'   => 'Conflict',
				'message' => 'L’email è già presente e non può essere modificata.',
			]);
			exit;
		}
	}


	if (empty($toUpdate)) {
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Nessun dato valido da aggiornare.',
		]);
		exit;
	}

	$result  = update_user($user['id'], $toUpdate);
	$message = 'Dati anagrafici aggiornati';


}else{
	$input_data = $input_data['init_profiling'];
	if( ! isset($input_data['genere'], $input_data['fascia_eta'], $input_data['lifestyle'], $input_data['argomenti']) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Compila tutti i campi della profilazione.',
		]);
		exit();
	}

	// Validazione "genere"
	$input_data['genere'] = ucwords($input_data['genere']);
	$valid_genders = ['Maschio', 'Femmina'];
	if( ! in_array($input_data['genere'], $valid_genders) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Genere non valido. Valori consentiti: '.implode(', ', $valid_genders),
		]);
		exit();
	}

	// Validazione "fascia d'età"
	$valid_ages = ['18-30', '30-50', '50-70', '70+'];
	if( ! in_array($input_data['fascia_eta'], $valid_ages) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Fascia età non valida. Valori consentiti: '.implode(', ', $valid_ages),
		]);
		exit();
	}

	// Validazione "lifestyle"
	$valid_lifestyles = ['Poco equilibrato', 'Abbastanza equilibrato', 'Molto equilibrato'];
	if( ! in_array($input_data['lifestyle'], $valid_lifestyles) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Stile di vita non valido. Valori consentiti: '.implode(', ', $valid_lifestyles),
		]);
		exit();
	}

	// Validazione "argomenti"
	$valid_argoments = [
		'Alimentazione e Nutrizione',
		'Benessere Fisico e Movimento',
		'Gestione dello Stress e del Sonno',
		'Salute e Prevenzione',
		'Cura della Pelle e Beauty Routine',
		'Supporto Cognitivo e Memoria',
		'Benessere Naturale',
		'Mamma e Bambino',
	];
	if( ! is_array($input_data['argomenti']) OR ! ( count($input_data['argomenti']) >= 1 && count($input_data['argomenti']) <= 3 ) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Devi scegliere 1-3 argomenti di interesse.',
		]);
		exit();
	}
	foreach( $input_data['argomenti'] AS $_argoment ){
		if( ! in_array($_argoment, $valid_argoments) ){
			echo json_encode([
				'code'    => 400,
				'status'  => false,
				'error'   => 'Bad Request',
				'message' => 'Argomenti di interesse non validi. Valori consentiti: '.implode(', ', $valid_argoments),
			]);
			exit();
		}
	}

	//------------------------------------------------

	$params = [
		'genere'     => $input_data['genere'],
		'fascia_eta' => $input_data['fascia_eta'],
		'lifestyle'  => $input_data['lifestyle'],
		'argomenti'  => $input_data['argomenti'],
	];

	$result = update_user_init_profiling($user['id'], $params);
	$message = 'Profilo aggiornato';

}

//------------------------------------------------

if( ! $result ){
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Error',
		'message' => 'Errore. Riprova.',
	]);
	exit();
}

// Recupera l'utente aggiornato per la risposta
$updated_user = get_user_by_id($user['id']);

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => $message,
	'data'    => normalize_user_profile_data($updated_user),
]);
