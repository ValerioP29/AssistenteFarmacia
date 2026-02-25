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
$related_debug = in_array(strtolower(trim((string)($input['related_debug'] ?? ''))), ['1', 'true', 'yes', 'on'], true);
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

function normalize_related_text($value){
	$value = strtolower(trim((string)$value));
	$value = str_replace(['-', ' '], '_', $value);
	return $value;
}

function get_related_category_keywords($relatedTag){
	$tag = normalize_related_text($relatedTag);
	$map = [
		'dolore_febbre' => ['dolore', 'febbre', 'antidolorifici', 'analgesici', 'antinfiammatori'],
		'raffreddore_influenza' => ['raffreddore', 'influenza', 'decongestionanti', 'respiratorio'],
		'gola' => ['gola', 'faringe', 'orofaringeo'],
		'tosse' => ['tosse', 'espettoranti', 'sciroppi'],
		'gastro' => ['gastro', 'digestione', 'reflusso', 'intestino'],
		'dermocosmesi' => ['dermocosmesi', 'pelle', 'viso', 'beauty'],
		'vitamine_integratori' => ['vitamine', 'integratori', 'benessere', 'minerali'],
		'bambino' => ['bambino', 'infanzia', 'baby', 'pediatrico'],
		'medicazione' => ['medicazione', 'cerotti', 'disinfettanti', 'garze'],
		'igiene_orale' => ['igiene orale', 'orale', 'dentifricio', 'collutorio', 'gengive'],
		'naso' => ['naso', 'nasale', 'rinite', 'sinusite'],
		'occhi' => ['occhi', 'oculare', 'colliri'],
	];

	return $map[$tag] ?? [];
}

function related_debug_log($enabled, $message, $context = []){
	if (!$enabled) return;
	error_log('[related-products] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function build_related_order_by($nameExpr, $imageExpr, $priceExpr){
	$imageTextExpr = "CONVERT({$imageExpr} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
	$nameTextExpr = "CONVERT({$nameExpr} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
	$hasImageExpr = "CASE WHEN {$imageExpr} IS NOT NULL AND TRIM({$imageTextExpr}) <> '' THEN 1 ELSE 0 END";
	$hasPriceExpr = "CASE WHEN {$priceExpr} IS NOT NULL AND {$priceExpr} > 0 THEN 1 ELSE 0 END";
	$isAllCapsExpr = "CASE
		WHEN {$nameExpr} IS NULL OR TRIM({$nameTextExpr}) = '' THEN 1
		WHEN BINARY {$nameExpr} = BINARY UPPER({$nameExpr})
			AND BINARY {$nameExpr} <> BINARY LOWER({$nameExpr}) THEN 1
		ELSE 0
	END";

	return [
		'has_image' => $hasImageExpr,
		'has_price' => $hasPriceExpr,
		'is_all_caps' => $isAllCapsExpr,
		'order_sql' => "{$hasImageExpr} DESC,
			{$hasPriceExpr} DESC,
			{$isAllCapsExpr} ASC,
			pp.is_featured DESC,
			pp.updated_at DESC,
			{$nameExpr} ASC",
	];
}

function build_tags_search_expr(){
	return "CONVERT((
		CASE
			WHEN pp.tags IS NULL THEN NULL
			WHEN JSON_VALID(pp.tags) THEN
				CASE
					WHEN JSON_TYPE(pp.tags) = 'ARRAY' AND JSON_LENGTH(pp.tags) = 0 THEN NULL
					ELSE JSON_UNQUOTE(JSON_EXTRACT(pp.tags, '$'))
				END
			ELSE CAST(pp.tags AS CHAR)
		END
	) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
}

