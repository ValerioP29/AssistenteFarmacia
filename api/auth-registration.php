<?php
require_once('_api_bootstrap.php');
setHeadersAPI();

$input = json_decode(file_get_contents("php://input"), true);
$username = isset($input['username']) ? $input['username'] : '';
$password = isset($input['password']) ? $input['password'] : '';
$ref      = isset($input['ref']) ? mb_strtolower($input['ref']) : '';
$phone = $input['phone'] ?? '';
$email       = isset($input['email']) ? mb_strtolower(trim($input['email'])) : '';
$first_name  = isset($input['first_name']) ? ucwords(sanitize_string(trim($input['first_name']))) : '';
$last_name   = isset($input['last_name']) ? ucwords(sanitize_string(trim($input['last_name']))) : '';
$consents = $input['consents'] ?? [];
$acceptMarketing = !empty($consents['accept_marketing']) ? 1 : 0;

if( get_option('registration_disabled', true) ){
	echo json_encode([
		'code'    => 401,
		'status'  => FALSE,
		'error'   => 'Deny',
		'message' => get_option('registration_disabled_msg', 'Le registrazioni sono temporaneamente disattivate.'),
	]);
	exit;
}

if( ! $username || ! $password || ! $phone ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Compila tutti i campi',
	]);
	exit;
}

if( ! preg_match('/^[a-zA-Z0-9]+$/', $username) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Lo username può contenere solo lettere semplici e numeri',
	]);
	exit;
}

if (!is_valid_email($email)) {
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Email non valida.',
	]);
	exit;
}

$phone = sanitize_phone($phone);
if( ! is_valid_mobile($phone) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Numero di telefono non valido',
	]);
	exit;
}

if( mb_strlen($password) < 4 ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'La password è troppo corta',
	]);
	exit;
}

if( get_user_by_username($username) ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Nome utente già in uso',
	]);
	exit;
}

if ( get_user_by_email($email)) {
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Email già in uso.',
	]);
	exit;
}

if (!$first_name || !$last_name) {
    echo json_encode([
        'code'    => 400,
        'status'  => false,
        'error'   => 'Bad Request',
        'message' => 'Nome e cognome sono obbligatori.',
    ]);
    exit;
}

if (mb_strlen($first_name) < 2 || mb_strlen($first_name) > 25 ||
    mb_strlen($last_name) < 2 || mb_strlen($last_name) > 25) {
    echo json_encode([
        'code'    => 400,
        'status'  => false,
        'error'   => 'Bad Request',
        'message' => 'Nome e cognome devono avere tra 2 e 25 caratteri.',
    ]);
    exit;
}




$phone = '39' . $phone;

if( get_option('registration_phone_is_unique', true) ){
	if( get_user_by_wa($phone) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Numero WhatsApp già in uso',
		]);
		exit;
	}
}

$pharma_id = 1; // Farmacia Giovinazzi default
$user_id = insert_user([
	'slug_name'    => $username,
	'password'     => $password,
	'phone_number' => $phone,
	'pharma_id'    => $pharma_id,
	'email'        => $email,
	'name'         => $first_name,
	'surname'      => $last_name,
	'accept_marketing' => $acceptMarketing,
]);

if( ! $user_id ){
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Error',
		'message' => 'La registrazione non è andata a buon fine. Riprova o contatta l\'assistenza.',
	]);
	exit;
}

if( ! empty($ref) ) update_user( $user_id, ['ref' => $ref ] );
insertUserPharmaRel( $user_id, $pharma_id, 1 );

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Registrazione effettuata con successo.',
	'data'    => NULL,
]);
