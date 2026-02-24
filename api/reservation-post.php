<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if( ! $user ){
	echo json_encode([
		'code'    => 401,
		'status'  => FALSE,
		'error'   => 'Invalid or expired token',
		'message' => 'Accesso negato',
	]);
	exit();
}

$pharma = getMyPharma();

function parse_boolish($value){
	if (is_bool($value)) return $value;
	if ($value === null) return false;
	if (is_int($value) || is_float($value)) return (int)$value === 1;
	$value = strtolower(trim((string)$value));
	return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function parse_json_array($value){
	if (!is_string($value) || trim($value) === '') return [];
	$decoded = json_decode($value, true);
	if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return [];
	return $decoded;
}

//------------------------------------------------

$input = $_POST;
$files = $_FILES;

$products = parse_json_array($input['products'] ?? '');
$pickup   = $input['pickup'] ?? null;
$note     = $input['note'] ?? '';
$urgent   = parse_boolish($input['urgent'] ?? false);
$salta_fila = parse_boolish($input['salta_fila'] ?? false);
$delivery = parse_boolish($input['delivery'] ?? false);

$items = $products ?? [];
if( empty($products) ){
	echo json_encode([
		'code'    => 200,
		'status'  => FALSE,
		'error'   => 'No items',
		'message' => 'Non hai inserito prodotti.',
	]);
	exit();
}

$valid_products = [];
$errors_arr = [];

foreach ($products AS $_idx => $_item) {
	$_prescription = FALSE;

	$has_name = isset($_item['name']) && ! empty(trim($_item['name']));
	$has_qty = isset($_item['qty']) && ! empty(trim($_item['qty']));

	if( ! $has_name ) $errors_arr[] = 'Il prodotto #'.$_idx.' non ha un nome.';
	if( $has_qty != 1 ) $errors_arr[] = 'Il prodotto '.($_item['name'] ?? '#'.$_idx).' ha una quantità non valida.';

	if( $_item['type'] === 0 ){
		if( $has_name && $has_qty ){
			$valid_products[] = $_idx;
		}
	}elseif( $_item['type'] === 1 ){

		$_has_valid_prescription_type = isset($_item['prescription_type']) && in_array($_item['prescription_type'], ['nre', 'file']);
		$_has_cf = isset($_item['prescription_cf']) && ! empty(trim($_item['prescription_cf']));
		$_has_nre = isset($_item['prescription_nre']) && ! empty(trim($_item['prescription_nre']));
		$_has_prescription = FALSE;

		if( ! $_has_valid_prescription_type ){
			$errors_arr[] = 'Per il prodotto '.($_item['name'] ?? '#'.$_idx).' inserire NRE opppure allegare file.';
		}

		if( $has_name && $has_qty && $_has_valid_prescription_type ){
			if( $_item['prescription_type'] == 'nre' ){
				if( $_has_nre && $_has_cf ){
					$_has_prescription = TRUE;
					$valid_products[] = $_idx;
					$_prescription = [
						'type'  => $_item['prescription_type'],
						'value' => [
							'cf'  => $_item['prescription_cf'],
							'nre' => $_item['prescription_nre'],
						],
					];
				}else{
					$errors_arr[] = 'Per il prodotto '.($_item['name'] ?? '#'.$_idx).' non inserito correttamente codice fiscale e/o NRE.';
				}
			}elseif( $_item['prescription_type'] == 'file' ){
				if( isset($files['file_'.$_item['uuid']]) ){
					$_has_prescription = TRUE;
					$valid_products[] = $_idx;
					$_prescription = [
						'type'  => $_item['prescription_type'],
						'value' => $files['file_'.$_item['uuid']],
					];
				}else{
					$errors_arr[] = 'Per il prodotto '.($_item['name'] ?? '#'.$_idx).' non hai allegato un file ricetta valido.';
				}
			}
		}
	}else{
		$errors_arr[] = 'Puoi aggiungere il prodotto '.($_item['name'] ?? '#'.$_idx).' specificando se è con ricetta oppure senza ricetta.';
	}

	$products[$_idx]['prescription'] = $_prescription;
	unset($products[$_idx]['prescription_cf']);
	unset($products[$_idx]['prescription_nre']);
	unset($products[$_idx]['prescription_type']);
}

if( ! empty($errors_arr) ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad request',
		'message' => trim('La prenotazione non è stata salvata. '.implode('. ', $errors_arr)),
		'data'    => NULL,
	]);
	exit;
}

