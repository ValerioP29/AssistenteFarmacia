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
$pharma_id = (int)($input['pharma_id'] ?? ($input['pharmacy_id'] ?? 0));
$related_mode = (int)($input['related_mode'] ?? 0) === 1;
$related_tag = trim($input['related_tag'] ?? '');
$limit = (int)($input['limit'] ?? ($related_mode ? 8 : 100));
$limit = max(1, min(50, $limit));
$exclude_ids_raw = trim($input['exclude_ids'] ?? '');
$exclude_ids = [];
if ($exclude_ids_raw !== '') {
	$exclude_ids = array_values(array_filter(array_map('intval', explode(',', $exclude_ids_raw)), function($id){
		return $id > 0;
	}));
}

function decode_tags_array($rawTags){
	if ($rawTags === null) return [];
	if (is_array($rawTags)) return $rawTags;
	if (!is_string($rawTags)) return [];
	$rawTags = trim($rawTags);
	if ($rawTags === '') return [];
	$decoded = json_decode($rawTags, true);
	if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
		return array_values(array_filter(array_map(function($tag){
			return strtolower(trim((string)$tag));
		}, $decoded)));
	}
	return array_values(array_filter(array_map(function($tag){
		return strtolower(trim((string)$tag));
	}, explode(',', $rawTags))));
}

// Validazione input
if( $pharma_id <= 0 || (!$related_mode && empty($search_term)) ){
	echo json_encode([
		'code'    => 400,
		'status'  => FALSE,
		'error'   => 'Bad Request',
		'message' => $related_mode
			? 'Parametro non valido. Richiesto: pharma_id (ID farmacia).'
			: 'Parametri mancanti o non validi. Richiesti: search (stringa di ricerca) e pharma_id (ID farmacia)',
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
	
	// Query correlati
	if ($related_mode) {
		if ($global_table_exists) {
			$select_base = "SELECT 
							pp.*,
							COALESCE(pp.name, gp.name) as name,
							COALESCE(pp.description, gp.description) as description,
							COALESCE(pp.image, gp.image) as image,
							COALESCE(pp.sku, gp.sku) as sku,
							gp.requires_prescription
						FROM jta_pharma_prods pp
						LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id
						WHERE pp.pharma_id = :pharma_id
							AND pp.is_active = 1";
		} else {
			$select_base = "SELECT 
							pp.*,
							pp.name as name,
							pp.description as description,
							pp.image as image,
							pp.sku as sku,
							0 AS requires_prescription
						FROM jta_pharma_prods pp
						WHERE pp.pharma_id = :pharma_id
							AND pp.is_active = 1";
		}

		if (!empty($exclude_ids)) {
			$placeholders = [];
			foreach ($exclude_ids as $idx => $id) {
				$placeholders[] = ':exclude_' . $idx;
			}
			$select_base .= " AND pp.id NOT IN (" . implode(',', $placeholders) . ")";
		}

		$params = [':pharma_id' => $pharma_id];
		foreach ($exclude_ids as $idx => $id) {
			$params[':exclude_' . $idx] = $id;
		}

		// 1) related_tag su tags
		$products = [];
		if (!empty($related_tag)) {
			$sql_tag = $select_base . " AND LOWER(COALESCE(pp.tags, '')) LIKE LOWER(:related_tag)
										ORDER BY pp.is_featured DESC, (pp.image IS NULL OR pp.image = ''), pp.name ASC
										LIMIT :limit";
			$stmt = $pdo->prepare($sql_tag);
			$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
			$stmt->bindValue(':related_tag', '%"' . strtolower($related_tag) . '"%', PDO::PARAM_STR);
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			foreach ($exclude_ids as $idx => $id) {
				$stmt->bindValue(':exclude_' . $idx, $id, PDO::PARAM_INT);
			}
			$stmt->execute();
			$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		// 2) fallback featured
		if (count($products) < $limit) {
			$needed = $limit - count($products);
			$exclude = array_column($products, 'id');
			$allEx = array_unique(array_merge($exclude_ids, array_map('intval', $exclude)));
			$sql_featured = $select_base . " AND pp.is_featured = 1";
			if (!empty($allEx)) {
				$ph = [];
				foreach ($allEx as $idx => $id) {
					$ph[] = ':fexclude_' . $idx;
				}
				$sql_featured .= " AND pp.id NOT IN (" . implode(',', $ph) . ")";
			}
			$sql_featured .= " ORDER BY (pp.image IS NULL OR pp.image = ''), pp.name ASC LIMIT :limit";
			$stmt = $pdo->prepare($sql_featured);
			$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
			$stmt->bindValue(':limit', $needed, PDO::PARAM_INT);
			if (!empty($allEx)) {
				foreach ($allEx as $idx => $id) {
					$stmt->bindValue(':fexclude_' . $idx, $id, PDO::PARAM_INT);
				}
			}
			$stmt->execute();
			$products = array_merge($products, $stmt->fetchAll(PDO::FETCH_ASSOC));
		}

		// 3) fallback attivi con immagine
		if (count($products) < $limit) {
			$needed = $limit - count($products);
			$exclude = array_column($products, 'id');
			$allEx = array_unique(array_merge($exclude_ids, array_map('intval', $exclude)));
			$sql_img = $select_base . " AND pp.image IS NOT NULL AND pp.image != ''";
			if (!empty($allEx)) {
				$ph = [];
				foreach ($allEx as $idx => $id) {
					$ph[] = ':iexclude_' . $idx;
				}
				$sql_img .= " AND pp.id NOT IN (" . implode(',', $ph) . ")";
			}
			$sql_img .= " ORDER BY pp.name ASC LIMIT :limit";
			$stmt = $pdo->prepare($sql_img);
			$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
			$stmt->bindValue(':limit', $needed, PDO::PARAM_INT);
			if (!empty($allEx)) {
				foreach ($allEx as $idx => $id) {
					$stmt->bindValue(':iexclude_' . $idx, $id, PDO::PARAM_INT);
				}
			}
			$stmt->execute();
			$products = array_merge($products, $stmt->fetchAll(PDO::FETCH_ASSOC));
		}

		$products = array_map(function($p){
			$p['tags'] = decode_tags_array($p['tags'] ?? null);
			$p['is_featured'] = (int)($p['is_featured'] ?? 0);
			return $p;
		}, $products);

		echo json_encode([
			'code'    => 200,
			'status'  => TRUE,
			'message' => 'Correlati caricati con successo',
			'data'    => [
				'products' => $products,
				'total' => count($products),
				'pharma_id' => $pharma_id,
				'related_mode' => 1,
				'related_tag' => $related_tag,
			]
		]);
		exit();
	}

	// Query di ricerca classica
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
