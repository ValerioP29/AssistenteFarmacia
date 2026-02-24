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
