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

$input = $_GET;
$search_term = trim($input['search'] ?? '');
$pharma_id = (int)($input['pharma_id'] ?? 0);

// Validazione input
if( empty($search_term) || $pharma_id <= 0 ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => 'Parametri mancanti o non validi. Richiesti: search (stringa di ricerca) e pharma_id (ID farmacia)',
	]);
	exit();
}

//------------------------------------------------

try {
	global $pdo;
	
	// Prima verifichiamo se esiste la tabella globale dei prodotti
	$global_table_exists = false;
	try {
		$stmt = $pdo->prepare("SHOW TABLES LIKE 'jta_global_prods'");
		$stmt->execute();
		$global_table_exists = $stmt->rowCount() > 0;
	} catch (Exception $e) {
		// Tabella non esiste, continuiamo senza
		$global_table_exists = false;
	}
	
	// Query di ricerca
	if ($global_table_exists) {
		// Query con JOIN alla tabella globale per completare i dati mancanti
		$sql = "SELECT 
					pp.*,
					COALESCE(pp.name, gp.name) as name,
					COALESCE(pp.description, gp.description) as description,
					COALESCE(pp.image, gp.image) as image,
					COALESCE(pp.sku, gp.sku) as sku
				FROM jta_pharma_prods pp
				LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id
				WHERE pp.pharma_id = :pharma_id 
					AND pp.is_active = 1
					AND (
						LOWER(pp.name) LIKE LOWER(:search_term) 
						OR LOWER(COALESCE(gp.name, '')) LIKE LOWER(:search_term)
					)
				ORDER BY pp.name ASC";
	} else {
		// Query solo sulla tabella pharma_prods
		$sql = "SELECT * FROM jta_pharma_prods 
				WHERE pharma_id = :pharma_id 
					AND is_active = 1
					AND LOWER(name) LIKE LOWER(:search_term)
				ORDER BY name ASC";
	}
	
	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':pharma_id' => $pharma_id,
		':search_term' => '%' . $search_term . '%'
	]);
	
	$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	echo json_encode([
		'code'    => 200,
		'status'  => TRUE,
		'message' => 'Ricerca completata con successo',
		'data'    => [
			'products' => $products,
			'total' => count($products),
			'search_term' => $search_term,
			'pharma_id' => $pharma_id
		]
	]);
	
} catch (Exception $e) {
	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Internal Server Error',
		'message' => 'Errore durante la ricerca dei prodotti: ' . $e->getMessage(),
	]);
} 