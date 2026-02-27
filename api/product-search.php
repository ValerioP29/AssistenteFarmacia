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

function build_related_discount_expr($priceExpr, $columnRefs = []){
	$isOnSaleExpr = $columnRefs['is_on_sale'] ?? 'pp.is_on_sale';
	$discountTypeExpr = $columnRefs['discount_type'] ?? 'pp.discount_type';
	$percentageDiscountRawExpr = $columnRefs['percentage_discount'] ?? 'pp.percentage_discount';

	$percentageDiscountExpr = "CASE
		WHEN {$percentageDiscountRawExpr} IS NULL THEN NULL
		WHEN {$percentageDiscountRawExpr} > 0 AND {$percentageDiscountRawExpr} <= 100 THEN {$percentageDiscountRawExpr}
		ELSE NULL
	END";

	$discountedPriceExpr = "CASE
		WHEN ({$priceExpr}) IS NULL OR ({$priceExpr}) <= 0 THEN NULL
		WHEN {$percentageDiscountExpr} IS NOT NULL THEN ROUND(({$priceExpr}) * (1 - ({$percentageDiscountExpr} / 100)), 2)
		ELSE NULL
	END";

	$hasDiscountExpr = "CASE
		WHEN {$isOnSaleExpr} = 1 THEN 1
		WHEN ({$discountedPriceExpr}) IS NOT NULL AND ({$discountedPriceExpr}) < ({$priceExpr}) THEN 1
		WHEN {$discountTypeExpr} IS NOT NULL AND TRIM(CONVERT({$discountTypeExpr} USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> ''
			AND {$percentageDiscountRawExpr} IS NOT NULL AND {$percentageDiscountRawExpr} > 0 THEN 1
		ELSE 0
	END";

	return [
		'has_discount' => $hasDiscountExpr,
		'price_discounted' => $discountedPriceExpr,
	];
}

function build_related_order_by($nameExpr, $imageExpr, $priceExpr, $scoreExpr = null, $columnRefs = []){
	$discountConfig = build_related_discount_expr($priceExpr, $columnRefs);
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

function build_safe_column_expr($columnName, $pharmaColumnsMap, $defaultExpr = 'NULL'){
	$exists = !empty($pharmaColumnsMap[$columnName]);
	if ($exists) {
		return "pp.{$columnName}";
	}
	return $defaultExpr;
}

function build_safe_column_select_expr($columnName, $pharmaColumnsMap, $defaultExpr = 'NULL'){
	$expr = build_safe_column_expr($columnName, $pharmaColumnsMap, $defaultExpr);
	return "{$expr} AS {$columnName}";
}

function build_product_base_select_sql($global_table_exists, $pharma_columns_map, $includeSearchFilter = false, $exclude_ids = []){
	// Selezione esplicita: niente pp.* per evitare collisioni con alias normalizzati (name/description/image/sku/tags).
	$tagsSelect = build_safe_column_select_expr('tags', $pharma_columns_map, 'NULL');
	$baseColumns = [
		build_safe_column_select_expr('id', $pharma_columns_map, 'NULL'),
		build_safe_column_select_expr('pharma_id', $pharma_columns_map, 'NULL'),
		build_safe_column_select_expr('product_id', $pharma_columns_map, 'NULL'),
		build_safe_column_select_expr('price', $pharma_columns_map, 'NULL'),
		build_safe_column_select_expr('sale_price', $pharma_columns_map, 'NULL'),
		build_safe_column_select_expr('num_items', $pharma_columns_map, '0'),
		build_safe_column_select_expr('is_active', $pharma_columns_map, '1'),
		build_safe_column_select_expr('is_featured', $pharma_columns_map, '0'),
		build_safe_column_select_expr('is_on_sale', $pharma_columns_map, 'NULL'),
		build_safe_column_select_expr('discount_type', $pharma_columns_map, 'NULL'),
		build_safe_column_select_expr('percentage_discount', $pharma_columns_map, 'NULL'),
		$tagsSelect,
	];

	$normalizedColumns = $global_table_exists
		? [
			"COALESCE(" . build_safe_column_expr('name', $pharma_columns_map, 'NULL') . ", gp.name) AS name",
			"COALESCE(" . build_safe_column_expr('description', $pharma_columns_map, 'NULL') . ", gp.description) AS description",
			"COALESCE(" . build_safe_column_expr('image', $pharma_columns_map, 'NULL') . ", gp.image) AS image",
			"COALESCE(" . build_safe_column_expr('sku', $pharma_columns_map, 'NULL') . ", gp.sku) AS sku",
			"COALESCE(gp.category, '') AS category",
			"COALESCE(gp.requires_prescription, 0) AS requires_prescription",
		]
		: [
			build_safe_column_select_expr('name', $pharma_columns_map, 'NULL'),
			"COALESCE(" . build_safe_column_expr('description', $pharma_columns_map, 'NULL') . ", '') AS description",
			build_safe_column_select_expr('image', $pharma_columns_map, 'NULL'),
			build_safe_column_select_expr('sku', $pharma_columns_map, 'NULL'),
			"NULL AS category",
			"0 AS requires_prescription",
		];

	$where = [
		'pp.pharma_id = :pharma_id',
		'pp.is_active = 1',
	];

	if ($includeSearchFilter) {
		$searchExprs = [
			"LOWER(CONVERT(pp.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term)",
			"LOWER(CONVERT(COALESCE(pp.description, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term)",
			"LOWER(CONVERT(COALESCE(pp.sku, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term)",
		];
		if ($global_table_exists) {
			$searchExprs[] = "LOWER(CONVERT(COALESCE(gp.name, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term)";
			$searchExprs[] = "LOWER(CONVERT(COALESCE(gp.description, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term)";
			$searchExprs[] = "LOWER(CONVERT(COALESCE(gp.sku, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term)";
		}
		$where[] = '(' . implode(' OR ', $searchExprs) . ')';
	}

	if (!empty($exclude_ids)) {
		$placeholders = [];
		foreach ($exclude_ids as $idx => $id) {
			$placeholders[] = ':exclude_' . $idx;
		}
		$where[] = 'pp.id NOT IN (' . implode(',', $placeholders) . ')';
	}

	$joinSql = $global_table_exists ? 'LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id' : '';

	$selectCols = array_merge($baseColumns, $normalizedColumns);
	return "SELECT
		" . implode(",
		", $selectCols) . "
	FROM jta_pharma_prods pp
	{$joinSql}
	" . build_where_sql($where);
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

$sql = null;
$sql_branch = null;
$sql_generic = null;

try {
	global $pdo;
	$debug_enabled = in_array(strtolower(trim((string)($input['debug'] ?? ''))), ['1', 'true', 'yes', 'on'], true);
	$schema_debug = [];
	
	// Prima verifichiamo se esiste la tabella globale dei prodotti
	$global_table_exists = false;
	$pharma_columns_map = [];
	try {
		$stmt = $pdo->prepare("SHOW TABLES LIKE 'jta_global_prods'");
		$stmt->execute();
		$global_table_exists = $stmt->rowCount() > 0;
	} catch (Exception $e) {
		// Tabella non esiste, continuiamo senza
		$global_table_exists = false;
	}

	$stmtColumns = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jta_pharma_prods'");
	$stmtColumns->execute();
	$pharma_columns = $stmtColumns->fetchAll(PDO::FETCH_COLUMN);
	foreach ($pharma_columns as $columnName) {
		$pharma_columns_map[(string)$columnName] = true;
	}
	$tags_column_exists = !empty($pharma_columns_map['tags']);

	if ($debug_enabled) {
		$schema_debug['file'] = __FILE__;
		$schema_debug['database'] = $pdo->query("SELECT DATABASE()")->fetchColumn();
		$schema_debug['host'] = $pdo->query("SELECT @@hostname")->fetchColumn();
		$schema_debug['port'] = $pdo->query("SELECT @@port")->fetchColumn();

		$stmtTableMeta = $pdo->prepare("SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jta_pharma_prods'");
		$stmtTableMeta->execute();
		$schema_debug['table_meta'] = $stmtTableMeta->fetchAll(PDO::FETCH_ASSOC);

		$stmtColumnCheck = $pdo->prepare("SELECT COLUMN_NAME, COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jta_pharma_prods' AND COLUMN_NAME IN ('is_on_sale', 'is_featured', 'discount_type') GROUP BY COLUMN_NAME");
		$stmtColumnCheck->execute();
		$schema_debug['column_presence'] = $stmtColumnCheck->fetchAll(PDO::FETCH_ASSOC);

		error_log('[product-search][debug] runtime_schema=' . json_encode($schema_debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
		'pharma_columns' => array_keys($pharma_columns_map),
		'exclude_ids_count' => count($exclude_ids),
	]);
	
	// Query correlati
	if ($related_mode) {
		$nameExpr = $global_table_exists ? "COALESCE(pp.name, gp.name)" : "pp.name";
		$imageExpr = $global_table_exists ? "COALESCE(pp.image, gp.image)" : "pp.image";
		$priceExpr = "COALESCE(pp.sale_price, pp.price)";
		$descriptionExpr = $global_table_exists ? "COALESCE(pp.description, gp.description, '')" : "COALESCE(pp.description, '')";
		$categoryExpr = $global_table_exists ? "COALESCE(gp.category, '')" : "''";

		$select_base = build_product_base_select_sql($global_table_exists, $pharma_columns_map, false, $exclude_ids);

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

		$orderConfig = build_related_order_by('base.name', 'base.image', 'COALESCE(base.sale_price, base.price)', $scoreExpr, [
			'is_on_sale' => 'base.is_on_sale',
			'discount_type' => 'base.discount_type',
			'percentage_discount' => 'base.percentage_discount',
		]);
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
			$orderGeneric = build_related_order_by('ranked.name', 'ranked.image', 'COALESCE(ranked.sale_price, ranked.price)', $genericScore, [
				'is_on_sale' => 'ranked.is_on_sale',
				'discount_type' => 'ranked.discount_type',
				'percentage_discount' => 'ranked.percentage_discount',
			]);
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
	$search_select_base = build_product_base_select_sql($global_table_exists, $pharma_columns_map, true, $exclude_ids);
	$sql = "SELECT base.*,
		CASE
			WHEN LOWER(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_prefix) THEN 3
			WHEN LOWER(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term) THEN 2
			WHEN LOWER(CONVERT(base.description USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE LOWER(:search_term) THEN 1
			ELSE 0 END AS search_relevance
		FROM ({$search_select_base}) base
		ORDER BY
			CASE WHEN base.image IS NOT NULL AND TRIM(CONVERT(base.image USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND LOWER(TRIM(CONVERT(base.image USING utf8mb4) COLLATE utf8mb4_unicode_ci)) NOT LIKE '%placeholder%' THEN 1 ELSE 0 END DESC,
			CASE WHEN base.name IS NOT NULL AND TRIM(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND BINARY base.name = BINARY LOWER(base.name) THEN 1 ELSE 0 END DESC,
			CASE WHEN base.name IS NOT NULL AND TRIM(CONVERT(base.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) <> '' AND BINARY base.name = BINARY CONCAT(UPPER(LEFT(base.name, 1)), LOWER(SUBSTRING(base.name, 2))) THEN 1 ELSE 0 END DESC,
			search_relevance DESC,
			base.name ASC
		LIMIT :limit";

	$stmt = $pdo->prepare($sql);
	$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
	$stmt->bindValue(':search_term', $searchLike, PDO::PARAM_STR);
	$stmt->bindValue(':search_prefix', mb_strtolower($search_term, 'UTF-8') . '%', PDO::PARAM_STR);
	foreach ($exclude_ids as $idx => $id) {
		$stmt->bindValue(':exclude_' . $idx, $id, PDO::PARAM_INT);
	}
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
	$errorContext = [
		'file' => __FILE__,
		'pharma_id' => $pharma_id,
		'search_term' => $search_term,
		'related_mode' => $related_mode,
		'sql' => $sql,
		'sql_branch' => $sql_branch,
		'sql_generic' => $sql_generic,
	];
	error_log('[product-search] SQL error: ' . $e->getMessage() . ' | SQLSTATE: ' . $sqlState . ' | context=' . json_encode($errorContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

	echo json_encode([
		'code'    => 500,
		'status'  => FALSE,
		'error'   => 'Internal Server Error',
		'message' => 'Errore durante la ricerca dei prodotti: ' . $e->getMessage() . ' (SQLSTATE: ' . $sqlState . ')',
	]);
} 
