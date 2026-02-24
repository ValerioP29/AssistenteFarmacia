-- =====================================================
-- AGGIORNAMENTO DATABASE PER SISTEMA CRON
-- =====================================================

-- Tabella per la gestione dei cron jobs
CREATE TABLE IF NOT EXISTS `jta_cron` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `file_name` varchar(255) NOT NULL COMMENT 'Nome del file cron',
    `description` text COMMENT 'Descrizione del cron',
    `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=attivo, 0=disattivo',
    `last_start` datetime NULL DEFAULT NULL COMMENT 'Data/ora ultimo avvio',
    `last_end` datetime NULL DEFAULT NULL COMMENT 'Data/ora ultima fine',
    `status` tinyint NOT NULL DEFAULT '0' COMMENT '-1=errore, 0=terminato, 1=in esecuzione',
    `last_error` text COMMENT 'Ultimo errore generato',
    PRIMARY KEY (`id`),
    UNIQUE KEY `file_name` (`file_name`),
    KEY `is_active` (`is_active`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiunta campo last_notification alla tabella jta_users
ALTER TABLE `jta_users` 
ADD COLUMN IF NOT EXISTS `last_notification` datetime NULL DEFAULT NULL COMMENT 'Data/ora ultima notifica inviata';

-- Aggiungi indice per ottimizzare le query
ALTER TABLE `jta_users` 
ADD KEY IF NOT EXISTS `idx_last_notification` (`last_notification`);

-- Inserimento record iniziali nella tabella jta_cron
INSERT IGNORE INTO `jta_cron` (`file_name`, `description`, `is_active`) VALUES
('reminder_therapy.php', 'Cron per promemoria terapie attive', 1),
('reminder_expiry.php', 'Cron per promemoria scadenze prodotti', 1); 