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

$request_id = RequestModel::insert([
	'request_type' => 'promos',
	'user_id'      => get_my_id(),
	'pharma_id'    => $pharma['id'],
	'message'      => $message,
	'metadata'     => $input,
]);

if( ! $request_id ){
	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Error',
		'message' => 'L\'ordine non è stato salvato. Riprova.',
	]);
	exit;
}


$wa_response = app_wa_send( $message );

$request_response = 'Grazie per le tue prenotazioni. Avrai notizie dal Farmacista appena possibile.';

$points_awarded = 0;
$points_value = (int) get_option('point--order_checkout', 10);

// Idempotenza senza modificare schema: fingerprint stabile del carrello giornaliero.
$items_fingerprint = array_map(function($item){
	return [
		'id' => (int) ($item['id'] ?? 0),
		'quantity' => (int) ($item['quantity'] ?? 0),
		'price_sale' => (float) ($item['price_sale'] ?? 0),
	];
}, $items);
usort($items_fingerprint, function($a, $b){ return $a['id'] <=> $b['id']; });

$hash_payload = [
	'user_id' => (int) $user['id'],
	'pharma_id' => (int) $pharma['id'],
	'items' => $items_fingerprint,
];
$checkout_source = 'order_checkout--' . substr(sha1(json_encode($hash_payload)), 0, 16);

$already_tracked = UserPointsModel::hasEntryForDate((int) $user['id'], (int) $pharma['id'], $checkout_source);
if( ! $already_tracked && $points_value > 0 ){
	$added = UserPointsModel::addPoints((int) $user['id'], (int) $pharma['id'], $points_value, $checkout_source);
	if( $added ) $points_awarded = $points_value;
}

echo json_encode([
	'code'      => 200,
	'status'    => TRUE,
	'message'   => $request_response,
	'data'      => [
		'request_id' => (int) $request_id,
		'points_awarded' => $points_awarded,
	],
]);
