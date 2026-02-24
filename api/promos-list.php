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

// $limit_default = get_option('max_promos_limit', 12);
$limit_default = 80;
$limit = $_GET['limit'] ?? $limit_default;
if( $limit < 1 ) $limit = 1;
if( $limit > $limit_default ) $limit = $limit_default;

$tipo   = $_GET['tipo'] ?? null;
$pharma = getMyPharma();
$is_really_filtered = FALSE;

$products = ProductsModel::findPromosByPharma( $pharma['id'], $limit );

if (isset($tipo) ) {
	$filtered_ids = [];

	if ( $tipo === mb_strtolower('ottobre-rosa') ) {
		if(is_localhost()) { $filtered_ids = [14];  }
		else { $filtered_ids = [7109, 7106, 7103, 7104, 7105, 7099, 7100, 7108, 7107, 7102, 7094, 7095, 7096, 7097, 7098, 7101]; }
	}
	elseif ( $tipo === mb_strtolower('bionike-1-1') ) {
		if(is_localhost()) { $filtered_ids = [104];  }
		else { $filtered_ids = [7110, 7111, 7113, 7114, 7115]; }	
	}

	if ( ! empty($filtered_ids) ){
		$tmp_products = [];
		foreach ($filtered_ids as $_idx => $_id) {
			$tmp_products[] = ProductsModel::findPharmaPromoById($pharma['id'], $_id);
		}
		$tmp_products = array_filter($tmp_products);
		$products = $tmp_products;
		$is_really_filtered = TRUE;
	}
}

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data'    => [
		'products' => array_map('normalize_product_data', $products),
		'filtered' => $is_really_filtered ? TRUE : FALSE,
	],
]);
