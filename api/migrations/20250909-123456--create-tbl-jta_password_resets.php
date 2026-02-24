<?php
require_once('../_api_bootstrap.php');
/**
 * Migrazione per creare la tabella per il reset password via OTP
 */

global $pdo;

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS jta_password_resets (
        `id` int AUTO_INCREMENT PRIMARY KEY,
        `user_id` int NOT NULL,
        `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
        `otp_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
        `expires_at` datetime NOT NULL,
        `attempts` tinyint UNSIGNED NOT NULL DEFAULT '0',
        `used` tinyint UNSIGNED NOT NULL DEFAULT '0',
        `sent_via` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'email',
        `created_at` datetime NOT NULL,
        INDEX(email),
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Tabella jta_password_resets creata con successo!\n";

} catch (PDOException $e) {
    echo "Errore nella creazione della tabella: " . $e->getMessage() . "\n";
}
