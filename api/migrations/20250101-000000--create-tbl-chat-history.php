<?php require_once('../_api_bootstrap.php');
/**
 * Migrazione per creare la tabella dello storico delle conversazioni del chatbot
 */

global $pdo;

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS jta_chat_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pharma_id INT NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        role ENUM('user', 'assistant', 'system') NOT NULL,
        content TEXT NOT NULL,
        content_type ENUM('text', 'image', 'mixed') DEFAULT 'text',
        image_data LONGTEXT NULL,
        tokens_used INT DEFAULT 0,
        model_used VARCHAR(50) DEFAULT 'gpt-4o',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_session (user_id, session_id),
        INDEX idx_created_at (created_at),
        INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Tabella jta_chat_history creata con successo!\n";

} catch (PDOException $e) {
    echo "Errore nella creazione della tabella: " . $e->getMessage() . "\n";
} 