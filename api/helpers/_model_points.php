<?php

class PointsModel {
	private static function baseSelect(): string {
		return "SELECT * FROM jta_user_points_log WHERE deleted_at IS NULL";
	}

	public static function insert(array $data): bool {
		global $pdo;
		$data['created_at'] = date('Y-m-d H:i:s');

		$sql = "INSERT INTO jta_user_points_log (user_id, pharma_id, date, points, source, created_at) 
				VALUES (:user_id, :pharma_id, :date, :points, :source, :created_at)";
		$stmt = $pdo->prepare($sql);
		return $stmt->execute($data);
	}

	public static function update(int $id, array $data): bool {
		global $pdo;
		$fields = [];
		foreach ($data as $key => $value) {
			$fields[] = "$key = :$key";
		}
		$sql = "UPDATE jta_user_points_log SET " . implode(", ", $fields) . " WHERE id = :id";
		$data['id'] = $id;
		$stmt = $pdo->prepare($sql);
		return $stmt->execute($data);
	}

	public static function delete(int $id): bool {
		return self::update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
	}

	public static function getAll(): array {
		global $pdo;
		$stmt = $pdo->query(self::baseSelect());
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function getById(int $id) {
		global $pdo;
		$sql = self::baseSelect() . " AND id = :id";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['id' => $id]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
	}
}

class UserPointsModel {
	public static function getActionRules(): array {
		return [
			'request_service' => [
				'title' => 'Richiesta servizio',
				'desc' => 'Invio richiesta per un servizio selezionato tra quelli disponibili.',
				'option' => 'points_request_service',
				'default_points' => 10,
			],
			'request_service_free' => [
				'title' => 'Richiesta servizio libero',
				'desc' => 'Invio richiesta servizio da input libero.',
				'option' => 'points_request_service_free',
				'default_points' => 10,
			],
			'request_event' => [
				'title' => 'Eventi',
				'desc' => 'Invio richiesta/prenotazione relativa a un evento.',
				'option' => 'points_request_event',
				'default_points' => 10,
			],
			'reservation_cart' => [
				'title' => 'Prenotazione prodotto da carrello',
				'desc' => 'Prenotazione prodotti inviata dal carrello.',
				'option' => 'points_reservation_cart',
				'default_points' => 10,
			],
			'reservation_page' => [
				'title' => 'Prenotazione prodotto da pagina prenotazioni',
				'desc' => 'Prenotazione prodotti inviata dalla pagina prenotazioni.',
				'option' => 'points_reservation_page',
				'default_points' => 10,
			],
			'quiz_daily' => [
				'title' => 'Quiz del giorno',
				'desc' => 'Completamento quiz del giorno (massimo una volta al giorno). Punteggio variabile in base al quiz pubblicato.',
				'option' => 'point--quiz_daily',
				'default_points' => 3,
				'is_variable' => true,
			],
			'login_daily' => [
				'title' => 'Accesso quotidiano all\'app',
				'desc' => 'Primo accesso giornaliero in app (massimo una volta al giorno).',
				'option' => 'points_login_daily',
				'default_points' => 1,
			],
		];
	}

	public static function getPointsForAction(string $actionId): int {
		$rules = self::getActionRules();
		$rule = $rules[$actionId] ?? null;
		if (!$rule) return 0;
		return (int) get_option($rule['option'], $rule['default_points']);
	}

	public static function getLegendForActions(array $actionIds): array {
		$rules = self::getActionRules();
		$legend = [];

		foreach ($actionIds as $actionId) {
			if (!isset($rules[$actionId])) continue;
			$rule = $rules[$actionId];
			$isVariable = !empty($rule['is_variable']);
			$legend[] = [
				'id' => $actionId,
				'title' => $rule['title'],
				'desc' => $rule['desc'],
				'value' => $isVariable ? null : self::getPointsForAction($actionId),
				'value_label' => $isVariable ? 'Variabile' : null,
				'hidden' => false,
			];
		}

		return $legend;
	}

