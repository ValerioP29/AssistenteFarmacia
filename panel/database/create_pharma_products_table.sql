-- Creazione tabella prodotti farmacia
CREATE TABLE IF NOT EXISTS `jta_pharma_prods` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `pharma_id` int(10) unsigned NOT NULL,
    `product_id` int(11) DEFAULT NULL,
    `price` decimal(10,2) NOT NULL DEFAULT 0.00,
    `sale_price` decimal(10,2) DEFAULT NULL,
    `num_items` int(11) NOT NULL DEFAULT 0,
    `sku` varchar(100) DEFAULT NULL,
    `description` text,
    `name` varchar(255) NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `is_on_sale` tinyint(1) NOT NULL DEFAULT 0,
    `sale_start_date` datetime DEFAULT NULL,
    `sale_end_date` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `pharma_id` (`pharma_id`),
    KEY `product_id` (`product_id`),
    KEY `is_active` (`is_active`),
    KEY `is_on_sale` (`is_on_sale`),
    CONSTRAINT `fk_pharma_prods_pharma` FOREIGN KEY (`pharma_id`) REFERENCES `jta_pharmas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pharma_prods_product` FOREIGN KEY (`product_id`) REFERENCES `jta_global_prods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiornamento tabella prodotti globali per supportare stato "Da Approvare"
ALTER TABLE `jta_global_prods` 
MODIFY COLUMN `is_active` enum('active','inactive','pending_approval') NOT NULL DEFAULT 'active' 
COMMENT 'active=Attivo, inactive=Inattivo, pending_approval=Da Approvare';

-- Inserimento prodotti di esempio per farmacia (assumendo pharma_id = 1)
INSERT INTO `jta_pharma_prods` (`pharma_id`, `product_id`, `price`, `sale_price`, `num_items`, `sku`, `description`, `name`) VALUES
(1, 1, 8.50, 7.20, 25, 'PAR001-FARM1', 'Paracetamolo 500mg - Disponibile in farmacia', 'Paracetamolo 500mg'),
(1, 2, 12.30, NULL, 15, 'IBU001-FARM1', 'Ibuprofene 400mg - Antinfiammatorio', 'Ibuprofene 400mg'),
(1, 3, 6.80, 5.50, 30, 'ASP001-FARM1', 'Aspirina 100mg - Antidolorifico', 'Aspirina 100mg'),
(1, 4, 18.90, 15.20, 10, 'VIT001-FARM1', 'Vitamina C 1000mg - Integratore', 'Vitamina C 1000mg'),
(1, 5, 22.50, NULL, 8, 'OMG001-FARM1', 'Omega 3 - Integratore alimentare', 'Omega 3'); 