function build_utf8_unicode_expr($expr){
	return "CONVERT({$expr} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
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
	$tags_column_exists = false;
	try {
		$stmt = $pdo->prepare("SHOW TABLES LIKE 'jta_global_prods'");
		$stmt->execute();
		$global_table_exists = $stmt->rowCount() > 0;
	} catch (Exception $e) {
		// Tabella non esiste, continuiamo senza
		$global_table_exists = false;
	}

	try {
		$stmt = $pdo->prepare("SHOW COLUMNS FROM jta_pharma_prods LIKE 'tags'");
		$stmt->execute();
		$tags_column_exists = $stmt->rowCount() > 0;
	} catch (Exception $e) {
		$tags_column_exists = false;
	}

	related_debug_log($related_debug, 'init', [
		'pharma_id' => $pharma_id,
		'related_tag' => $related_tag,
		'related_mode' => $related_mode,
		'tags_column_exists' => $tags_column_exists,
		'global_table_exists' => $global_table_exists,
		'exclude_ids_count' => count($exclude_ids),
	]);
	
	// Query correlati
	if ($related_mode) {
		$nameExpr = $global_table_exists ? "COALESCE(pp.name, gp.name)" : "pp.name";
		$imageExpr = $global_table_exists ? "COALESCE(pp.image, gp.image)" : "pp.image";
		$priceExpr = "COALESCE(pp.sale_price, pp.price)";
		$orderConfig = build_related_order_by($nameExpr, $imageExpr, $priceExpr);

		related_debug_log($related_debug, 'ranking_order', [
			'order_sql' => $orderConfig['order_sql'],
		]);

		if ($global_table_exists) {
			$select_base = "SELECT 
							pp.*,
							COALESCE(pp.name, gp.name) as name,
							COALESCE(pp.description, gp.description) as description,
							COALESCE(pp.image, gp.image) as image,
							COALESCE(pp.sku, gp.sku) as sku,
							gp.requires_prescription,
							{$orderConfig['has_image']} AS related_has_image,
							{$orderConfig['has_price']} AS related_has_price,
							{$orderConfig['is_all_caps']} AS related_is_all_caps_name
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
							0 AS requires_prescription,
							{$orderConfig['has_image']} AS related_has_image,
							{$orderConfig['has_price']} AS related_has_price,
							{$orderConfig['is_all_caps']} AS related_is_all_caps_name
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
				$tagConditions = [];
				$paramsTag = [];
				$tagsSearchExpr = build_tags_search_expr();
				$categorySearchExpr = $global_table_exists
					? build_utf8_unicode_expr("COALESCE(gp.category, '')")
					: null;

				if ($tags_column_exists) {
					$tagConditions[] = "({$tagsSearchExpr} IS NOT NULL AND TRIM({$tagsSearchExpr}) <> '' AND LOWER({$tagsSearchExpr}) LIKE LOWER(:related_tag_json))";
					$tagConditions[] = "({$tagsSearchExpr} IS NOT NULL AND TRIM({$tagsSearchExpr}) <> '' AND LOWER({$tagsSearchExpr}) LIKE LOWER(:related_tag_plain))";
					$paramsTag[':related_tag_json'] = '%"' . strtolower($related_tag) . '"%';
					$paramsTag[':related_tag_plain'] = '%' . strtolower($related_tag) . '%';
				}

			$categoryKeywords = get_related_category_keywords($related_tag);
				if ($global_table_exists && !empty($categoryKeywords)) {
					$catParts = [];
					foreach ($categoryKeywords as $kIdx => $keyword) {
						$key = ':related_cat_' . $kIdx;
						$catParts[] = "LOWER({$categorySearchExpr}) LIKE LOWER({$key})";
						$paramsTag[$key] = '%' . strtolower($keyword) . '%';
					}
				if (!empty($catParts)) {
					$tagConditions[] = '(' . implode(' OR ', $catParts) . ')';
				}
			}

			if (empty($tagConditions)) {
				related_debug_log($related_debug, 'no_tag_conditions_available', ['related_tag' => $related_tag]);
				$products = [];
			} else {
				$sql_tag = $select_base . " AND (" . implode(' OR ', $tagConditions) . ")
									ORDER BY {$orderConfig['order_sql']}
									LIMIT :limit";
				$stmt = $pdo->prepare($sql_tag);
				$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
				$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
				foreach ($exclude_ids as $idx => $id) {
					$stmt->bindValue(':exclude_' . $idx, $id, PDO::PARAM_INT);
				}
				foreach ($paramsTag as $pKey => $pVal) {
					$stmt->bindValue($pKey, $pVal, PDO::PARAM_STR);
				}
				$stmt->execute();
				$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
				related_debug_log($related_debug, 'category_filtered_result', [
					'related_tag' => $related_tag,
					'keywords' => $categoryKeywords,
					'result_count' => count($products),
				]);
			}
		}

		// 2) fallback featured
		if (count($products) < $limit) {
			related_debug_log($related_debug, 'fallback_featured_start', ['already_found' => count($products), 'limit' => $limit]);
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
			$sql_featured .= " ORDER BY {$orderConfig['order_sql']} LIMIT :limit";
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
			related_debug_log($related_debug, 'fallback_image_start', ['already_found' => count($products), 'limit' => $limit]);
			$needed = $limit - count($products);
			$exclude = array_column($products, 'id');
			$allEx = array_unique(array_merge($exclude_ids, array_map('intval', $exclude)));
			$imageNotEmptyExpr = build_utf8_unicode_expr('pp.image');
			$sql_img = $select_base . " AND pp.image IS NOT NULL AND TRIM({$imageNotEmptyExpr}) <> ''";
			if (!empty($allEx)) {
				$ph = [];
				foreach ($allEx as $idx => $id) {
					$ph[] = ':iexclude_' . $idx;
				}
				$sql_img .= " AND pp.id NOT IN (" . implode(',', $ph) . ")";
			}
			$sql_img .= " ORDER BY {$orderConfig['order_sql']} LIMIT :limit";
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
			$p['related_has_image'] = (int)($p['related_has_image'] ?? 0);
			$p['related_has_price'] = (int)($p['related_has_price'] ?? 0);
			$p['related_is_all_caps_name'] = (int)($p['related_is_all_caps_name'] ?? 0);
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
				'debug' => $related_debug ? [
					'tags_column_exists' => $tags_column_exists,
					'global_table_exists' => $global_table_exists,
				] : null,
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
	$sqlState = $e->getCode();
	if ($e instanceof PDOException && isset($e->errorInfo[0])) {
		$sqlState = $e->errorInfo[0];
	}
	error_log('[product-search] SQL error: ' . $e->getMessage() . ' | SQLSTATE: ' . $sqlState);

	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Internal Server Error',
		'message' => 'Errore durante la ricerca dei prodotti: ' . $e->getMessage() . ' (SQLSTATE: ' . $sqlState . ')',
	]);
} 