	private static function baseSelect(): string {
		return "SELECT * FROM jta_user_points_log WHERE deleted_at IS NULL";
	}

	public static function getByDay(int $userId, string $day): array {
		global $pdo;
		$sql = self::baseSelect() . " AND user_id = :user_id AND date = :day";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['user_id' => $userId, 'day' => $day]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function getByMonth(int $userId, string $month): array {
		global $pdo;
		$sql = self::baseSelect() . " AND user_id = :user_id AND DATE_FORMAT(date, '%Y-%m') = :month";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['user_id' => $userId, 'month' => $month]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function getByYear(int $userId, string $year): array {
		global $pdo;
		$sql = self::baseSelect() . " AND user_id = :user_id AND YEAR(date) = :year";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['user_id' => $userId, 'year' => $year]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Somma dei punti per un utente in un giorno specifico (yyyy-mm-dd).
	 *
	 * @param int $userId
	 * @param string $day
	 * @return int Totale punti (0 se nessun record)
	 */
	public static function getSumByDay(int $userId, string $day): int {
		global $pdo;
		$sql = "SELECT COALESCE(SUM(points), 0) FROM jta_user_points_log 
				WHERE deleted_at IS NULL AND user_id = :user_id AND date = :day";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['user_id' => $userId, 'day' => $day]);
		return (int)$stmt->fetchColumn();
	}

	/**
	 * Somma dei punti per un utente in un mese specifico (yyyy-mm).
	 *
	 * @param int $userId
	 * @param string $month
	 * @return int Totale punti (0 se nessun record)
	 */
	public static function getSumByMonth(int $userId, string $month): int {
		global $pdo;
		$sql = "SELECT COALESCE(SUM(points), 0) FROM jta_user_points_log 
				WHERE deleted_at IS NULL AND user_id = :user_id AND DATE_FORMAT(date, '%Y-%m') = :month";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['user_id' => $userId, 'month' => $month]);
		return (int)$stmt->fetchColumn();
	}

	/**
	 * Somma dei punti per un utente in un anno specifico (yyyy).
	 *
	 * @param int $userId
	 * @param string $year
	 * @return int Totale punti (0 se nessun record)
	 */
	public static function getSumByYear(int $userId, string $year): int {
		global $pdo;
		$sql = "SELECT COALESCE(SUM(points), 0) FROM jta_user_points_log 
				WHERE deleted_at IS NULL AND user_id = :user_id AND YEAR(date) = :year";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['user_id' => $userId, 'year' => $year]);
		return (int)$stmt->fetchColumn();
	}

	/**
	 * Aggiunge punti (usando PointsModel::insert()) e aggiorna riepilogo.
	 */
	public static function addPoints(int $userId, int $pharmaId, int $pointsVal, string $source, ?string $date = null): bool {
		if ($pointsVal <= 0) return false;
		$date = $date ?: date('Y-m-d');

		$success = PointsModel::insert([
			'user_id' => $userId,
			'pharma_id' => $pharmaId,
			'date' => $date,
			'points' => $pointsVal,
			'source' => $source
		]);

		if ($success) {
			PointsSummaryModel::updateCurrentMonthPoints($userId, $pharmaId);
			PointsSummaryModel::regenerateByUser($userId);
		}

		return $success;
	}

	/**
	 * Verifica se esiste un log per un utente, farmacia e motivo in una data specifica.
	 * Se la data non è fornita, si assume la data odierna.
	 *
	 * @param int $userId ID utente
	 * @param int $pharmaId ID farmacia
	 * @param string $source Motivo dell’assegnazione punti
	 * @param string|null $date Data (formato yyyy-mm-dd), opzionale
	 * @return bool True se esiste almeno un record, false altrimenti
	 */
	public static function hasEntryForDate(int $userId, int $pharmaId, string $source, ?string $date = null): bool {
		global $pdo;
		$date = $date ?: date('Y-m-d');

		$sql = "SELECT id FROM jta_user_points_log
				WHERE deleted_at IS NULL AND user_id = :user_id AND pharma_id = :pharma_id AND source = :source AND date = :date
				LIMIT 1";

		$stmt = $pdo->prepare($sql);
		$stmt->execute([
			'user_id'   => $userId,
			'pharma_id' => $pharmaId,
			'source'    => $source,
			'date'      => $date
		]);

		return (bool) $stmt->fetchColumn();
	}

	/**
	 * Verifica se esiste un log per un utente, farmacia e motivo in una settimana specifica.
	 * Se la data non è fornita, si assume la settimana odierna.
	 *
	 * @param int $userId ID utente
	 * @param int $pharmaId ID farmacia
	 * @param string $source Motivo dell’assegnazione punti
	 * @param string|null $date Data all'interno della settimana (formato yyyy-mm-dd), opzionale
	 * @return bool True se esiste almeno un record, false altrimenti
	 */
	public static function hasEntryForWeek(int $userId, int $pharmaId, string $source, ?string $date = null): bool {
		global $pdo;
		$date = $date ?: date('Y-m-d');

		// Calcola l'inizio (lunedì) e la fine (domenica) della settimana
		$startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
		$endOfWeek   = date('Y-m-d', strtotime('sunday this week', strtotime($date)));

		$sql = "SELECT id FROM jta_user_points_log
				WHERE deleted_at IS NULL
				AND user_id = :user_id
				AND pharma_id = :pharma_id
				AND source = :source
				AND date BETWEEN :start_date AND :end_date
				LIMIT 1";

		$stmt = $pdo->prepare($sql);
		$stmt->execute([
			'user_id'    => $userId,
			'pharma_id'  => $pharmaId,
			'source'     => $source,
			'start_date' => $startOfWeek,
			'end_date'   => $endOfWeek
		]);

		return (bool) $stmt->fetchColumn();
	}

	private static function executeInsertIfNotExists(array $insertData, string $whereClause, array $whereParams): bool {
		global $pdo;

		if (($insertData['points'] ?? 0) <= 0) {
			return false;
		}

		$sql = "INSERT INTO jta_user_points_log (user_id, pharma_id, date, points, source, created_at)
				SELECT :user_id, :pharma_id, :date, :points, :source, :created_at
				WHERE NOT EXISTS (
					SELECT 1 FROM jta_user_points_log
					WHERE deleted_at IS NULL {$whereClause}
				)";

		$params = array_merge($insertData, $whereParams);
		$stmt = $pdo->prepare($sql);
		$success = $stmt->execute($params);

		if ($success && $stmt->rowCount() > 0) {
			PointsSummaryModel::updateCurrentMonthPoints((int)$insertData['user_id'], (int)$insertData['pharma_id']);
			PointsSummaryModel::regenerateByUser((int)$insertData['user_id']);
			return true;
		}

		return false;
	}

	public static function addPointsOnceBySource(int $userId, int $pharmaId, int $pointsVal, string $source, ?string $date = null): bool {
		$date = $date ?: date('Y-m-d');
		$insertData = [
			'user_id' => $userId,
			'pharma_id' => $pharmaId,
			'date' => $date,
			'points' => $pointsVal,
			'source' => $source,
			'created_at' => date('Y-m-d H:i:s'),
		];

		return self::executeInsertIfNotExists(
			$insertData,
			' AND user_id = :check_user_id AND pharma_id = :check_pharma_id AND source = :check_source',
			[
				'check_user_id' => $userId,
				'check_pharma_id' => $pharmaId,
				'check_source' => $source,
			]
		);
	}

	public static function addPointsOncePerDay(int $userId, int $pharmaId, int $pointsVal, string $source, ?string $date = null): bool {
		$date = $date ?: date('Y-m-d');
		$insertData = [
			'user_id' => $userId,
			'pharma_id' => $pharmaId,
			'date' => $date,
			'points' => $pointsVal,
			'source' => $source,
			'created_at' => date('Y-m-d H:i:s'),
		];

		return self::executeInsertIfNotExists(
			$insertData,
			' AND user_id = :check_user_id AND pharma_id = :check_pharma_id AND source = :check_source AND date = :check_date',
			[
				'check_user_id' => $userId,
				'check_pharma_id' => $pharmaId,
				'check_source' => $source,
				'check_date' => $date,
			]
		);
	}

	public static function addPointsOnceByActionReference(int $userId, int $pharmaId, int $pointsVal, string $actionId, string $referenceKey, ?string $date = null): bool {
		$cleanRef = trim($referenceKey);
		if ($cleanRef === '') {
			return false;
		}

		$source = $actionId . ':' . $cleanRef;
		return self::addPointsOnceBySource($userId, $pharmaId, $pointsVal, $source, $date);
	}

}

class PointsSummaryModel {
	private static function baseSelect(): string {
		return "SELECT * FROM jta_user_points_summary";
	}

	public static function regenerateAll(): void {
		global $pdo;
		$sql = "INSERT INTO jta_user_points_summary (user_id, pharma_id, year, month, total_points)
				SELECT user_id, pharma_id, YEAR(date), MONTH(date), SUM(points)
				FROM jta_user_points_log
				WHERE deleted_at IS NULL
				GROUP BY user_id, pharma_id, YEAR(date), MONTH(date)
				ON DUPLICATE KEY UPDATE total_points = VALUES(total_points)";
		$pdo->exec($sql);
	}

	public static function regenerateByUser(int $userId): void {
		global $pdo;
		$sql = "INSERT INTO jta_user_points_summary (user_id, pharma_id, year, month, total_points)
				SELECT user_id, pharma_id, YEAR(date), MONTH(date), SUM(points)
				FROM jta_user_points_log
				WHERE deleted_at IS NULL AND user_id = :user_id
				GROUP BY pharma_id, YEAR(date), MONTH(date)
				ON DUPLICATE KEY UPDATE total_points = VALUES(total_points)";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['user_id' => $userId]);
	}

	public static function regenerateByPharma(int $pharmaId): void {
		global $pdo;
		$sql = "INSERT INTO jta_user_points_summary (user_id, pharma_id, year, month, total_points)
				SELECT user_id, pharma_id, YEAR(date), MONTH(date), SUM(points)
				FROM jta_user_points_log
				WHERE deleted_at IS NULL AND pharma_id = :pharma_id
				GROUP BY user_id, YEAR(date), MONTH(date)
				ON DUPLICATE KEY UPDATE total_points = VALUES(total_points)";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['pharma_id' => $pharmaId]);
	}

	public static function updateCurrentMonthPoints(int $userId, int $pharmaId): bool {
		global $pdo;
		$sql = "SELECT SUM(points) FROM jta_user_points_log 
				WHERE user_id = :user_id AND pharma_id = :pharma_id AND deleted_at IS NULL AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(['user_id' => $userId, 'pharma_id' => $pharmaId]);
		$total = $stmt->fetchColumn();

		$update = $pdo->prepare("UPDATE jta_users SET points_current_month = :points WHERE id = :id");
		return $update->execute(['points' => (int)$total, 'id' => $userId]);
	}

	public static function getByUserPharmaDate(int $userId, int $pharmaId, string $date) {
		global $pdo;
		[$year, $month] = explode('-', substr($date, 0, 7));
		$sql = self::baseSelect() . " WHERE user_id = :user_id AND pharma_id = :pharma_id AND year = :year AND month = :month";
		$stmt = $pdo->prepare($sql);
		$stmt->execute([
			'user_id' => $userId,
			'pharma_id' => $pharmaId,
			'year' => (int)$year,
			'month' => (int)$month,
		]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
	}
}
