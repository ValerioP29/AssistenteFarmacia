<?php
require_once('../_api_bootstrap.php');
/**
 * Crea la colonna pharma_id su alcune tabelle (per il multi-farmacia)
 */

global $pdo;

try {
	$sql = "ALTER TABLE `jta_quizzes` DROP INDEX `date`;ALTER TABLE `jta_quizzes` ADD INDEX(`date`);";
	$pdo->exec($sql);
	$sql = "ALTER TABLE `jta_week_challenges` DROP INDEX `date_start`;ALTER TABLE `jta_week_challenges` ADD INDEX(`date_start`);";
	$pdo->exec($sql);
	echo "Modifiche applicate!\n";
} catch (PDOException $e) {
	echo "Errore: " . $e->getMessage() . "\n";
}
