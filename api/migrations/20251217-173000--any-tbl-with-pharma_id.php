<?php
require_once('../_api_bootstrap.php');
/**
 * Crea la colonna pharma_id su alcune tabelle (per il multi-farmacia)
 */

global $pdo;

try {
	$sql = "ALTER TABLE `jta_week_challenges` ADD `pharma_id` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `id`, ADD INDEX (`pharma_id`);ALTER TABLE `jta_week_challenges` CHANGE `pharma_id` `pharma_id` INT UNSIGNED NOT NULL;";
	$pdo->exec($sql);
	$sql = "ALTER TABLE `jta_quizzes` ADD `pharma_id` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `id`, ADD INDEX (`pharma_id`);ALTER TABLE `jta_quizzes` CHANGE `pharma_id` `pharma_id` INT UNSIGNED NOT NULL;";
	$pdo->exec($sql);
	$sql = "ALTER TABLE `jta_daily_pills` ADD `pharma_id` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `id`, ADD INDEX (`pharma_id`);ALTER TABLE `jta_daily_pills` CHANGE `pharma_id` `pharma_id` INT UNSIGNED NOT NULL;";
	$pdo->exec($sql);
	echo "Modifiche applicate!\n";
} catch (PDOException $e) {
	echo "Errore: " . $e->getMessage() . "\n";
}
