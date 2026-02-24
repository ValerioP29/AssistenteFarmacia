<?php

// Funzione per validare il formato della data (YYYY-MM-DD)
function is_valid_date($date) {
	$d = DateTime::createFromFormat('Y-m-d', $date);
	return $d && $d->format('Y-m-d') === $date;
}

// Funzione per validare il formato della data (Y-m-d H:i:s)
function is_valid_datetime($date) {
	$d = DateTime::createFromFormat('Y-m-d H:i:s', $date);
	return $d && $d->format('Y-m-d H:i:s') === $date;
}

/**
 * Valida un numero di cellulare italiano senza prefisso internazionale.
 *
 * Deve iniziare con 3 e avere un totale di 9 o 10 cifre.
 * Accetta solo numeri senza prefisso internazionale (es. 3201234567).
 * Rimuove automaticamente spazi, punti e trattini.
 *
 * @param string $number Il numero da validare.
 * @return bool True se è un numero cellulare italiano valido.
 */
function is_valid_mobile(string $number): bool {
	// Rimuove spazi, trattini, punti
	$cleaned = preg_replace('/[\s\.\-]/', '', $number);

	// Rimuove prefisso internazionale se presente
	if (strpos($cleaned, '+39') === 0) {
		$cleaned = substr($cleaned, 3);
	} elseif (strpos($cleaned, '0039') === 0) {
		$cleaned = substr($cleaned, 4);
	}

	// Verifica: inizia con 3, poi 8 o 9 cifre (totale 9 o 10)
	if (!preg_match('/^3\d{8,9}$/', $cleaned)) {
		return false;
	}

	return true;
}

/**
 * Verifica la robustezza di una password secondo i criteri:
 * - lunghezza 6–15 caratteri
 * - almeno una lettera minuscola
 * - almeno una lettera maiuscola
 * - lettere accentate non sono permesse
 * - almeno un numero
 * - almeno un simbolo
 * - nessuno spazio
 * - nessuna emoji
 *
 * @param string $password
 * @return bool
 */
function is_strong_password($password) {

	$len = mb_strlen($password, 'UTF-8');
	if ($len < 6 || $len > 15) {
		return FALSE;
	}

	$symbols = '\|\'"!£\$%&\/\(\)=\?\^\[\]\*\+#,\.\-_:;<>';

	// regex per caratteri ammessi (lettere, cifre, simboli specifici)
	if (!preg_match('/^[' . 'A-Za-z0-9' . $symbols . ']+$/', $password)) {
		return FALSE;
	}

	// almeno una minuscola
	if (!preg_match('/[a-z]/', $password)) {
		return FALSE;
	}

	// almeno una maiuscola
	if (!preg_match('/[A-Z]/', $password)) {
		return FALSE;
	}

	// almeno un numero
	if (!preg_match('/[0-9]/', $password)) {
		return FALSE;
	}

	// almeno un simbolo dalla lista
	if (!preg_match('/[' . $symbols . ']/', $password)) {
		return FALSE;
	}

	return TRUE;
}


/**
 * Sanitizza un numero di telefono italiano.
 *
 * Restituisce il numero pulito composto solo da cifre.
 *
 * @param string $number Il numero da sanitizzare.
 * @return string Il numero sanitizzato.
 */
function sanitize_phone(string $number): string {
	// Rimuove tutti i caratteri non numerici
	$cleaned = preg_replace('/\D+/', '', $number);

	// Rimuove prefisso internazionale se presente
	// if (strpos($cleaned, '39') === 0 && strlen($cleaned) > 10) {
	//     $cleaned = substr($cleaned, 2);
	// } elseif (strpos($cleaned, '0039') === 0) {
	//     $cleaned = substr($cleaned, 4);
	// }

	return $cleaned;
}

function sanitize_string(string $value): string {
	$cleaned = trim($value);
	$cleaned = preg_replace('/\s+/', ' ', $cleaned);
	$cleaned = preg_replace("/[^a-zA-ZÀ-ÿ'\-\s]/u", '', $cleaned);
	return $cleaned;
}



function ltrim_tab( $text ){
	return preg_replace('/^\t+/m', '', $text);
}

