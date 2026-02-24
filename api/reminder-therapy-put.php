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

// Gestione dati da multipart/form-data
$reminder_id = $_POST['id'] ?? FALSE;
$medication_name = $_POST['medicationName'] ?? FALSE;
$dosage = $_POST['dosage'] ?? FALSE;
$start_date = $_POST['startDate'] ?? FALSE;
$end_date = $_POST['endDate'] ?? FALSE;
$frequency = $_POST['frequency'] ?? FALSE;
$times = $_POST['times'] ?? FALSE;
$notes = $_POST['notes'] ?? NULL;

// Gestione file upload
$uploaded_file = $_FILES['file'] ?? null;
$file_path = null;

// Validazione ID
if( ! $reminder_id ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'ID promemoria mancante.',
	]);
	exit();
}

// Verifica che il promemoria esista e appartenga all'utente
$existing_reminder = get_reminder_therapy_by_id($reminder_id, $user['id']);
if( ! $existing_reminder ){
	echo json_encode([
		'code'    => 404,
		'status'  => false,
		'error'   => 'Not Found',
		'message' => 'Promemoria non trovato.',
	]);
	exit();
}

// Validazione frequenza se fornita
if( $frequency ){
	$valid_frequencies = ['daily', 'twice_daily', 'three_times', 'weekly', 'custom'];
	if( ! in_array($frequency, $valid_frequencies) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Frequenza non valida.',
		]);
		exit();
	}
}

// Validazione date se fornite
if( $start_date && $end_date && $end_date <= $start_date ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'La data di fine deve essere successiva alla data di inizio.',
	]);
	exit();
}

// Validazione e salvataggio nuovo file se presente
if( $uploaded_file && $uploaded_file['error'] !== UPLOAD_ERR_NO_FILE ){
	$file_errors = validateUploadedFile($uploaded_file);
	
	if( ! empty($file_errors) ){
		echo json_encode([
			'code'    => 400,
			'status'  => false,
			'error'   => 'Bad Request',
			'message' => 'Errore nel file: ' . implode(', ', $file_errors),
		]);
		exit();
	}
	
	try {
		$file_path = saveUploadedFile($uploaded_file, $user['id'], 'terapies');
		
		// Elimina il vecchio file se esiste
		if( $existing_reminder['file'] ){
			deleteUploadedFile($existing_reminder['file']);
		}
		
	} catch (Exception $e) {
		echo json_encode([
			'code'    => 500,
			'status'  => false,
			'error'   => 'Internal Server Error',
			'message' => 'Errore nel salvataggio del file: ' . $e->getMessage(),
		]);
		exit();
	}
}

//------------------------------------------------

// Prepara i dati per l'aggiornamento (usa i valori esistenti se non forniti)
$update_data = [
	'medicationName' => $medication_name ?: $existing_reminder['drug_name'],
	'dosage'         => $dosage ?: $existing_reminder['dosage'],
	'startDate'      => $start_date ?: $existing_reminder['start_date'],
	'endDate'        => $end_date ?: $existing_reminder['end_date'],
	'frequency'      => $frequency ?: $existing_reminder['frequency'],
	'times'          => $times ?: $existing_reminder['times'],
	'notes'          => $notes !== NULL ? $notes : $existing_reminder['notes'],
	'file'           => $file_path ?: $existing_reminder['file']
];

$success = update_reminder_therapy($reminder_id, $user['id'], $update_data);

if( ! $success ){
	// Se c'è stato un errore e il nuovo file è stato salvato, lo elimino
	if( $file_path ){
		deleteUploadedFile($file_path);
	}
	
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Internal Server Error',
		'message' => 'Errore durante l\'aggiornamento del promemoria.',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Promemoria terapia aggiornato con successo',
	'data'    => [
		'id' => $reminder_id,
		'file' => $update_data['file']
	],
]); 