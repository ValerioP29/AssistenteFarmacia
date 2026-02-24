<?php
require_once('_api_bootstrap.php');
setHeadersAPI();

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$stage  = $input['stage'] ?? '';
$email  = isset($input['email']) ? strtolower(trim($input['email'])) : null;
$otp    = $input['otp'] ?? null;
$new1   = $input['new_password'] ?? null;
$new2   = $input['confirm_password'] ?? null;
if (!$email || !is_valid_email($email)) {
	return out(400, 'Email non valida');
}

$user = get_user_by_email($email);
if (!$user) return out(404, 'Utente non trovato');
if ( $user['status'] == 'verify' ) return out(403, 'Il tuo account non è stato ancora verificato.');
if ( $user['status'] != 'active' ) return out(404, 'Utente non trovato');

// if ($user['id'] < 1 || $user['id'] > 5) {
//     return out(403, 'Cambio password non disponibile. Riprova domani!');
// }

// Config OTP
const OTP_TTL_SECONDS  = 900;
const OTP_MAX_ATTEMPTS = 5;
const OTP_LENGTH       = 6;
const RATE_LIMIT_SEC   = 60;
$demo_mode = is_localhost();



if ($stage === 'start') {
	$last = get_latest_otp_for_email($email);
	if ($last && (time() - strtotime($last['created_at'])) < RATE_LIMIT_SEC) {
		$wait = RATE_LIMIT_SEC - (time() - strtotime($last['created_at']));
		return out(429, "Hai già fatto una richiesta, attendi {$wait}s.");
	}

	$otp_code = str_pad((string)random_int(0, pow(10, OTP_LENGTH)-1), OTP_LENGTH, '0', STR_PAD_LEFT);
	$expires  = (new DateTime("+" . OTP_TTL_SECONDS . " seconds", new DateTimeZone('Europe/Rome')))->format('Y-m-d H:i:s');
	$now      = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d H:i:s');

	create_email_otp($user['id'], $email, $otp_code, $expires, $now);

	if ($demo_mode) {
		$msg = "Codice inviato (demo: {$otp_code})";
	} else {
		$ok = send_reset_otp_email($email, $otp_code);
		if (!$ok) return out(500, 'Impossibile inviare l’email di reset, riprova più tardi');
		$msg = "Codice inviato alla tua email";
	} 
	return out(200, $msg, ['next' => 'verify']);
}

if ($stage === 'verify') {
	if (!$otp) {
		return out(400, 'Dati insufficienti');
	}

	$row = get_active_email_otp($email, $user['id']);
	if (!$row) return out(400, 'Codice non trovato o scaduto');

	if (strtotime($row['expires_at']) < time()) {
		return out(400, 'Codice non trovato o scaduto');
	}

	if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
		return out(429, 'Troppi tentativi di inserimento. Richiedi un nuovo codice');
	}

	if ($otp !== $row['otp_code']) {
		bump_email_otp_attempts($row['id']);
		return out(400, 'Codice errato');
	}

	mark_email_otp_used($row['id']);

	$payload = [
		'sub'  => (int)$user['id'],
		'type' => 'password_reset',
		'exp'  => time() + (5 * 60)
	];
	$token = getJwtEncoded($payload);

	return out(200, 'Codice verificato', ['next' => 'reset', 'reset_token' => $token]);
}

if ($stage === 'reset') {
	$token = $input['reset_token'] ?? '';
	if (!$token) return out(400, 'Token mancante');

	$payload = getJwtDecoded($token);
	if (!$payload) return out(400, 'Token non valido o scaduto');
	if (($payload->type ?? '') !== 'password_reset') return out(400, 'Token non valido');

	$userId = (int)($payload->sub ?? 0);
	if ($userId <= 0) return out(400, 'Token non valido');

	if (!$new1 || !$new2) return out(400, 'Inserisci la nuova password');
	if ($new1 !== $new2) return out(400, 'Le password non coincidono');
	if (mb_strlen($new1) < 6) {
		return out(400, 'La password deve avere almeno 6 caratteri');
	}
	if (!is_strong_password($new1)) {
		return out(400, 'La password deve contenere almeno 1 maiuscola, 1 minuscola, 1 numero e 1 simbolo. Non sono ammessi spazi, lettere accentate ed emoji');
	}
	if (!update_user($userId, ['password' => $new1])) {
		return out(500, 'Impossibile aggiornare la password');
	}

	return out(200, 'Password aggiornata. Accedi di nuovo.', ['done' => true]);
}

return out(400, 'Stage non supportato');

/*helpers*/

function out($code, $msg, $data = null, $error = 'Error') {
	$resp = [
		'code'    => $code,
		'status'  => $code < 300,
		'message' => $msg,
		'data'    => $data,
	];
	if ($code >= 300) $resp['error'] = $error;
	echo json_encode($resp);
	exit;
}



function create_email_otp(int $user_id, string $email, string $otp, string $expires_at, string $now): void {
	global $pdo;
	$stmt = $pdo->prepare("INSERT INTO jta_password_resets (user_id, email, otp_code, expires_at, attempts, used, created_at, sent_via)
						VALUES (:uid, :em, :otp, :exp, 0, 0, :now, 'email')");
	$stmt->execute([
		':uid' => $user_id,
		':em'  => $email,
		':otp' => $otp,
		':exp' => $expires_at,
		':now' => $now
	]);
}

function get_latest_otp_for_email(string $email): ?array {
	global $pdo;
	$stmt = $pdo->prepare("SELECT * FROM jta_password_resets WHERE email = :em ORDER BY id DESC LIMIT 1");
	$stmt->execute([':em' => $email]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

function get_active_email_otp(string $email, int $user_id): ?array {
	global $pdo;
	$stmt = $pdo->prepare("SELECT * FROM jta_password_resets
						WHERE email = :em AND user_id = :uid AND used = 0
						ORDER BY id DESC LIMIT 1");
	$stmt->execute([':em' => $email, ':uid' => $user_id]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

function bump_email_otp_attempts(int $row_id): void {
	global $pdo;
	$pdo->prepare("UPDATE jta_password_resets SET attempts = attempts + 1 WHERE id = :id")
		->execute([':id' => $row_id]);
}

function mark_email_otp_used(int $row_id): void {
	global $pdo;
	$pdo->prepare("UPDATE jta_password_resets SET used = 1 WHERE id = :id")
		->execute([':id' => $row_id]);
}

function send_reset_otp_email(string $to, string $otp): bool {
	$user = get_user_by_email($to);
	if (!$user) return FALSE;

	$subject = "Codice cambio password #".time();
	$body    = "Ciao \"".esc_html($user['slug_name'])."\", inserisci il seguente codice nell'app per cambiare la password del tuo account: {$otp}<br>Scade tra 10 minuti.<br>Se non hai fatto tu questa richiesta, ignora questa email.";

	return jta_send_email($to, $subject, $body, true);
}

