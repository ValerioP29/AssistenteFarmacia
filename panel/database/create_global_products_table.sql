-- Script per creazione tabella prodotti globali
-- Assistente Farmacia Panel

USE `jt_assistente_farmacia`;

-- Tabella prodotti globali
CREATE TABLE IF NOT EXISTS `jta_global_prods` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sku` varchar(50) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `image` varchar(255) DEFAULT NULL,
    `category` varchar(100) DEFAULT NULL,
    `brand` varchar(100) DEFAULT NULL,
    `active_ingredient` varchar(255) DEFAULT NULL,
    `dosage_form` varchar(50) DEFAULT NULL,
    `strength` varchar(50) DEFAULT NULL,
    `package_size` varchar(50) DEFAULT NULL,
    `requires_prescription` tinyint(1) NOT NULL DEFAULT 0,
    `is_active` ENUM('active', 'inactive', 'pending_approval', 'deleted') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sku` (`sku`),
    KEY `name` (`name`),
    KEY `category` (`category`),
    KEY `brand` (`brand`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserimento di alcuni prodotti di esempio
INSERT INTO `jta_global_prods` (`sku`, `name`, `description`, `category`, `brand`, `active_ingredient`, `dosage_form`, `strength`, `package_size`, `requires_prescription`, `is_active`) VALUES
('PAR001', 'Paracetamolo', 'Antidolorifico e antipiretico', 'Antidolorifici', 'Generico', 'Paracetamolo', 'Compresse', '500mg', '20 compresse', 0, 'active'),
('IBU001', 'Ibuprofene', 'Antinfiammatorio non steroideo', 'Antinfiammatori', 'Generico', 'Ibuprofene', 'Compresse', '400mg', '20 compresse', 0, 'active'),
('ASP001', 'Aspirina', 'Antidolorifico e antipiretico', 'Antidolorifici', 'Bayer', 'Acido acetilsalicilico', 'Compresse', '100mg', '30 compresse', 0, 'active'),
('VIT001', 'Vitamina C', 'Integratore vitaminico', 'Integratori', 'Generico', 'Acido ascorbico', 'Compresse', '1000mg', '30 compresse', 0, 'active'),
('OMG001', 'Omega 3', 'Integratore di acidi grassi', 'Integratori', 'Generico', 'Acidi grassi omega-3', 'Capsule', '1000mg', '60 capsule', 0, 'active'); 