<?php

class ProductsModel {

	/**
	 * Inserisce un nuovo prodotto
	 * @return int|false
	 */
	/*
	public static function insert(array $data) {
		global $pdo;

		try {
			$stmt = $pdo->prepare("INSERT INTO jta_pharma_prods (
				pharma_id, product_id, price, sale_price, num_items, sku,
				description, image, name, is_active, is_on_sale, sale_start_date, sale_end_date
			) VALUES (
				:pharma_id, :product_id, :price, :sale_price, :num_items, :sku,
				:description, :image, :name, :is_active, :is_on_sale, :sale_start_date, :sale_end_date
			)");

			$stmt->execute([
				':pharma_id' => $data['pharma_id'],
				':product_id' => $data['product_id'],
				':price' => $data['price'],
				':sale_price' => $data['sale_price'],
				':num_items' => $data['num_items'],
				':sku' => $data['sku'],
				':description' => $data['description'],
				':image' => $data['image'],
				':name' => $data['name'],
				':is_active' => isset($data['is_active']) ? $data['is_active'] : 1,
				':is_on_sale' => isset($data['is_on_sale']) ? $data['is_on_sale'] : 0,
				':sale_start_date' => $data['sale_start_date'],
				':sale_end_date' => $data['sale_end_date']
			]);

			return (int) $pdo->lastInsertId();
		} catch (Exception $e) {
			return false;
		}
	}
	*/

	public static function update($id, array $params) {
		global $pdo;

		try {
			if (empty($params)) return false;

			$fields = [];
			$values = [];

			foreach ($params as $key => $value) {
				$fields[] = "$key = :$key";
				$values[":$key"] = $value;
			}

			// $fields[] = "updated_at = NOW()";
			$values[":id"] = $id;

			$sql = "UPDATE jta_pharma_prods SET " . implode(", ", $fields) . " WHERE id = :id";
			$stmt = $pdo->prepare($sql);
			return $stmt->execute($values);
		} catch (Exception $e) {
			return false;
		}
	}

	/*
	public static function delete($id) {
		global $pdo;

		try {
			$stmt = $pdo->prepare("DELETE FROM jta_pharma_prods WHERE id = :id");
			return $stmt->execute([':id' => $id]);
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
	 * Una promozione è considerata valida se:
	 * - il prodotto è attivo (is_active = 1)
	 * - è contrassegnato come in promozione (is_on_sale = 1)
	 * - ha un sale_price definito
	 * - eventuali date di inizio/fine promozione sono rispettate
	 *
	 * @param int $pharma_id ID della farmacia
	 * @param int|null $limit Numero massimo di risultati da restituire
	 * @param int|null $offset Offset per la paginazione
	 * @return array Elenco dei prodotti in promozione validi
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

	/**
	 * Verifica se un prodotto è una promozione valida.
	 * Una promo è valida se:
	 * - è contrassegnata come promozione (is_on_sale = 1)
	 * - ha un prezzo promozionale definito e numerico
	 * - eventuali date di inizio/fine rientrano nell'intervallo attuale (se presenti)
	 *
	 * @param array $product Dati del prodotto
	 * @return bool True se il prodotto è una promo valida, false altrimenti
	 */
	public static function isPromo(array $product): bool {
		if (
			empty($product['is_on_sale']) ||
			empty($product['sale_price']) ||
			!is_numeric($product['sale_price'])
		) {
			return false;
		}

		$now = date('Y-m-d H:i:s');

		// Se c'è una data di inizio, il prodotto deve essere attivo da quella data
		if (!empty($product['sale_start_date']) && $now < $product['sale_start_date']) {
			return false;
		}

		// Se c'è una data di fine, il prodotto non deve essere scaduto
		if (!empty($product['sale_end_date']) && $now > $product['sale_end_date']) {
			return false;
		}

		return true;
	}

	/**
	 * Restituisce una descrizione testuale del motivo per cui una promo è valida o non valida.
	 * Utile per debugging o per mostrare messaggi all’utente/amministratore.
	 *
	 * @param array $product Dati del prodotto
	 * @return string Messaggio che descrive lo stato della promozione
	 */
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
