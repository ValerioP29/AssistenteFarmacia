<?php

class QuizzesModel {
	/**
	 * Restituisce tutti i quiz presenti in tabella.
	 */
	public static function getAll(int $pharma_id): array {
		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_quizzes WHERE pharma_id = :pharma_id ORDER BY date DESC");
		$stmt->execute([':pharma_id' => $pharma_id]);
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!$results) return [];

		foreach ($results as &$row) {
			$row = self::decodeJsonFields($row);
		}

		return $results;
	}

	/**
	 * Restituisce il quiz relativo a una specifica data (formato YYYY-MM-DD).
	 */
	public static function getByDate(string $date, int $pharma_id) {
		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_quizzes WHERE date = :date AND pharma_id = :pharma_id LIMIT 1");
		$stmt->execute([':date' => $date, ':pharma_id' => $pharma_id]);

		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		return $result ? self::decodeJsonFields($result) : false;
	}

	/**
	 * Restituisce l'ultimo quiz disponibile con data non futura.
	 */
	public static function getLastAvailable( int $pharma_id, $not_future = TRUE ) {
		global $pdo;
		$query = "SELECT * FROM jta_quizzes WHERE pharma_id = :pharma_id";
		$query .= $not_future ? " AND date <= CURDATE()" : "";
		$query .= " ORDER BY date DESC LIMIT 1";
		$stmt = $pdo->prepare($query);
		$stmt->execute([':pharma_id' => $pharma_id]);

		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		return $result ? self::decodeJsonFields($result) : false;
	}

	/**
	 * Restituisce il quiz del giorno (se esiste).
	 */
	public static function getToday(int $pharma_id) {
		return self::getByDate(date('Y-m-d'), $pharma_id);
	}

	/**
	 * Inserisce un nuovo quiz.
	 */
	public static function insert(array $data) {
		global $pdo;

		if (empty($data['pharma_id'])) {
			return false;
		}

		// Controllo su metadata: se è array o oggetto, lo codifichiamo in JSON
		if (isset($data['metadata']) && (is_array($data['metadata']) || is_object($data['metadata']))) {
			$data['metadata'] = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$data['created_at'] = date('Y-m-d H:i:s');
		$data['updated_at'] = $data['created_at'];

		$sql = "INSERT INTO jta_quizzes (pharma_id, date, points, metadata, created_at, updated_at)
				VALUES (:pharma_id, :date, :points, :metadata, :created_at, :updated_at)";

		$stmt = $pdo->prepare($sql);
		$stmt->execute($data);

		return (int) $pdo->lastInsertId();
	}

	/**
	 * Aggiorna un quiz esistente.
	 */
	public static function update(string $date, int $pharma_id, array $data): bool {
		global $pdo;

		// Controllo su metadata: se è array o oggetto, lo codifichiamo in JSON
		if (isset($data['metadata']) && (is_array($data['metadata']) || is_object($data['metadata']))) {
			$data['metadata'] = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$data['updated_at'] = date('Y-m-d H:i:s');

		$fields = [];
		foreach ($data as $key => $val) {
			$fields[] = "$key = :$key";
		}

		$data['date'] = $date;
		$data['pharma_id'] = $pharma_id;

		$sql = "UPDATE jta_quizzes SET " . implode(", ", $fields) . " WHERE date = :date AND pharma_id = :pharma_id";
		$stmt = $pdo->prepare($sql);

		return $stmt->execute($data);
	}

	/**
	 * Decodifica automatica dei campi JSON di un quiz.
	 */
	private static function decodeJsonFields(array $row, array $fields = ['metadata']): array {
		foreach ($fields as $field) {
			if (isset($row[$field])) {
				$row[$field] = json_decode($row[$field], true);
			}
		}
		return $row;
	}

	/**
	 * Genera ed inserisce una nuova pillola quotidiana nel database
	 * @return int|false
	 */
	public static function insertFromAI( string $date, int $points, string $topic, int $pharma_id ) {
		if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
		if( ! is_valid_date($date) ) return FALSE;
		if( self::getByDate($date, $pharma_id) ) return FALSE;
		if ( ! isset($topic) OR empty($topic) ) $topic = get_random_quiz_category();
		if( empty($points) ) $points = get_option('point--quiz_daily', 3);
		if( $points < 1 ) $points = get_option('point--quiz_daily', 3);

		$quiz_data = openai_generate_daily_quiz($topic, $date);
		if( ! $quiz_data ) return FALSE;

		return self::insert([
			'pharma_id'=> $pharma_id,
			'date'     => $date,
			'points'   => $points,
			'metadata' => $quiz_data,
		]);
	}

	/**
	 * Normalizza un record quiz per l'output API JSON.
	 */
	public static function normalize(array $quiz) {
		if (!$quiz) return false;

		$quiz['metadata']['id'] = (int) $quiz['id'];
		$quiz['metadata']['points'] = (int) $quiz['points'];
		return $quiz['metadata'];
	}

	/**
	 * Prepara i dati da un JSON completo per l'inserimento nel database.
	 */
	/*
	public static function prepareForInsert(string $date, int $points, array $quiz_normalized): array {
		return [
			'date' => $date,
			'title' => $quiz_normalized['header']['title'] ?? '',
			'description' => $quiz_normalized['header']['description'] ?? '',
			'points' => $points,
			'steps' => $quiz_normalized['header']['steps'] ?? 0,
			'questions' => json_encode($quiz_normalized['questions']),
			'profiles' => json_encode($quiz_normalized['profiles']),
			'mini_guide' => json_encode($quiz_normalized['mini_guide']),
			'recommended_products' => json_encode($quiz_normalized['recommended_products']),
		];
	}
	*/
}

/**
 * Funzione standalone per normalizzare un quiz, basata sul metodo statico della classe.
 */
function normalize_quiz_data(array $quiz) {
	return QuizzesModel::normalize($quiz);
}


/**
 * Funzione standalone per preparare i dati per l'inserimento nel database.
 */
/*
function prepare_quiz_data_for_insert(string $date, int $points, array $quiz_normalized): array {
	return QuizzesModel::prepareForInsert($date, $points, $quiz_normalized);
}
*/