//------------------------------------------------

$orderSummary = "🛒 *Ordine Farmacia*\n\n";

$totalQty = 0;

foreach ($products AS $_idx => $_item) {
	$_name = $_item['name'];
	$_qty = $_item['qty'];

	$_prescription = $_item['prescription'] ? '📄 con ricetta' : '❌ senza ricetta';

	$orderSummary .= "• ";
		$orderSummary .= "$_name × $_qty";
		$orderSummary .= $_item['prescription'] ? " $_prescription" : "";
	$orderSummary .= "\n";

	$totalQty += $_qty;
}

$orderSummary .= "\n📦 Totale pezzi: *$totalQty*";

if ($pickup) {
    $date = (new DateTime($pickup))->format('d/m/Y H:i');
    $orderSummary .= "\n📅 Ritiro previsto: *$date*";
}

if ($salta_fila) {
    $orderSummary .= "\n⚡ Opzione salta fila richiesta";
}

if ($delivery) {
    $orderSummary .= "\n📮 Si richiede consegna a domicilio";
}

if (!empty($note)) {
    $orderSummary .= "\n📝 Nota: \"$note\"";
}

if ($urgent) {
    $orderSummary .= "\n🚨 *URGENTE*";
}

//------------------------------------------------

$my_wa = get_my_wa();

$message = $orderSummary;
$message = filter_comm_message( $message, get_my_id(), $pharma['id'], 'request--reservation' );

$metadata = [
	'products' => array_map(function($_p){
		if($_p['prescription'] && $_p['prescription']['type'] == 'file' ){
			$_p['prescription']['value'] = NULL;
		}
		return $_p;
	}, $products),
	'pickup'   => $pickup,
	'note'     => $note,
	'urgent'   => $urgent,
	'salta_fila' => $salta_fila,
	'delivery' => $delivery,
];

$request_id = RequestModel::insert([
	'request_type' => 'reservation',
	'user_id'      => get_my_id(),
	'pharma_id'    => $pharma['id'],
	'message'      => $message,
	'metadata'     => $metadata,
]);

if( ! $request_id ){
	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Error',
		'message' => 'La prenotazione non è stata salvata. Riprova.',
		'data'    => NULL,
	]);
	exit;
}

//-------------------------------------
// UPLOAD FILES
//-------------------------------------
	$base_path_reservation_files = site_path().'/uploads/pharmacies/'.$pharma['id'].'/reservations/'.$request_id.'/';
	foreach ($products AS $_idx => $_item) {
		if( $_item['prescription'] && $_item['prescription']['type'] == 'file' && $_item['prescription']['value'] ){
			jt_mkdir($base_path_reservation_files, TRUE);

			$new_filename = $pharma['id'] .'_'. $_item['uuid'];
			$_file = $_item['prescription']['value'];

			if( is_upload_pdf($_file) ){
				$pdf_meta = false;
				if( move_uploaded_file($_file['tmp_name'], $base_path_reservation_files . $new_filename . '.pdf' ) ){
					$pdf_meta = get_pdf_info($base_path_reservation_files . $new_filename . '.pdf');
				}
				$metadata['products'][$_idx]['prescription']['value'] = $pdf_meta;
			}elseif( is_upload_image($_file) ){
				$img_meta = @minimize_image(
					$files['file_'.$_item['uuid']],
					$base_path_reservation_files,
					$new_filename
				);
				$metadata['products'][$_idx]['prescription']['value'] = $img_meta;
			}
		}
	}

	RequestModel::update($request_id, [
		'metadata' => $metadata,
	]);
//-------------------------------------

$wa_response = app_wa_send( $message );

$request_response = 'La farmacia ha ricevuto la tua richiesta. Ti avviseremo quando la tua richiesta sarà confermata.';

echo json_encode([
	'code'      => 200,
	'status'    => TRUE,
	'message'   => $request_response,
]);
