<?php
require_once('_api_bootstrap.php');
setHeadersAPI();

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$stage  = $input['stage'] ?? '';
$phone  = isset($input['phone']) ? sanitize_phone($input['phone']) : null;
$otp    = $input['otp'] ?? null;
$new1   = $input['new_password'] ?? null;
$new2   = $input['confirm_password'] ?? null;
$user   = get_user_by_wa($phone);

if (!$user) return out(404, 'Utente non trovato');

const DEMO_OTP = '12345'; 

if ($stage === 'start') {
	if (!$phone || !is_valid_mobile($phone)) return out(400, 'Numero non valido');
	if (!preg_match('/^39\d{7,15}$/', $phone)) $phone = '39' . $phone;

	return out(200, 'OTP inviato. (demo: 12345)', ['next' => 'verify']);
}

if ($stage === 'verify') {
	if (!$phone || !$otp) return out(400, 'Dati insufficienti');
	if (!preg_match('/^39\d{7,15}$/', $phone)) $phone = '39' . $phone;

	if ($otp !== DEMO_OTP) return out(400, 'OTP errato');

	$payload = [
		'sub'  => (int)$user['id'],     
		'type' => 'password_reset',
		'exp'  => time() + 10 * 60      
	];
	$token = getJwtEncoded($payload);

	return out(200, 'OTP verificato', ['next' => 'reset', 'reset_token' => $token]);
}

if ($stage === 'reset') {
	/*$token = $input['reset_token'] ?? '';
	if (!$token) return out(400, 'Token mancante');

	$payload = getJwtDecoded($token);
	if (!$payload) return out(400, 'Token non valido o scaduto');
	if (($payload->type ?? '') !== 'password_reset') return out(400, 'Token non valido');

	$userId = (int)($payload->sub ?? 0);
	if ($userId <= 0) return out(400, 'Token non valido'); */

	if (!$new1 || !$new2) return out(400, 'Inserisci la nuova password');
	if ($new1 !== $new2) return out(400, 'Le password non coincidono');
	if (mb_strlen($new1) < 4) return out(400, 'La password deve avere almeno 4 caratteri');

	if (!update_user($user['id'], ['password' => $new1])) {
		return out(500, 'Impossibile aggiornare la password');
	}

	return out(200, "Password aggiornata.\nAccedi di nuovo.", ['done' => true]);
}

return out(400, 'Stage non supportato');


/* ---------------- Helpers ---------------- */

function out($code, $msg, $data = null, $error = 'Error') {
	$params = [
		'code'    => $code,
		'status'  => $code < 300,
		'message' => $msg,
		'data'    => $data,
	];
	if ($params['code']>= 300) {
		$params['error'] = $error;
	}
	echo json_encode($params);
	
	exit;
}
