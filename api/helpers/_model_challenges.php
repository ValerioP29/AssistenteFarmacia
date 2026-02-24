<?php

class ChallengesModel {
	/**
	 * Restituisce tutte le sfide ordinate per data decrescente.
	 */
	public static function getAll(int $pharma_id): array {
		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_week_challenges WHERE pharma_id = :pharma_id ORDER BY date_start DESC");
		$stmt->execute([':pharma_id' => $pharma_id]);
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $results ? $results : [];
	}

	/**
	 * Restituisce una sfida corrispondente alla data di inizio indicata.
	 */
	public static function getByDate(string $date_start, int $pharma_id) {
		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_week_challenges WHERE date_start = :date_start AND pharma_id = :pharma_id LIMIT 1");
		$stmt->execute([':date_start' => get_week_start_date($date_start), ':pharma_id' => $pharma_id]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
	}

	public static function getById( $id, int $pharma_id ) {
		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_week_challenges WHERE id = :id AND pharma_id = :pharma_id LIMIT 1");
		$stmt->execute([':id' => $id, ':pharma_id' => $pharma_id]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: FALSE;
	}

	/**
	 * Restituisce la sfida relativa alla settimana corrente.
	 */
	public static function getCurrentWeek(int $pharma_id) {
		return self::getByDate(get_week_start_date(date('Y-m-d')), $pharma_id);
	}

	public static function getNextWeek(int $pharma_id) {
		$dt = new DateTime();
		$dt->modify('next monday');
		$next_week = $dt->format('Y-m-d');
		return self::getByDate(get_week_start_date($next_week), $pharma_id);
	}

	/**
	 * Inserisce una nuova sfida.
	 */
	public static function insert(array $data) {
		global $pdo;

		if (empty($data['pharma_id'])) {
			return false;
		}

		if (empty($data['date_start'])) {
			$data['date_start'] = get_week_start_date(date('Y-m-d'));
		}

		// Controllo su metadata: se è array o oggetto, lo codifichiamo in JSON
		if (isset($data['metadata']) && (is_array($data['metadata']) || is_object($data['metadata']))) {
			$data['metadata'] = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		// Controllo su metadata: se è array o oggetto, lo codifichiamo in JSON
		if (isset($data['instructions']) && (is_array($data['instructions']) || is_object($data['instructions']))) {
			$data['instructions'] = json_encode($data['instructions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$data['created_at'] = $data['updated_at'] = date('Y-m-d H:i:s');

		$sql = "INSERT INTO jta_week_challenges (pharma_id, date_start, points, title, description, instructions, reward, icon, metadata, created_at, updated_at)
				VALUES (:pharma_id, :date_start, :points, :title, :description, :instructions, :reward, :icon, :metadata, :created_at, :updated_at)";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($data);
		return (int) $pdo->lastInsertId();
	}

	/**
	 * Aggiorna una sfida esistente tramite ID.
	 */
	public static function update(string $date, int $pharma_id, array $data): bool {
		global $pdo;

		unset($json['progress']);
		unset($json['is_completed']);
		unset($json['today_is_done']);

		unset($data['created_at']);
		$data['updated_at'] = date('Y-m-d H:i:s');
		$data['pharma_id'] = $pharma_id;

		$date = get_week_start_date($date);

		$set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
		$sql = "UPDATE jta_week_challenges SET $set WHERE date_start = :date AND pharma_id = :pharma_id";
		$stmt = $pdo->prepare($sql);
		return $stmt->execute($data);
	}

	/**
	 * Normalizza una riga del database per output JSON API.
	 */
	public static function normalize(array $challenge): array {
		return [
			'title' => $challenge['title'],
			'description' => $challenge['description'],
			'instructions' => json_decode($challenge['instructions'], true),
			'reward' => $challenge['reward'],
			'icon' => $challenge['icon'],
		];
	}

	/**
	 * Prepara i dati da JSON per l'inserimento.
	 */
	public static function prepareForInsert(array $json, ?string $date_start = null): array {
		unset($json['progress']);
		unset($json['is_completed']);
		unset($json['today_is_done']);

		if (!$date_start) {
			$date_start = get_week_start_date(date('Y-m-d'));
		}

		return [
			'date_start' => $date_start,
			'title' => $json['title'] ?? '',
			'description' => $json['description'] ?? '',
			'instructions' => json_encode($json['instructions'] ?? []),
			'reward' => $json['reward'] ?? '',
			'icon' => $json['icon'] ?? '',
		];
	}

	public static function insertFromAI( string $date, int $points, int $pharma_id ) {
		if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
		$date = get_week_start_date($date);
		if( ! is_valid_date($date) ) return FALSE;
		if( self::getByDate($date, $pharma_id) ) return FALSE;
		if( empty($points) ) $points = get_option('point--challenge_daily', 1);
		if( $points < 1 ) $points = get_option('point--challenge_daily', 1);

		$data = openai_generate_weekly_challenge($date);
		if( ! $data ) return FALSE;

		return self::insert([
			'pharma_id'    => $pharma_id,
			'date_start'   => $date,
			'points'       => $points,

			'title'        => $data['title'],
			'description'  => $data['description'],
			'instructions' => $data['instructions'],
			'reward'       => $data['reward'],
			'icon'         => $data['icon'],
			'metadata'     => $data,
		]);
	}
}

class ChallengeProgressModel {
	/**
	 * Aggiorna il giorno corrente a completato per un utente e una sfida.
	 */
	public static function updateProgress(int $user_id, int $challenge_id): bool {
		if ( ! self::exists($user_id, $challenge_id) ) {
			return self::generate($user_id, $challenge_id);
		}

		$day = (int) date('N');
		$column = 'd' . $day;
		return self::update(self::getId($user_id, $challenge_id), [
			$column => 1,
			'updated_at' => date('Y-m-d H:i:s')
		]);
	}

	/**
	 * Inserisce un nuovo record di progresso.
	 */
	public static function insert(array $data): bool {
		global $pdo;
		$columns = implode(', ', array_keys($data));
		$placeholders = ':' . implode(', :', array_keys($data));
		$sql = "INSERT INTO jta_week_challenge_progress ($columns) VALUES ($placeholders)";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($data);
		return (int) $pdo->lastInsertId();
	}

	/**
	 * Aggiorna un record esistente tramite ID.
	 */
	public static function update(int $id, array $data): bool {
		global $pdo;
		$set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
		$data['id'] = $id;
		$sql = "UPDATE jta_week_challenge_progress SET $set WHERE id = :id";
		$stmt = $pdo->prepare($sql);
		return $stmt->execute($data);
	}

	/**
	 * Verifica se esiste un progresso per utente e sfida.
	 */
	public static function exists(int $user_id, int $challenge_id): bool {
		return self::getId($user_id, $challenge_id) !== false;
	}

	/**
	 * Ottiene l'ID di un record progresso.
	 */
	public static function getId(int $user_id, int $challenge_id) {
		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_week_challenge_progress WHERE user_id = :user_id AND challenge_id = :challenge_id");
		$stmt->execute(['user_id' => $user_id, 'challenge_id' => $challenge_id]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? (int)$row['id'] : false;
	}

	/**
	 * Ottiene il record completo di progresso.
	 */
	public static function get(int $user_id, int $challenge_id) {
		if( ! self::exists($user_id, $challenge_id)) {
			self::generate($user_id, $challenge_id);
		}

		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_week_challenge_progress WHERE user_id = :user_id AND challenge_id = :challenge_id");
		$stmt->execute(['user_id' => $user_id, 'challenge_id' => $challenge_id]);

		return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
	}

	/**
	 * Restituisce i progressi settimanali in array da una riga DB.
	 */
	public static function normalizeProgress(array $progress_db): array {
		$progress_arr = array_map(function ($i) use ($progress_db) {
			return (int)($progress_db['d' . $i] ?? 0);
		}, range(1, 7));

		return [
			'is_completed'  => array_sum($progress_arr) == 7,
			'progress'      => $progress_arr,
			'today_is_done' => !! $progress_arr[ ((int) date('N') - 1) ],
		];
	}

	/**
	 * Conta i giorni completati di una sfida.
	 */
	public static function getCompletedDaysCount(int $user_id, int $challenge_id): int {
		$row = self::get($user_id, $challenge_id);
		if (!$row) return 0;
		$progress = self::normalizeProgress($row);
		return array_sum($progress['progress']);
	}

	/**
	 * Resetta tutti i progressi di una sfida per utente.
	 */
	public static function reset(int $user_id, int $challenge_id): bool {
		$data = ['updated_at' => date('Y-m-d H:i:s')];
		for ($i = 1; $i <= 7; $i++) {
			$data['d' . $i] = 0;
		}
		return self::update(self::getId($user_id, $challenge_id), $data);
	}

	/**
	 * Genera i progressi di una sfida per utente.
	 */
	public static function generate(int $user_id, int $challenge_id): bool {
		$data = ['user_id' => $user_id, 'challenge_id' => $challenge_id];
		for ($i = 1; $i <= 7; $i++) {
			$data['d' . $i] = 0;
		}
		$data['created_at'] = $data['updated_at'] = date('Y-m-d H:i:s');
		return self::insert($data);
	}
}


/**
 * Funzione standalone per normalizzare una riga di jta_week_challenges per l'output API.
 */
function normalize_challenge_data(array $row): array {
	return ChallengesModel::normalize($row);
}

/**
 * Restituisce la data del lunedì della settimana in cui cade la data specificata (YYYY-MM-DD).
 */
function get_week_start_date(string $date): string {
	$dt = new DateTime($date);
	$dt->setISODate((int)$dt->format('o'), (int)$dt->format('W'), 1);
	return $dt->format('Y-m-d');
}

/**
 * Restituisce un array con le date del Lunedi' e della Domenica di una settimana in cui cade la data specificata (YYYY-MM-DD).
 */
function get_week_range(string $date): array {
	$dt = new DateTime($date);
	
	// Calcola il lunedì della settimana
	$monday = clone $dt;
	$monday->setISODate((int)$dt->format('o'), (int)$dt->format('W'), 1);
	
	// Calcola la domenica della stessa settimana
	$sunday = clone $dt;
	$sunday->setISODate((int)$dt->format('o'), (int)$dt->format('W'), 7);
	
	return [
		$monday->format('Y-m-d'),
		$sunday->format('Y-m-d')
	];
}
