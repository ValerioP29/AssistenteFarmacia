<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

function waservice_url( $pharma_id = NULL ){
	switch( $pharma_id ){
		case 1: $url = 'https://waservice-pharma1.jungleteam.it'; break;
		case 2: $url = 'https://waservice-pharma2.jungleteam.it'; break;
		default: $url = NULL; break;
	}
	return $url;
}

function wa_send( $message, $to, $pharma_id = NULL ){
	$send = get_option('wa_send_enabled', TRUE);
	if( ! $send ) return FALSE;
	if( ! $message ) return FALSE;
	if( ! $to ) return FALSE;
	if( ! $pharma_id ) return FALSE;
	$base_url = waservice_url($pharma_id);
	if( ! $base_url ) return FALSE;

	// Invia il messaggio WhatsApp
	$to = preg_replace('/^\+?39/', '', $to); // rimuove eventuale +39 o 39 se già presente
	$to = '39' . ltrim($to, '0'); // aggiunge 39 davanti e rimuove eventuale zero iniziale

	$whatsappData = [
		'message' => $message,
		'phone' => $to,
	];

	$is_connect = pharma_is_wa_connected($pharma_id);
	$is_connect = $is_connect['success'] === TRUE; 

	if( ! $is_connect ){
		return [
			'success' => FALSE,
			'message' => 'Non è stato possibile inviare il messaggio WhatsApp.',
		];
	}

	$ch = curl_init( $base_url.'/send' );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($whatsappData));
	if( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Accept: application/json'
	]);

	$response = curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);

	if( $error ){
		return [
			'success' => FALSE,
			'message' => $error,
		];
	}
	return json_decode($response, TRUE);
}

function app_wa_send( $message ){
	$pharma = getMyPharma();
	if( ! $pharma ) return FALSE;
	$to = get_my_wa();
	return wa_send( $message, $to, $pharma['id'] );
}

function pharma_is_wa_connected( $pharma_id = NULL ){
	$base_url = waservice_url($pharma_id);
	if( ! $base_url ) return [
		'success' => FALSE,
		'message' => 'Farmacia non dichiarata.',
	];

	$ch = curl_init( $base_url.'/status' );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	// curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POST, FALSE);
	// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
	if( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Accept: application/json'
	]);

	$response = curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);

	if( $error ){
		return [
			'success' => FALSE,
			'message' => $error,
		];
	}

	if( $response ){
		$response = json_decode($response);

		if( isset($response->success) && $response->success == TRUE ){
			return [
				'success' => TRUE,
				'message' => 'Connesso',
				'data'    => $response,
			];
		}
		return [
			'success' => FALSE,
			'message' => 'Non connesso',
		];
	}

	return [
		'success' => FALSE,
		'message' => 'Errore imprevisto.',
	];

}

function pharma_wa_disconnect( $pharma_id = NULL ){
	$base_url = waservice_url($pharma_id);
	if( ! $base_url ) return [
		'success' => FALSE,
		'message' => 'Farmacia non dichiarata.',
	];

	$ch = curl_init( $base_url.'/disconnect' );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
	if( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Accept: application/json'
	]);

	$response = curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);

	if( $error ){
		return [
			'success' => FALSE,
			'message' => $error,
		];
	}

	if( $response ){
		$response = json_decode($response);

		if( $response->success == TRUE ){
			return [
				'success' => TRUE,
				'message' => 'Disconnesso',
				'data'    => $response,
			];
		}
	}

	return [
		'success' => FALSE,
		'message' => 'Errore imprevisto.',
	];

}

function pharma_wa_qr( $pharma_id = NULL ){
	$base_url = waservice_url($pharma_id);
	if( ! $base_url ) return [
		'success' => FALSE,
		'message' => 'Farmacia non dichiarata.',
	];

	$ch = curl_init( $base_url.'/qr');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_POST, FALSE);
	// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
	if( $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Accept: application/json'
	]);

	$response = curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);

	if( $error ){
		return [
			'success' => FALSE,
			'message' => $error,
		];
	}

	if( $response ){
		$response = json_decode($response);

		if( $response->success == TRUE ){
			return $response;
		}
	}

	return [
		'success' => FALSE,
		'message' => 'Errore imprevisto.',
	];

}
