<?php
require_once('_api_bootstrap.php');
require_once __DIR__ . '/../taxonomy/tags.php';
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

$limit_default = 80;
$limit = $_GET['limit'] ?? $limit_default;
if( $limit < 1 ) $limit = 1;
if( $limit > $limit_default ) $limit = $limit_default;

$tipo   = $_GET['tipo'] ?? null;
$tag    = trim((string)($_GET['tag'] ?? ''));
$pharma = getMyPharma();
$is_really_filtered = FALSE;

function normalize_promo_tags($rawTags) {
	if ($rawTags === null) return [];
	if (is_array($rawTags)) {
		$tags = $rawTags;
	} else {
		$rawTags = trim((string)$rawTags);
		if ($rawTags === '') return [];
		$decoded = json_decode($rawTags, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
			$tags = $decoded;
		} else {
			$tags = explode(',', $rawTags);
		}
	}

	$normalized = [];
	foreach ($tags as $tagValue) {
		if (is_array($tagValue) || is_object($tagValue)) continue;
		$clean = canonicalizeTag(trim((string)$tagValue));
		if ($clean === '') continue;
		$normalized[$clean] = true;
	}

	return array_keys($normalized);
}

// ── Sorgente prodotti ─────────────────────────────────────────────────────
//
// Con $tag: tutti i prodotti ATTIVI con quel tag (non solo promo).
//   Il filtro per tag ha senso su tutto il catalogo — l'utente vuole vedere
//   "tutti i prodotti tosse", non solo quelli con sale_price impostato.
//
// Senza $tag (o con $tipo): solo le promo reali (is_on_sale + sale_price).
//

if ($tag !== '') {
	global $pdo;
	$normalizedTag = canonicalizeTag($tag);

	// Filtro per tag direttamente in SQL con JSON_CONTAINS —
	// evita di caricare tutti i prodotti della farmacia in memoria
	$stmt = $pdo->prepare("
		SELECT * FROM jta_pharma_prods
		WHERE pharma_id = :pharma_id
		  AND is_active = 1
		  AND JSON_VALID(tags)
		  AND JSON_CONTAINS(tags, :tag_json)
		ORDER BY name ASC
		LIMIT :limit
	");
	$stmt->bindValue(':pharma_id', (int)$pharma['id'], PDO::PARAM_INT);
	$stmt->bindValue(':tag_json',  json_encode($normalizedTag), PDO::PARAM_STR);
	$stmt->bindValue(':limit',     (int)$limit, PDO::PARAM_INT);
	$stmt->execute();
	$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$is_really_filtered = TRUE;

} else {
	// Modalità promo classica
	$products = ProductsModel::findPromosByPharma($pharma['id'], $limit);

	if (isset($tipo)) {
		$filtered_ids = [];

		if ($tipo === mb_strtolower('ottobre-rosa')) {
			if (is_localhost()) { $filtered_ids = [14]; }
			else { $filtered_ids = [7109, 7106, 7103, 7104, 7105, 7099, 7100, 7108, 7107, 7102, 7094, 7095, 7096, 7097, 7098, 7101]; }
		}
		elseif ($tipo === mb_strtolower('bionike-1-1')) {
			if (is_localhost()) { $filtered_ids = [104]; }
			else { $filtered_ids = [7110, 7111, 7113, 7114, 7115]; }
		}

		if (!empty($filtered_ids)) {
			$tmp_products = [];
			foreach ($filtered_ids as $_id) {
				$tmp_products[] = ProductsModel::findPharmaPromoById($pharma['id'], $_id);
			}
			$products = array_values(array_filter($tmp_products));
			$is_really_filtered = TRUE;
		}
	}
}

echo json_encode([
	'code'    => 200,
	'status'  => TRUE,
	'message' => NULL,
	'data'    => [
		'products' => array_map('normalize_product_data', array_slice($products, 0, $limit)),
		'filtered' => $is_really_filtered,
		'tag'      => $tag !== '' ? canonicalizeTag($tag) : null,
	],
]);