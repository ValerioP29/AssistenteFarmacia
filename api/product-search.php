<?php
require_once('_api_bootstrap.php');
require_once(__DIR__ . '/helpers/_related_tags.php');
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
$search_term = trim((string)($input['search'] ?? ($input['query'] ?? ($input['q'] ?? ''))));
$pharma_id = (int)($input['pharma_id'] ?? ($input['pharmacy_id'] ?? 0));
$related_mode = (int)($input['related_mode'] ?? 0) === 1;
$related_tag = trim($input['related_tag'] ?? '');
$related_seed = trim($input['related_seed'] ?? '');
$related_debug = in_array(strtolower(trim((string)($input['related_debug'] ?? ''))), ['1', 'true', 'yes', 'on'], true);
$limit = (int)($input['limit'] ?? ($related_mode ? 3 : 100));
$limit = max(1, min(50, $limit));
if ($related_mode) {
	$limit = 3;
}
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

function is_featured_related_tag($tag){
	$normalized = normalize_related_text($tag);
	return $normalized === '' || in_array($normalized, ['in_evidenza', 'featured', 'evidenza'], true);
}
function get_related_category_keywords($relatedTag){
	return related_tags_get_category_keywords($relatedTag);
}

function related_debug_log($enabled, $message, $context = []){
	if (!$enabled) return;
	error_log('[related-products] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function build_related_discount_expr($priceExpr){
	$percentageDiscountExpr = "CASE
		WHEN pp.percentage_discount IS NULL THEN NULL
		WHEN pp.percentage_discount > 0 AND pp.percentage_discount <= 100 THEN pp.percentage_discount
		ELSE NULL
	END";

	$discountedPriceExpr = "CASE
		WHEN ({$priceExpr}) IS NULL OR ({$priceExpr}) <= 0 THEN NULL
		WHEN {$percentageDiscountExpr} IS NOT NULL THEN ROUND(({$priceExpr}) * (1 - ({$percentageDiscountExpr} / 100)), 2)
		ELSE NULL
	END";

	$hasDiscountExpr = "CASE
		WHEN pp.is_on_sale = 1 THEN 1
		WHEN ({$discountedPriceExpr}) IS NOT NULL AND ({$discountedPriceExpr}) < ({$priceExpr}) THEN 1
		WHEN pp.discount_type IS NOT NULL AND TRIM(CONVERT(pp.discount_type USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> ''
			AND pp.percentage_discount IS NOT NULL AND pp.percentage_discount > 0 THEN 1
		ELSE 0
	END";

	return [
		'has_discount' => $hasDiscountExpr,
		'price_discounted' => $discountedPriceExpr,
	];
}

function build_related_order_by($nameExpr, $imageExpr, $priceExpr, $scoreExpr = null){
	$discountConfig = build_related_discount_expr($priceExpr);
	$imageTextExpr = "CONVERT({$imageExpr} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
	$nameTextExpr = "CONVERT({$nameExpr} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
	$hasImageExpr = "CASE
		WHEN {$imageExpr} IS NOT NULL
			AND TRIM({$imageTextExpr}) <> ''
			AND LOWER(TRIM({$imageTextExpr})) NOT LIKE '%placeholder%'
		THEN 1 ELSE 0 END";
	$hasPriceExpr = "CASE WHEN {$priceExpr} IS NOT NULL AND {$priceExpr} > 0 THEN 1 ELSE 0 END";
	$isSentenceCaseExpr = "CASE
		WHEN {$nameExpr} IS NULL OR TRIM({$nameTextExpr}) = '' THEN 0
		WHEN BINARY {$nameExpr} = BINARY CONCAT(UPPER(LEFT({$nameExpr}, 1)), LOWER(SUBSTRING({$nameExpr}, 2))) THEN 1
		ELSE 0
	END";
	$isLowercaseExpr = "CASE
		WHEN {$nameExpr} IS NULL OR TRIM({$nameTextExpr}) = '' THEN 0
		WHEN BINARY {$nameExpr} = BINARY LOWER({$nameExpr}) THEN 1
		ELSE 0
	END";
	$relevanceExpr = $scoreExpr ? $scoreExpr : '0';

	return [
		'has_discount' => $discountConfig['has_discount'],
		'price_discounted' => $discountConfig['price_discounted'],
		'has_image' => $hasImageExpr,
		'has_price' => $hasPriceExpr,
		'is_sentence_case' => $isSentenceCaseExpr,
		'is_lowercase' => $isLowercaseExpr,
		'relevance' => $relevanceExpr,
		'order_sql' => "{$hasImageExpr} DESC,
			{$isLowercaseExpr} DESC,
			{$isSentenceCaseExpr} DESC,
			{$relevanceExpr} DESC,
			{$hasPriceExpr} DESC,
			{$discountConfig['has_discount']} DESC,
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

function build_where_sql($conditions){
	if (empty($conditions)) return '';
	return ' WHERE ' . implode(' AND ', $conditions);
}

$related_tag_normalized = normalize_related_text($related_tag);
$related_seed_normalized = trim(mb_strtolower((string)$related_seed, 'UTF-8'));
$is_featured_tag = is_featured_related_tag($related_tag_normalized);

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
		'related_tag_normalized' => $related_tag_normalized,
		'related_seed' => $related_seed_normalized,
		'is_featured_tag' => $is_featured_tag,
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
		$descriptionExpr = $global_table_exists ? "COALESCE(pp.description, gp.description, '')" : "COALESCE(pp.description, '')";
		$categoryExpr = $global_table_exists ? "COALESCE(gp.category, '')" : "''";

		if ($global_table_exists) {
			$select_base = "SELECT 
							pp.*,
							COALESCE(pp.name, gp.name) as name,
							COALESCE(pp.description, gp.description) as description,
							COALESCE(pp.image, gp.image) as image,
							COALESCE(pp.sku, gp.sku) as sku,
							gp.category as category,
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
							NULL as category,
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

		$scoreExpr = "0";
		$whereConditions = [];
		$paramsBranch = [];
		$branch = 'generic';

		if (!$is_featured_tag && !empty($related_tag_normalized)) {
			$tagConditions = [];
			$tagsSearchExpr = "CONVERT((
				CASE
					WHEN base.tags IS NULL THEN NULL
					WHEN JSON_VALID(base.tags) THEN
						CASE
							WHEN JSON_TYPE(base.tags) = 'ARRAY' AND JSON_LENGTH(base.tags) = 0 THEN NULL
							ELSE JSON_UNQUOTE(JSON_EXTRACT(base.tags, '$'))
						END
					ELSE CAST(base.tags AS CHAR)
				END
			) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
			$nameSearchExpr = build_utf8_unicode_expr('base.name');
			$descriptionSearchExpr = build_utf8_unicode_expr('base.description');
			$categorySearchExpr = build_utf8_unicode_expr('base.category');
			$categoryContext = get_related_category_keywords($related_tag_normalized);
			$categoryKeywords = $categoryContext['keywords'] ?? [];

			if ($tags_column_exists) {
				foreach ($categoryKeywords as $kIdx => $keyword) {
					$likeKey = ':related_tag_kw_' . $kIdx;
					$tagConditions[] = "LOWER({$tagsSearchExpr}) LIKE LOWER({$likeKey})";
					$paramsBranch[$likeKey] = '%' . strtolower($keyword) . '%';
				}
			}

			foreach ($categoryKeywords as $kIdx => $keyword) {
				$nameKey = ':related_name_' . $kIdx;
				$descKey = ':related_desc_' . $kIdx;
				$catKey = ':related_cat_' . $kIdx;
				$tagConditions[] = "LOWER({$nameSearchExpr}) LIKE LOWER({$nameKey})";
				$tagConditions[] = "LOWER({$descriptionSearchExpr}) LIKE LOWER({$descKey})";
				$tagConditions[] = "LOWER({$categorySearchExpr}) LIKE LOWER({$catKey})";
				$paramsBranch[$nameKey] = '%' . strtolower($keyword) . '%';
				$paramsBranch[$descKey] = '%' . strtolower($keyword) . '%';
				$paramsBranch[$catKey] = '%' . strtolower($keyword) . '%';
			}

			if (!empty($tagConditions)) {
				$branch = 'tag';
				$whereConditions[] = '(' . implode(' OR ', $tagConditions) . ')';
				$scoreExpr = 'CASE WHEN (' . implode(' OR ', $tagConditions) . ') THEN 1 ELSE 0 END';
			}
		}

		if ($branch === 'generic' && !empty($related_seed_normalized)) {
			$seedLike = '%' . $related_seed_normalized . '%';
			$nameSearchExpr = build_utf8_unicode_expr('base.name');
			$descriptionSearchExpr = build_utf8_unicode_expr('base.description');
			$categorySearchExpr = build_utf8_unicode_expr('base.category');
			$seedCondition = "(LOWER({$nameSearchExpr}) LIKE LOWER(:seed_like)
				OR LOWER({$descriptionSearchExpr}) LIKE LOWER(:seed_like)
				OR LOWER({$categorySearchExpr}) LIKE LOWER(:seed_like))";
			$branch = 'seed';
			$whereConditions[] = $seedCondition;
			$paramsBranch[':seed_like'] = $seedLike;
			$scoreExpr = "CASE
				WHEN LOWER({$nameSearchExpr}) LIKE LOWER(:seed_like) THEN 3
				WHEN LOWER({$descriptionSearchExpr}) LIKE LOWER(:seed_like) THEN 2
				WHEN LOWER({$categorySearchExpr}) LIKE LOWER(:seed_like) THEN 1
				ELSE 0 END";
		}

		$orderConfig = build_related_order_by('base.name', 'base.image', 'COALESCE(base.sale_price, base.price)', $scoreExpr);
		$select_sql = "SELECT base.*,
			{$orderConfig['has_discount']} AS related_has_discount,
			{$orderConfig['price_discounted']} AS related_price_discounted,
			{$orderConfig['has_image']} AS related_has_image,
			{$orderConfig['has_price']} AS related_has_price,
			{$orderConfig['is_sentence_case']} AS related_is_sentence_case_name,
			{$orderConfig['is_lowercase']} AS related_is_lowercase_name,
			{$scoreExpr} AS related_relevance_score
			FROM ({$select_base}) base";
		$whereExtraSql = build_where_sql($whereConditions);
		$orderSql = $orderConfig['order_sql'];

		$params = [':pharma_id' => $pharma_id];
		foreach ($exclude_ids as $idx => $id) {
			$params[':exclude_' . $idx] = $id;
		}

		$products = [];
		if ($branch !== 'generic') {
			$sql_branch = $select_sql . $whereExtraSql . " ORDER BY {$orderSql} LIMIT :limit";
			$stmt = $pdo->prepare($sql_branch);
			foreach ($params as $k => $v) {
				$stmt->bindValue($k, $v, PDO::PARAM_INT);
			}
			foreach ($paramsBranch as $k => $v) {
				$stmt->bindValue($k, $v, PDO::PARAM_STR);
			}
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->execute();
			$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		if (count($products) === 0) {
			$branch = 'generic';
			$genericScore = "CASE WHEN ranked.is_featured = 1 THEN 2 ELSE 1 END";
			$orderGeneric = build_related_order_by('ranked.name', 'ranked.image', 'COALESCE(ranked.sale_price, ranked.price)', $genericScore);
			$sql_generic = "SELECT ranked.*,
				{$orderGeneric['has_discount']} AS related_has_discount,
				{$orderGeneric['price_discounted']} AS related_price_discounted,
				{$orderGeneric['has_image']} AS related_has_image,
				{$orderGeneric['has_price']} AS related_has_price,
				{$orderGeneric['is_sentence_case']} AS related_is_sentence_case_name,
				{$orderGeneric['is_lowercase']} AS related_is_lowercase_name,
				{$genericScore} AS related_relevance_score
				FROM ({$select_base}) ranked
				ORDER BY {$orderGeneric['order_sql']}
				LIMIT :limit";
			$stmt = $pdo->prepare($sql_generic);
			foreach ($params as $k => $v) {
				$stmt->bindValue($k, $v, PDO::PARAM_INT);
			}
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->execute();
			$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		related_debug_log($related_debug, 'final_result', [
			'related_tag' => $related_tag_normalized,
			'related_seed' => $related_seed_normalized,
			'branch' => $branch,
			'result_count' => count($products),
		]);

		$products = array_map(function($p){
			$priceOriginal = isset($p['price']) ? (float)$p['price'] : 0.0;
			$priceDiscounted = isset($p['related_price_discounted']) && $p['related_price_discounted'] !== null
				? (float)$p['related_price_discounted']
				: null;
			$hasDiscount = (int)($p['related_has_discount'] ?? 0) === 1
				&& $priceDiscounted !== null
				&& $priceOriginal > 0
				&& $priceDiscounted > 0
				&& $priceDiscounted < $priceOriginal;

			$p['tags'] = decode_tags_array($p['tags'] ?? null);
			$p['is_featured'] = (int)($p['is_featured'] ?? 0);
			$p['price_original'] = $priceOriginal > 0 ? round($priceOriginal, 2) : null;
			$p['has_discount'] = $hasDiscount;
			$p['price_discounted'] = $hasDiscount ? round($priceDiscounted, 2) : null;
			$p['related_has_discount'] = (int)($p['related_has_discount'] ?? 0);
			$p['related_has_image'] = (int)($p['related_has_image'] ?? 0);
			$p['related_has_price'] = (int)($p['related_has_price'] ?? 0);
			$p['related_is_sentence_case_name'] = (int)($p['related_is_sentence_case_name'] ?? 0);
			$p['related_is_lowercase_name'] = (int)($p['related_is_lowercase_name'] ?? 0);
			$p['related_relevance_score'] = (float)($p['related_relevance_score'] ?? 0);
			return $p;
		}, $products);

		echo json_encode([
			'code'    => 200,
			'status'  => TRUE,
			'message' => 'Correlati caricati con successo',
			'data'    => [
				'products' => array_slice($products, 0, $limit),
				'total' => count($products),
				'pharma_id' => $pharma_id,
				'related_mode' => 1,
				'related_tag' => $related_tag,
				'related_tag_normalized' => $related_tag_normalized,
				'related_seed' => $related_seed_normalized,
				'debug' => $related_debug ? [
					'tags_column_exists' => $tags_column_exists,
					'global_table_exists' => $global_table_exists,
					'branch' => $branch,
				] : null,
			]
		]);
		exit();
	}

	// Query di ricerca classica
	$searchLike = '%' . $search_term . '%';
	if ($global_table_exists) {
		$sql = "SELECT base.*,
			CASE
				WHEN LOWER(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_prefix) THEN 3
				WHEN LOWER(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term) THEN 2
				WHEN LOWER(CONVERT(base.description USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term) THEN 1
				ELSE 0 END AS search_relevance
			FROM (
				SELECT 
					pp.*,
					COALESCE(pp.name, gp.name) as name,
					COALESCE(pp.description, gp.description) as description,
					COALESCE(pp.image, gp.image) as image,
					COALESCE(pp.sku, gp.sku) as sku,
					gp.category as category
				FROM jta_pharma_prods pp
				LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id
				WHERE pp.pharma_id = :pharma_id
					AND pp.is_active = 1
					AND (
						LOWER(pp.name) LIKE LOWER(:search_term)
						OR LOWER(COALESCE(gp.name, '')) LIKE LOWER(:search_term)
					)
			) base
			ORDER BY
				CASE WHEN base.image IS NOT NULL AND TRIM(CONVERT(base.image USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND LOWER(TRIM(CONVERT(base.image USING utf8mb4) COLLATE utf8mb4_unicode_ci)) NOT LIKE '%placeholder%' THEN 1 ELSE 0 END DESC,
				CASE WHEN base.name IS NOT NULL AND TRIM(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND BINARY base.name = BINARY LOWER(base.name) THEN 1 ELSE 0 END DESC,
				CASE WHEN base.name IS NOT NULL AND TRIM(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND BINARY base.name = BINARY CONCAT(UPPER(LEFT(base.name, 1)), LOWER(SUBSTRING(base.name, 2))) THEN 1 ELSE 0 END DESC,
				search_relevance DESC,
				base.name ASC
			LIMIT :limit";
	} else {
		$sql = "SELECT base.*,
			CASE
				WHEN LOWER(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_prefix) THEN 3
				WHEN LOWER(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term) THEN 2
				WHEN LOWER(CONVERT(base.description USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term) THEN 1
				ELSE 0 END AS search_relevance
			FROM (
				SELECT * FROM jta_pharma_prods 
				WHERE pharma_id = :pharma_id
					AND is_active = 1
					AND LOWER(name) LIKE LOWER(:search_term)
			) base
			ORDER BY
				CASE WHEN base.image IS NOT NULL AND TRIM(CONVERT(base.image USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND LOWER(TRIM(CONVERT(base.image USING utf8mb4) COLLATE utf8mb4_unicode_ci)) NOT LIKE '%placeholder%' THEN 1 ELSE 0 END DESC,
				CASE WHEN base.name IS NOT NULL AND TRIM(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND BINARY base.name = BINARY LOWER(base.name) THEN 1 ELSE 0 END DESC,
				CASE WHEN base.name IS NOT NULL AND TRIM(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND BINARY base.name = BINARY CONCAT(UPPER(LEFT(base.name, 1)), LOWER(SUBSTRING(base.name, 2))) THEN 1 ELSE 0 END DESC,
				search_relevance DESC,
				base.name ASC
			LIMIT :limit";
	}

	$stmt = $pdo->prepare($sql);
	$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
	$stmt->bindValue(':search_term', $searchLike, PDO::PARAM_STR);
	$stmt->bindValue(':search_prefix', mb_strtolower($search_term, 'UTF-8') . '%', PDO::PARAM_STR);
	$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
	$stmt->execute();

	$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$products = array_map(function($p){
		$p['tags'] = decode_tags_array($p['tags'] ?? null);
		return $p;
	}, $products);
	
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