function format_schedule_human_friendly(string $json): string {
	$data = json_decode($json, true);
	if (!is_array($data)) return 'Orario non disponibile.';

	$legacy_map = [
		'lun' => 'Lunedì', 'mar' => 'Martedì', 'mer' => 'Mercoledì',
		'gio' => 'Giovedì', 'ven' => 'Venerdì', 'sab' => 'Sabato', 'dom' => 'Domenica'
	];

	$output = [];

	foreach ($legacy_map as $key => $label) {
		if (!isset($data[$key]) || !is_array($data[$key])) {
			$output[] = "$label: informazioni mancanti.";
			continue;
		}

		$entry = $data[$key];

		if (!empty($entry['closed'])) {
			$output[] = "$label: Chiuso";
		} else {
			$morning = "{$entry['morning_open']}–{$entry['morning_close']}";
			$afternoon = "{$entry['afternoon_open']}–{$entry['afternoon_close']}";
			$output[] = "$label: $morning e $afternoon";
		}
	}

	return implode("\n", $output);
}

function format_schedule_compact(string $json): string {
	$data = json_decode($json, true);
	if (!is_array($data)) return 'Schedule not available.';

	$ordered_days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
	$day_names = [
		'mon' => 'Lunedì',
		'tue' => 'Martedì',
		'wed' => 'Mercoledì',
		'thu' => 'Giovedì',
		'fri' => 'Venerdì',
		'sat' => 'Sabato',
		'sun' => 'Domenica'
	];

	// Map legacy keys (lun, mar, ...) to standard keys
	$legacy_map = [
		'lun' => 'mon', 'mar' => 'tue', 'mer' => 'wed',
		'gio' => 'thu', 'ven' => 'fri', 'sab' => 'sat', 'dom' => 'sun'
	];
	$normalized_data = [];
	foreach ($legacy_map as $old => $new) {
		if (isset($data[$old])) {
			$normalized_data[$new] = $data[$old];
		}
	}

	$blocks = [];
	foreach ($ordered_days as $day) {
		$info = $normalized_data[$day] ?? ['closed' => true];
		$label = $info['closed']
			? 'Chiuso'
			: "{$info['morning_open']}-{$info['morning_close']} / {$info['afternoon_open']}-{$info['afternoon_close']}";
		$blocks[$label][] = $day;
	}

	$output = [];
	foreach ($blocks as $label => $days) {
		$groups = _group_consecutive_days($days);
		foreach ($groups as $group) {
			if (count($group) === 1) {
				$text = $day_names[$group[0]];
			} else {
				$text = $day_names[$group[0]] . '–' . $day_names[end($group)];
			}
			$output[] = "$text: $label";
		}
	}

	return implode("\n", $output);
}

function _group_consecutive_days(array $days): array {
	$order = ['mon','tue','wed','thu','fri','sat','sun'];
	$positions = array_flip($order);
	usort($days, fn($a, $b) => $positions[$a] <=> $positions[$b]);

	$groups = [];
	$current = [];

	foreach ($days as $i => $day) {
		if (empty($current)) {
			$current[] = $day;
		} else {
			$last = end($current);
			if ($positions[$day] === $positions[$last] + 1) {
				$current[] = $day;
			} else {
				$groups[] = $current;
				$current = [$day];
			}
		}
	}

	if (!empty($current)) $groups[] = $current;
	return $groups;
}

function write_log($message, $folder = __DIR__ . '/logs', $filename = 'app.log') {
	$folder = site_path() . '/logs/';

	if (!is_dir($folder)) {
		mkdir($folder, 0755, true);
	}

	// Se è un array o un oggetto, lo converte in stringa leggibile
	if (is_array($message) || is_object($message)) {
		$message = print_r($message, true);
	}

	$filepath = rtrim($folder, '/') . '/' . $filename;
	$timestamp = date('Y-m-d H:i:s');
	$entry = "[$timestamp] $message" . PHP_EOL;

	file_put_contents($filepath, $entry, FILE_APPEND | LOCK_EX);
}

function get_profiling_categories(){
	return [
		'Alimentazione e Nutrizione',
		'Benessere Fisico e Movimento',
		'Gestione dello Stress e del Sonno',
		'Salute e Prevenzione',
		'Cura della Pelle e Beauty Routine',
		'Supporto Cognitivo e Memoria',
		'Benessere Naturale',
		'Mamma e Bambino',
	];
}

function get_random_profiling_category(): string {
	$categories = get_profiling_categories();
	return $categories[array_rand($categories)];
}

/**
 * Escaping di testo per <textarea>
 */
function esc_textarea($text) {
	$safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	return $safe_text;
}

/**
 * Escaping di testo HTML (visibile all'utente)
 */
