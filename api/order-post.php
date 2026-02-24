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

//------------------------------------------------

$input = json_decode(file_get_contents("php://input"), TRUE);

$items = $input['items'] ?? [];
if( empty($items) ){
	echo json_encode([
		'code'    => 200,
		'status'  => FALSE,
		'error'   => 'No items',
		'message' => 'Il tuo carrello è vuoto.',
	]);
	exit();
}


$orderSummary = "📦 Riepilogo ordine:\n\n";

$totalAmount = 0;
$totalQuantity = 0;

foreach ($items as $item) {
	$label = $item['name'];
	$qty = (int) $item['quantity'];
	$price = (float) $item['price_sale'];
	$subtotal = $qty * $price;

	$orderSummary .= "🧴 *{$label}*\n";
	$orderSummary .= "🔢 Quantità: {$qty}\n";
	$orderSummary .= "🆔 ID: {$item['id']}\n";
	if( $price ){
		$orderSummary .= "💰 Prezzo unitario: €" . number_format($price, 2, ',', '.') . "\n";
		$orderSummary .= "📌 Totale prodotto: €" . number_format($subtotal, 2, ',', '.') . "\n";
	}
	$orderSummary .= "\n";

	$totalAmount += $subtotal;
	$totalQuantity += $qty;
}

$orderSummary .= "🔄 Totale pezzi ordinati: {$totalQuantity}\n";
if( $totalAmount ) $orderSummary .= "💳 Totale ordine: €" . number_format($totalAmount, 2, ',', '.');

//------------------------------------------------

$my_wa = get_my_wa();
$pharma = getMyPharma();

$message = $orderSummary;
$message = filter_comm_message( $message, get_my_id(), $pharma['id'], 'request--order' );

RequestModel::insert([
	'request_type' => 'promos',
	'user_id'      => get_my_id(),
	'pharma_id'    => $pharma['id'],
	'message'      => $message,
	'metadata'     => $input,
]);


$wa_response = app_wa_send( $message );

$request_response = 'Grazie per le tue prenotazioni. Avrai notizie dal Farmacista appena possibile.';

echo json_encode([
	'code'      => 200,
	'status'    => TRUE,
	'message'   => $request_response,
]);
