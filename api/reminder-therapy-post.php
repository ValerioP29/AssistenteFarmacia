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
$medication_name = $_POST['medicationName'] ?? FALSE;
$dosage          = $_POST['dosage'] ?? FALSE;
$start_date      = $_POST['startDate'] ?? FALSE;
$end_date        = $_POST['endDate'] ?? FALSE;
$frequency       = $_POST['frequency'] ?? FALSE;
$times           = $_POST['times'] ?? FALSE;
$notes           = $_POST['notes'] ?? NULL;

// Gestione file upload
$uploaded_file = $_FILES['file'] ?? null;
$file_path = null;

// Validazione campi obbligatori
if( ! $medication_name || ! $dosage || ! $start_date || ! $end_date || ! $frequency || ! $times ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'Campi obbligatori mancanti.',
	]);
	exit();
}

// Validazione frequenza
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

// Validazione date
if( $end_date <= $start_date ){
	echo json_encode([
		'code'    => 400,
		'status'  => false,
		'error'   => 'Bad Request',
		'message' => 'La data di fine deve essere successiva alla data di inizio.',
	]);
	exit();
}

// Validazione e salvataggio file se presente
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

$reminder_data = [
	'medicationName' => $medication_name,
	'dosage'         => $dosage,
	'startDate'      => $start_date,
	'endDate'        => $end_date,
	'frequency'      => $frequency,
	'times'          => $times,
	'notes'          => $notes,
	'file'           => $file_path
];

$reminder_id = create_reminder_therapy( $user['id'], $reminder_data );

if( ! $reminder_id ){
	// Se c'è stato un errore e il file è stato salvato, lo elimino
	if( $file_path ){
		deleteUploadedFile($file_path);
	}
	
	echo json_encode([
		'code'    => 500,
		'status'  => false,
		'error'   => 'Internal Server Error',
		'message' => 'Errore durante la creazione del promemoria.',
	]);
	exit();
}

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => 'Promemoria terapia aggiunto con successo',
	'data'    => [
		'id' => $reminder_id,
		'file' => $file_path
	],
]); 