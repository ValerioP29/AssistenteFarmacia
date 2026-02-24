<?php require_once('../_api_bootstrap.php');

global $pdo;

try {
	// 1. Tabella log con pharma_id
	$pdo->exec("
		CREATE TABLE IF NOT EXISTS jta_user_points_log (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			user_id INT NOT NULL,
			pharma_id INT NOT NULL,
			date DATE NOT NULL,
			points INT NOT NULL,
			source VARCHAR(50) NOT NULL,
			deleted_at DATE NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX(user_id),
			INDEX(pharma_id),
			INDEX(date)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	");

	// 2. Riepilogo per mese e farmacia
	$pdo->exec("
		CREATE TABLE IF NOT EXISTS jta_user_points_summary (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			user_id INT NOT NULL,
			pharma_id INT NOT NULL,
			year INT NOT NULL,
			month INT NOT NULL,
			total_points INT NOT NULL DEFAULT 0,
			UNIQUE KEY unique_user_pharma_month (user_id, pharma_id, year, month),
			INDEX(user_id),
			INDEX(pharma_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	");

	// 3. Colonna su jta_users
	$columnExists = $pdo->prepare("
		SELECT COUNT(*) 
		FROM INFORMATION_SCHEMA.COLUMNS 
		WHERE TABLE_SCHEMA = DATABASE() 
		AND TABLE_NAME = 'jta_users' 
		AND COLUMN_NAME = 'points_current_month'
	");
	$columnExists->execute();

	if (!$columnExists->fetchColumn()) {
		$pdo->exec("
			ALTER TABLE jta_users
			ADD COLUMN points_current_month INT NOT NULL DEFAULT 0
			AFTER init_profiling;
		");
	}


	echo "✅ Tabelle e colonna aggiornate correttamente.";

} catch (PDOException $e) {
	echo "❌ Errore: " . $e->getMessage();
}
