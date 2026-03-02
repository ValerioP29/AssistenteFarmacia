-- ================================================================
-- migration_user_vouchers.sql
-- Crea la tabella voucher e aggiunge colonne di supporto
-- Eseguire UNA volta sul DB di produzione
-- ================================================================

-- ── Tabella voucher utente ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_vouchers` (
    `id`                   INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    `user_id`              INT UNSIGNED    NOT NULL,
    `pharmacy_id`          INT UNSIGNED    NOT NULL DEFAULT 1,
    `code`                 VARCHAR(32)     NOT NULL,
    `status`               TINYINT         NOT NULL DEFAULT 0
                           COMMENT '0=attivo, 1=usato, 2=scaduto',
    `points_cost`          SMALLINT        NOT NULL DEFAULT 100,
    `points_at_generation` INT             NOT NULL DEFAULT 0
                           COMMENT 'Totale punti utente al momento della generazione',
    `value_eur`            DECIMAL(6,2)    NOT NULL DEFAULT 10.00,
    `date_created`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_start`           DATETIME        NULL,
    `date_end`             DATETIME        NULL,
    `redeemed_at`          DATETIME        NULL,
    `notes`                VARCHAR(255)    NULL,

    UNIQUE KEY `uq_voucher_code` (`code`),
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_pharmacy`   (`pharmacy_id`),
    INDEX `idx_status`     (`status`),
    INDEX `idx_date_end`   (`date_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Se la colonna points_current_month non esiste ancora ─────────
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `points_current_month` INT NOT NULL DEFAULT 0
    COMMENT 'Punti accumulati nel ciclo corrente (scalati al riscatto)',
    ADD INDEX IF NOT EXISTS `idx_points` (`points_current_month`);

-- ── Vista utile per admin: voucher con nome utente ───────────────
CREATE OR REPLACE VIEW `v_vouchers_detail` AS
SELECT
    v.id,
    v.code,
    v.status,
    v.value_eur,
    v.points_cost,
    v.date_created,
    v.date_start,
    v.date_end,
    v.redeemed_at,
    u.id          AS user_id,
    u.email       AS user_email,
    CONCAT(u.first_name, ' ', u.last_name) AS user_name,
    v.pharmacy_id
FROM user_vouchers v
JOIN users u ON u.id = v.user_id;