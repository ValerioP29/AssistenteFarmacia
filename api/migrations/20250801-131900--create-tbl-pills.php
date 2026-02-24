<?php require_once('../_api_bootstrap.php');

global $pdo;

$sql = "
CREATE TABLE IF NOT EXISTS jta_daily_pills (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	day DATE NOT NULL,
	category VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	title VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	excerpt VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	content LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	metadata JSON,
	deleted_at DATETIME DEFAULT NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
	$pdo->exec($sql);
	echo "Tabella jta_daily_pills creata (o già esistente).";
} catch (PDOException $e) {
	echo "Errore nella creazione della tabella: " . $e->getMessage();
}
