-- Fase 2: tagging prodotti farmacia
-- Aggiunge campo tags (JSON nullable) a jta_pharma_prods

ALTER TABLE `jta_pharma_prods`
  ADD COLUMN `tags` JSON NULL AFTER `is_featured`;
