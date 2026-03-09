<?php

require_once(__DIR__ . '/_related_tags.php');
// taxonomy/tags.php è già caricata transitivamente da _related_tags.php

class ProductsModel {

	private static function getColumnAvailability(){
		global $pdo;
		static $cache = null;
		if ($cache !== null) return $cache;

		$columns = [
			'category' => false,
		];

		try {
			$stmt = $pdo->prepare("SHOW COLUMNS FROM jta_pharma_prods LIKE 'category'");
			$stmt->execute();
			$columns['category'] = $stmt->rowCount() > 0;
		} catch (Exception $e) {
			$columns['category'] = false;
		}

		$cache = $columns;
		return $cache;
	}

	private static function shouldAutoTag($incomingTags, $existingTags, $forceAutotag = false){
		if ($forceAutotag) return true;

		$incoming = trim((string)$incomingTags);
		if ($incoming !== '') return false;

		$existing = trim((string)$existingTags);
		return $existing === '';
	}

	private static function prepareAutoTagsForUpdate($id, array &$params){
		global $pdo;

		/**
		 * FIX: canonicalizeTag() su ciascun elemento prima dell'encode.
		 * Risolve alias legacy (es. "dermocosmetica" → "dermocosmesi") quando
		 * il panel passa tag a mano via array. La logica shouldAutoTag, la query
		 * di lettura e tutto il resto rimangono invariati.
		 * Prima: array_map('strval', ...) — nessuna canonicalizzazione.
		 */
		if (isset($params['tags']) && is_array($params['tags'])) {
			$canonicalized = array_map(
				function($t){ return canonicalizeTag((string)$t); },  // ← FIX
				$params['tags']
			);
			$params['tags'] = json_encode(
				array_values(array_unique(array_filter($canonicalized))),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
		}

		$forceAutotag = !empty($params['force_autotag']);
		unset($params['force_autotag']);

		$columns = self::getColumnAvailability();
		$selectCategory = $columns['category'] ? "category" : "'' AS category";

		$stmt = $pdo->prepare("SELECT id, name, description, {$selectCategory}, tags FROM jta_pharma_prods WHERE id = :id LIMIT 1");
		$stmt->execute([':id' => $id]);
		$current = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$current) return;

		$incomingTags = $params['tags'] ?? '';
		$existingTags = $current['tags'] ?? '';
		if (!self::shouldAutoTag($incomingTags, $existingTags, $forceAutotag)) return;

		$name = $params['name'] ?? $current['name'] ?? '';
		$description = $params['description'] ?? $current['description'] ?? '';
		$category = ($columns['category'] ? ($params['category'] ?? $current['category'] ?? '') : '');

		// related_tags_infer_from_product() restituisce già slug canonici (taxonomy)
		$autoTags = related_tags_infer_from_product($name, $description, $category);

		if (!empty($autoTags)) {
			$params['tags'] = json_encode($autoTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
	}

	/**
	 * Inserisce un nuovo prodotto
	 * @return int|false
	 */
	/*
	public static function insert(array $data) { ... }
	*/

	public static function update($id, array $params) {
		global $pdo;

		try {
			if (empty($params)) return false;
			self::prepareAutoTagsForUpdate($id, $params);

			$fields = [];
			$values = [];

			foreach ($params as $key => $value) {
				$fields[] = "$key = :$key";
				$values[":$key"] = $value;
			}

			$values[":id"] = $id;

			$sql = "UPDATE jta_pharma_prods SET " . implode(", ", $fields) . " WHERE id = :id";
			$stmt = $pdo->prepare($sql);
			return $stmt->execute($values);
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Restituisce tutti i prodotti attivi di una farmacia
	 * @return array
	 */
	public static function findByPharma($pharma_id, $limit = null, $offset = null) {
		global $pdo;

		try {
			$sql = "SELECT * FROM jta_pharma_prods WHERE pharma_id = :pharma_id AND is_active = 1 ORDER BY name ASC";
			if (!is_null($limit)) {
				$sql .= " LIMIT :limit";
				if (!is_null($offset)) {
					$sql .= " OFFSET :offset";
				}
			}

			$stmt = $pdo->prepare($sql);
			$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
			if (!is_null($limit)) {
				$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
				if (!is_null($offset)) {
					$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
				}
			}

			$stmt->execute();
			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
			return $results ?: [];
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Cerca un prodotto attivo per ID
	 * @return array|false
	 */
	public static function findById($id) {
		global $pdo;

		try {
			$stmt = $pdo->prepare("SELECT * FROM jta_pharma_prods WHERE id = :id AND is_active = 1");
			$stmt->execute([':id' => $id]);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			return $result ? $result : false;
		} catch (Exception $e) {
			return false;
		}
	}

	public static function findByIds($ids) {
		$products = [];

		foreach( $ids AS $_id ){
			$_product = self::findById($_id);
			if( $_product ) $products[] = $_product;
		}

		return $products;
	}

	/**
	 * Restituisce tutte le promozioni attive di una farmacia.
	 * @return array
	 */
	public static function findPromosByPharma($pharma_id, $limit = null, $offset = null) {
		global $pdo;

		try {
			$sql = "SELECT * FROM jta_pharma_prods 
				WHERE pharma_id = :pharma_id 
					AND is_active = 1 
					AND is_on_sale = 1 
					AND sale_price IS NOT NULL 
					AND (
						(sale_start_date IS NULL OR sale_start_date <= NOW()) AND 
						(sale_end_date IS NULL OR sale_end_date >= NOW())
					)
				ORDER BY name ASC";

			if (!is_null($limit)) {
				$sql .= " LIMIT :limit";
				if (!is_null($offset)) {
					$sql .= " OFFSET :offset";
				}
			}

			$stmt = $pdo->prepare($sql);
			$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);

			if (!is_null($limit)) {
				$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
				if (!is_null($offset)) {
					$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
				}
			}

			$stmt->execute();
			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
			return $results ?: [];

		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Cerca una promo attiva per ID
	 * @return array|false
	 */
	public static function findPharmaPromoById($pharma_id, $product_id) {
		global $pdo;

		try {
			$sql = "SELECT * FROM jta_pharma_prods 
					WHERE id = :id 
					AND pharma_id = :pharma_id
					AND is_active = 1 
					AND is_on_sale = 1 
					AND sale_price IS NOT NULL 
					AND (
						(sale_start_date IS NULL OR sale_start_date <= NOW()) AND 
						(sale_end_date IS NULL OR sale_end_date >= NOW())
					)";

			$stmt = $pdo->prepare($sql);
			$stmt->execute([
				':id' => $product_id,
				':pharma_id' => $pharma_id
			]);

			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			return $result ? $result : false;
		} catch (Exception $e) {
			return false;
		}
	}

	public static function isPromo(array $product): bool {
		if (
			empty($product['is_on_sale']) ||
			empty($product['sale_price']) ||
			!is_numeric($product['sale_price'])
		) {
			return false;
		}

		$now = date('Y-m-d H:i:s');

		if (!empty($product['sale_start_date']) && $now < $product['sale_start_date']) {
			return false;
		}

		if (!empty($product['sale_end_date']) && $now > $product['sale_end_date']) {
			return false;
		}

		return true;
	}

	public static function getPromoStatusMessage(array $product): string {
		if (empty($product['is_on_sale']) || (int)$product['is_on_sale'] !== 1) {
			return "Il prodotto non è contrassegnato come promo.";
		}

		if (empty($product['sale_price']) || !is_numeric($product['sale_price'])) {
			return "Il prezzo promozionale non è valido.";
		}

		$now = date('Y-m-d H:i:s');

		if (!empty($product['sale_start_date']) && $now < $product['sale_start_date']) {
			return "La promozione non è ancora attiva.";
		}

		if (!empty($product['sale_end_date']) && $now > $product['sale_end_date']) {
			return "La promozione è scaduta.";
		}

		return "Promo valida";
	}
}