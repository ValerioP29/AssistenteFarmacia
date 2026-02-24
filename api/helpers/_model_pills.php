<?php

class PillsModel {

	/**
	 * Inserisce una nuova pillola quotidiana
	 * @return int|false
	 */
	public static function insert(array $data) {
		global $pdo;

		try {
			if (empty($data['pharma_id'])) {
				return false;
			}
			$stmt = $pdo->prepare("INSERT INTO jta_daily_pills (
				pharma_id, day, category, title, excerpt, content, metadata, is_done
			) VALUES (
				:pharma_id, :day, :category, :title, :excerpt, :content, :metadata, :is_done
			)");

			$stmt->execute([
				':pharma_id'=> $data['pharma_id'],
				':day'      => $data['day'],
				':category' => $data['category'],
				':title'    => $data['title'],
				':excerpt'  => $data['excerpt'] ?? null,
				':content'  => $data['content'] ?? null,
				':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
				':is_done'  => isset($data['is_done']) ? $data['is_done'] : 1
			]);

			return (int) $pdo->lastInsertId();
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Aggiorna una pillola esistente
	 * @return bool
	 */
	public static function update(int $id, int $pharma_id, array $data): bool {
		global $pdo;

		try {
			$fields = [];
			$values = [];

			foreach ($data as $key => $value) {
				if ($key === 'metadata' && is_array($value)) {
					$value = json_encode($value);
				}
				$fields[] = "$key = :$key";
				$values[":$key"] = $value;
			}

			$values[":id"] = $id;
			$values[":pharma_id"] = $pharma_id;
			$sql = "UPDATE jta_daily_pills SET " . implode(", ", $fields) . " WHERE id = :id AND pharma_id = :pharma_id";
			$stmt = $pdo->prepare($sql);

			return $stmt->execute($values);
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Elimina una pillola dal database.
	 * Se $hard_delete è false, applica una cancellazione soft (imposta deleted_at).
	 * Se $hard_delete è true, elimina fisicamente il record.
	 *
	 * @param int $id
	 * @param bool $hard_delete
	 * @return bool
	 */
	public static function delete(int $id, int $pharma_id, bool $hard_delete = false): bool {
		global $pdo;

		try {
			if ($hard_delete) {
				$stmt = $pdo->prepare("DELETE FROM jta_daily_pills WHERE id = :id AND pharma_id = :pharma_id");
				return $stmt->execute([':id' => $id, ':pharma_id' => $pharma_id]);
			}

			// Soft delete
			$stmt = $pdo->prepare("UPDATE jta_daily_pills SET deleted_at = NOW() WHERE id = :id AND pharma_id = :pharma_id");
			return $stmt->execute([':id' => $id, ':pharma_id' => $pharma_id]);

		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Trova una pillola per una data specifica, escludendo eventuali date future.
	 *
	 * @param string $date Data da cercare (formato YYYY-MM-DD)
	 * @return array|false
	 */
	public static function findByDate(string $date, int $pharma_id) {
		global $pdo;

		try {
			$stmt = $pdo->prepare("
				SELECT * FROM jta_daily_pills 
				WHERE day = :day 
				AND pharma_id = :pharma_id
				AND deleted_at IS NULL 
				AND day <= CURDATE() 
				AND is_done = 1
				LIMIT 1
			");
			$stmt->execute([':day' => $date, ':pharma_id' => $pharma_id]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($row && !empty($row['metadata'])) {
				$decoded = json_decode($row['metadata'], true);
				$row['metadata'] = is_array($decoded) ? $decoded : null;
			}

			return $row ?: false;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Trova la pillola con ID specificato, escludendo eventuali date future.
	 *
	 * @param $id ID da cercare
	 * @return array|false
	 */
	public static function findById($id, int $pharma_id) {
		global $pdo;

		try {
			$stmt = $pdo->prepare("
				SELECT * FROM jta_daily_pills 
				WHERE id = :id 
				AND pharma_id = :pharma_id
				AND deleted_at IS NULL 
				AND day <= CURDATE() 
				AND is_done = 1
				LIMIT 1
			");
			$stmt->execute([':id' => $id, ':pharma_id' => $pharma_id]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($row && !empty($row['metadata'])) {
				$decoded = json_decode($row['metadata'], true);
				$row['metadata'] = is_array($decoded) ? $decoded : null;
			}

			return $row ?: false;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Trova il gruppo di pillole per una data specifica, escludendo eventuali date future.
	 *
	 * @param string $date Data da cercare (formato YYYY-MM-DD)
	 * @return array
	 */
	public static function findGroupByDate(string $date, int $pharma_id) {
		global $pdo;

		try {
			$stmt = $pdo->prepare("
				SELECT * FROM jta_daily_pills 
				WHERE day = :day 
				AND pharma_id = :pharma_id
				AND deleted_at IS NULL 
				AND day <= CURDATE() 
				AND is_done = 1
			");
			$stmt->execute([':day' => $date, ':pharma_id' => $pharma_id]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

			foreach( $rows AS $_index => $_row ){
				if ($_row && !empty($_row['metadata'])) {
					$decoded = json_decode($_row['metadata'], true);
					$_row['metadata'] = is_array($decoded) ? $decoded : null;
					$rows[$_index]['metadata'] = $_row;
				}
			}

			return $rows ?: [];
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Restituisce le ultime $limit pillole ordinate per data (campo day) in ordine decrescente, ma non con data futura.
	 *
	 * @param int $limit Numero massimo di risultati da restituire
	 * @return array
	 */
	public static function getLatest(int $limit, int $pharma_id, bool $not_future = TRUE ): array {
		global $pdo;

		try {
			if( $not_future ){
				$stmt = $pdo->prepare("
					SELECT * FROM jta_daily_pills 
					WHERE deleted_at IS NULL 
					AND pharma_id = :pharma_id
					AND day <= CURDATE()
					AND is_done = 1
					ORDER BY day DESC 
					LIMIT :limit
				");
			}else{
				$stmt = $pdo->prepare("
					SELECT * FROM jta_daily_pills 
					WHERE deleted_at IS NULL 
					AND pharma_id = :pharma_id
					AND is_done = 1
					ORDER BY day DESC 
					LIMIT :limit
				");
			}

			$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->execute();

			return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Trova il gruppo di pillole per la data piu' recente, escludendo eventuali date future.
	 *
	 * @return array
	 */
	public static function getLastGroup( int $pharma_id, $not_future = TRUE ) {
		global $pdo;

		try {
			if( $not_future ){
				$stmt = $pdo->prepare("
					SELECT * FROM jta_daily_pills
					WHERE deleted_at IS NULL
					AND pharma_id = :pharma_id
					AND is_done = 1
					AND day = (
						SELECT MAX(day)
						FROM jta_daily_pills
						WHERE deleted_at IS NULL
						AND pharma_id = :pharma_id
						AND is_done = 1
						AND day <= CURDATE()
					)
				");
			}else{
				$stmt = $pdo->prepare("
					SELECT * FROM jta_daily_pills
					WHERE deleted_at IS NULL
					AND pharma_id = :pharma_id
					AND is_done = 1
					AND day = (
						SELECT MAX(day)
						FROM jta_daily_pills
						WHERE deleted_at IS NULL
						AND pharma_id = :pharma_id
						AND is_done = 1
					)
				");
			}

			$stmt->execute([':pharma_id' => $pharma_id]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

			foreach( $rows AS $_index => $_row ){
				if ($_row && !empty($_row['metadata'])) {
					$decoded = json_decode($_row['metadata'], true);
					$_row['metadata'] = is_array($decoded) ? $decoded : null;
					$rows[$_index]['metadata'] = $_row;
				}
			}

			return $rows ?: [];
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Restituisce tutte le pillole il cui campo day è compreso tra due date,
	 * escludendo eventuali date future.
	 *
	 * @param string $from Data inizio (formato 'YYYY-MM-DD')
	 * @param string $to   Data fine (formato 'YYYY-MM-DD')
	 * @return array
	 */
	public static function getByDateRange(string $from, string $to, int $pharma_id): array {
		global $pdo;

		try {
			$stmt = $pdo->prepare("
				SELECT * FROM jta_daily_pills 
				WHERE deleted_at IS NULL 
				AND pharma_id = :pharma_id
				AND day BETWEEN :from AND :to
				AND day <= CURDATE()
				AND is_done = 1
				ORDER BY day ASC
			");
			$stmt->execute([
				':pharma_id' => $pharma_id,
				':from' => $from,
				':to'   => $to
			]);

			return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Restituisce tutte le pillole attive, escludendo eventuali date future.
	 * @return array
	 */
	public static function all(int $pharma_id): array {
		global $pdo;

		try {
			$stmt = $pdo->prepare("SELECT * FROM jta_daily_pills WHERE deleted_at IS NULL AND pharma_id = :pharma_id AND day <= CURDATE() AND is_done = 1 ORDER BY day DESC");
			$stmt->execute([':pharma_id' => $pharma_id]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

			foreach ($rows as &$row) {
				if (!empty($row['metadata'])) {
					$decoded = json_decode($row['metadata'], true);
					$row['metadata'] = is_array($decoded) ? $decoded : null;
				}
			}

			return $rows;
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Restituisce una pillola casuale tra quelle non eliminate e con data non futura.
	 *
	 * @return array|false
	 */
	public static function getRandom(int $pharma_id) {
		global $pdo;

		$stmt = $pdo->prepare("
			SELECT * FROM jta_daily_pills
			WHERE deleted_at IS NULL
			AND pharma_id = :pharma_id
			AND day <= CURDATE()
			AND is_done = 1
			ORDER BY RAND()
			LIMIT 1
		");
		$stmt->execute([':pharma_id' => $pharma_id]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
	}

	/**
	 * Restituisce una pillola casuale per una specifica categoria, ma non con data futura.
	 * @param string $category
	 * @return array|false
	 */
	public static function getRandomByCategory(string $category, int $pharma_id) {
		global $pdo;

		$stmt = $pdo->prepare("
			SELECT * FROM jta_daily_pills
			WHERE deleted_at IS NULL AND day <= CURDATE() AND category = :category AND pharma_id = :pharma_id
			ORDER BY RAND()
			LIMIT 1
		");
		$stmt->execute([':category' => $category, ':pharma_id' => $pharma_id]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
	}

	/**
	 * Restituisce l'ultima pillola per una specifica categoria (in base alla data), ma non con data futura.
	 * @param string $category
	 * @return array|false
	 */
	public static function getLastByCategory(string $category, int $pharma_id) {
		global $pdo;

		$stmt = $pdo->prepare("
			SELECT * FROM jta_daily_pills
			WHERE deleted_at IS NULL AND day <= CURDATE() AND is_done = 1 AND category = :category AND pharma_id = :pharma_id
			ORDER BY day DESC
			LIMIT 1
		");
		$stmt->execute([':category' => $category, ':pharma_id' => $pharma_id]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
	}

	/**
	 * Restituisce tutte le pillole di una categoria in ordine decrescente di data, ma non con data futura.
	 * @param string $category
	 * @param int|null $limit
	 * @return array
	 */
	public static function getAllByCategory(string $category, int $limit, int $pharma_id): array {
		global $pdo;

		$sql = "
			SELECT * FROM jta_daily_pills
			WHERE deleted_at IS NULL AND day <= CURDATE() AND is_done = 1 AND category = :category AND pharma_id = :pharma_id
			ORDER BY day DESC
		";
		if (!is_null($limit)) {
			$sql .= " LIMIT :limit";
		}

		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(':category', $category, PDO::PARAM_STR);
		if (!is_null($limit)) {
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		}

		$stmt->bindValue(':pharma_id', $pharma_id, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
	}

	/**
	 * Restituisce un array con tutte le categorie uniche presenti (non eliminate), ma non con data futura.
	 * @return array
	 */
	public static function getDistinctCategories(int $pharma_id): array {
		global $pdo;

		$stmt = $pdo->prepare("
			SELECT DISTINCT category
			FROM jta_daily_pills
			WHERE deleted_at IS NULL AND day <= CURDATE() AND pharma_id = :pharma_id
			ORDER BY category ASC
		");
		$stmt->execute([':pharma_id' => $pharma_id]);

		$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
		return $categories ?: [];
	}

	/**
	 * Genera ed inserisce una nuova pillola quotidiana nel database
	 * @return int|false
	 */
	public static function insertFromAI( string $date, string $category, int $pharma_id ) {
		if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
		if( ! is_valid_date($date) ) return FALSE;
		if( self::findByDate($date, $pharma_id) ) return FALSE;
		if( ! isset($category) OR empty($category) ) $category = get_random_profiling_category();

		$pill_data = openai_generate_daily_pill( $date, $category );

		if( ! $pill_data ) return FALSE;
		// $pill_data['metadata'] = $pill_data;
		return self::insert(array_merge($pill_data, ['pharma_id' => $pharma_id]));
	}

	/**
	 * Cerca una pillola per data senza avere altre restrizioni
	 *
	 * @param string $date Data da cercare (formato YYYY-MM-DD)
	 * @param string $category categoria da cercare
	 * @return array|false
	 */
	private static function _check_pill_for_generation(string $date, string $category, int $pharma_id) {
		global $pdo;

		try {
			$stmt = $pdo->prepare("
				SELECT * FROM jta_daily_pills 
				WHERE deleted_at IS NULL 
				AND day = :day 
				AND pharma_id = :pharma_id
				AND category = :category
				LIMIT 1
			");
			$stmt->execute([
				':day' => $date,
				':pharma_id' => $pharma_id,
				':category' => $category,
			]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($row && !empty($row['metadata'])) {
				$decoded = json_decode($row['metadata'], true);
				$row['metadata'] = is_array($decoded) ? $decoded : null;
			}

			return $row ?: false;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Genera pillole vuote per una giornata
	 * @return true|false
	 */
	public static function generateEmptyPillsByDate( string $date, int $pharma_id ) {
		if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
		if( ! is_valid_date($date) ) return FALSE;

		$cats = get_profiling_categories();

		foreach( $cats AS $_cat_name ){
			if( self::_check_pill_for_generation($date, $_cat_name, $pharma_id) ) continue;

			self::insert([
				'pharma_id' => $pharma_id,
				'day'      => $date,
				'category' => $_cat_name,
				'title'    => '',
				'excerpt'  => '',
				'content'  => '',
				'metadata' => NULL,
				'is_done'  => 0,
			]);
		}

		return TRUE;
	}

	public static function populateAnEmptyPill(int $pharma_id) {
		global $pdo;

		try {
			$stmt = $pdo->prepare("
				SELECT * FROM jta_daily_pills 
				WHERE deleted_at IS NULL 
				AND pharma_id = :pharma_id
				AND is_done = 0
				LIMIT 1
			");
			$stmt->execute([':pharma_id' => $pharma_id]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($row) {
				$pill_data = openai_generate_daily_pill( $row['day'], $row['category'] );
				self::update( $row['id'], $pharma_id, [
					'title'    => $pill_data['title'],
					'excerpt'  => $pill_data['excerpt'],
					'content'  => $pill_data['content'],
					// 'metadata' => $pill_data['metadata'],
					'is_done'  => 1,
				] );
			}

			return $row ?: false;
		} catch (Exception $e) {
			return false;
		}

	}

	public static function countEmptyPills(int $pharma_id) {
		global $pdo;
		
		$sql = "SELECT COUNT(*) FROM jta_daily_pills WHERE is_done = 0 AND pharma_id = :pharma_id";
		$stmt = $pdo->prepare($sql);
		$stmt->execute([':pharma_id' => $pharma_id]);
		
		return (int) $stmt->fetchColumn();
	}

}
