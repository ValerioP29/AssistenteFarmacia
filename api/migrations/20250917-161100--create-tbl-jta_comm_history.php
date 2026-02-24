<?php
require_once('../_api_bootstrap.php');
/**
 * Migrazione per creare la tabella
 */

global $pdo;

try {
	$check = $pdo->query("SHOW TABLES LIKE 'jta_comm_history'");
	if ($check->rowCount() > 0) {
		echo "La tabella jta_comm_history esiste già!\n";
	} else {
		$sql = "
			CREATE TABLE `jta_comm_history` (
				`id` int NOT NULL AUTO_INCREMENT,
				`type` enum('wa','email','sms','') COLLATE utf8mb4_unicode_ci NOT NULL,
				`pharma_id` int NOT NULL,
				`pharma_info` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
				`user_id` int NOT NULL,
				`user_info` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
				`body` text COLLATE utf8mb4_unicode_ci NOT NULL,
				`schedule_date` datetime NULL,
				`sent_date` datetime NULL,
				`response` text COLLATE utf8mb4_unicode_ci,
				`status` tinyint NOT NULL COMMENT '-1: ignore;\r\n0: in pending;\r\n1: in working;\r\n2: completed;',
				`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `pharma_id` (`pharma_id`),
				KEY `user_id` (`user_id`),
				KEY `status` (`status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			ALTER TABLE `jta_comm_history` CHANGE `status` `status` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '-1: ignore;\r\n0: in pending;\r\n1: in working;\r\n2: completed;';
			ALTER TABLE `jta_comm_history` ADD `group_id` VARCHAR(12) NULL AFTER `id`, ADD INDEX (`group_id`);
		";
		$pdo->exec($sql);
		echo "Tabella jta_comm_history creata con successo!\n";
	}
} catch (PDOException $e) {
	echo "Errore: " . $e->getMessage() . "\n";
}