function esc_html($text) {
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escaping e sanificazione URL
 */
function esc_url($url) {
	// Rimuove caratteri non validi e codifica quelli necessari
	$url = filter_var($url, FILTER_SANITIZE_URL);
	return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

/**
 * Escaping di testo per attributi HTML
 */
function esc_attr($text) {
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Restituisce l'elenco dei punteggi in base alle azioni/altro
 */
function get_points_legend(){
	return [
		[
			'id'     => 'quiz_daily',
			'title'  => 'Quiz del giorno',
			'desc'   => 'Completare il quiz del giorno (punti attribuiti massimo una volta al giorno.',
			'value'  => 3,
			'hidden' => FALSE,
		],
		[
			'id'     => 'challenge_daily',
			'title'  => 'Sfida del benessere',
			'desc'   => 'Completare la sfida del benessere. Punti attribuiti massimo una volta al giorno.',
			'value'  => 1,
			'hidden' => FALSE,
		],
		[
			'id'     => 'challenge_threshold',
			'title'  => 'Sfida del benessere extra',
			'desc'   => 'Completare in una settimane la sfida del benessere almeno 5 volte.',
			'value'  => 5,
			'hidden' => FALSE,
		],
		[
			'id'     => 'login_daily',
			'title'  => 'Accesso giornaliero',
			'desc'   => 'Accedere all\'app ogni giorno. Punti attribuiti massimo una volta al giorno.',
			'value'  => 1,
			'hidden' => FALSE,
		],
		[
			'id'     => 'chatbot_daily',
			'title'  => 'Messaggio al chabot',
			'desc'   => 'Scrivere al chatbot almeno una volta al giorno. Punti attribuiti massimo una volta al giorno.',
			'value'  => 5,
			'hidden' => FALSE,
		],
		[
			'id'     => 'checkup_daily',
			'title'  => 'Checkup',
			'desc'   => 'Checkup. Punti attribuiti massimo una volta al giorno per ogni tipo di checkup.',
			'value'  => 1,
			'hidden' => FALSE,
		],
		[
			'id'     => 'request_completed',
			'title'  => 'Richiesta completata',
			'desc'   => 'Per prenotazione eventi, servizi, prodotti, ricette. I punti saranno attribuiti dopo il completo svolgimento della richiesta da parte della Farmacia.',
			'value'  => 10,
			'hidden' => FALSE,
		], 
		[
			'id'     => 'weekly_survey',
			'title'  => 'Sondaggio settimanale',
			'desc'   => 'Completa il sondaggio 1 volta a settimana per ottenere punti.',
			'value'  => 10,
			'hidden' => FALSE,
		],
	];
}

//function jta_send_email(string $to, string $subject, string $body, string $headers = ''): bool {
//   return mail($to, $subject, $body, $headers);
//}

function jta_send_email( string $to, string $subject, string $body, bool $isHtml = false, string $from = NULL, string $fromName = NULL ) {
	$mail = new PHPMailer\PHPMailer\PHPMailer(true);

	$from = $from ?? $_ENV['APP_EMAIL_ADDRESS'];
	$fromName = $fromName ?? $_ENV['APP_EMAIL_FROM_NAME'];

	try {
		// // Configurazione SMTP
		// $mail->isSMTP();
		// $mail->SMTPAuth   = true;
		// $mail->SMTPSecure = 'tls';
		// $mail->Host       = $_ENV['APP_EMAIL_SMTP_SERVER'];
		// $mail->Username   = $_ENV['APP_EMAIL_USER'];
		// $mail->Password   = $_ENV['APP_EMAIL_PSW'];
		// $mail->Port       = $_ENV['APP_EMAIL_SMTP_PORT'];

		// Mittente e destinatario
		$mail->setFrom($from, $fromName);
		$mail->addAddress($to);

		// Contenuto
		$mail->isHTML($isHtml);
		$mail->Subject = $subject;
		$mail->Body    = $body;
		if (!$isHtml) {
			$mail->AltBody = $body;
		}

		return @$mail->send();
	} catch (PHPMailer\PHPMailer\Exception $e) {
		// error_log("Errore invio email: {$mail->ErrorInfo}");
		return FALSE;
	}
}

function generate_unique_string( $length = 12 ){
	$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
	$characters_length = strlen($characters);
	$random_string = '';
	for ($i = 0; $i < $length; $i++) {
		$random_string .= $characters[random_int(0, $characters_length - 1)];
	}
	return $random_string;
}

function filter_comm_message( $message = '', $user_id = NULL, $pharma_id = NULL, $source = NULL ){
	$user = get_user_by_id($user_id);

	if( $user && ( $user['is_tester'] == 1 OR $user['ref'] === 'fiera' ) ){
		$message = "MESSAGGIO UTENTE DEMO/TEST\n\n".$message;
	}

	return $message;
}


