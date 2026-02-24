<?php
require_once('../_api_bootstrap.php');

/**
 * Migrazione per creare la tabella jta_surveys
 */

global $pdo;

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS jta_surveys (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pharma_id INT(11) UNSIGNED NOT NULL,
        user_id INT(11) UNSIGNED NOT NULL,
        survey_id INT(11) UNSIGNED NOT NULL,
        profile CHAR(1) COLLATE utf8mb4_unicode_ci NOT NULL,
        counts JSON NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pharma (pharma_id),
        INDEX idx_user (user_id),
        INDEX idx_survey (survey_id),
        INDEX idx_profile (profile)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Tabella jta_surveys creata con successo!\n";

} catch (PDOException $e) {
    echo "Errore nella creazione della tabella jta_surveys: " . $e->getMessage() . "\n";
}
