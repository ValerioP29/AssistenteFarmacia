<?php
/**
 * Gestione Approvazione Prodotti
 * Assistente Farmacia Panel
 */

/**
 * Attiva automaticamente tutti i prodotti farmacia collegati a un prodotto globale
 * quando l'admin approva il prodotto globale
 */
function activatePharmaProductsForGlobalProduct($globalProductId) {
    try {
        // Trova tutti i prodotti farmacia collegati al prodotto globale
        $pharmaProducts = db_fetch_all(
            "SELECT id, pharma_id, name FROM jta_pharma_prods WHERE product_id = ? AND is_active = 0",
            [$globalProductId]
        );
        
        if (empty($pharmaProducts)) {
            return true; // Nessun prodotto da attivare
        }
        
        $activatedCount = 0;
        
        foreach ($pharmaProducts as $pharmaProduct) {
            // Attiva il prodotto farmacia
            $affected = db()->update(
                'jta_pharma_prods',
                ['is_active' => 1],
                'id = ?',
                [$pharmaProduct['id']]
            );
            
            if ($affected > 0) {
                $activatedCount++;
                
                // Log attività
                logActivity('pharma_product_auto_activated', [
                    'pharma_product_id' => $pharmaProduct['id'],
                    'global_product_id' => $globalProductId,
                    'pharma_id' => $pharmaProduct['pharma_id'],
                    'name' => $pharmaProduct['name']
                ]);
            }
        }
        
        // Log attività generale
        logActivity('global_product_approved_auto_activation', [
            'global_product_id' => $globalProductId,
            'activated_pharma_products' => $activatedCount,
            'total_pharma_products' => count($pharmaProducts)
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Errore nell'attivazione automatica prodotti farmacia: " . $e->getMessage());
        return false;
    }
}

/**
 * Disattiva automaticamente tutti i prodotti farmacia collegati a un prodotto globale
 * quando l'admin disattiva il prodotto globale
 */
function deactivatePharmaProductsForGlobalProduct($globalProductId) {
    try {
        // Trova tutti i prodotti farmacia collegati al prodotto globale
        $pharmaProducts = db_fetch_all(
            "SELECT id, pharma_id, name FROM jta_pharma_prods WHERE product_id = ? AND is_active = 1",
            [$globalProductId]
        );
        
        if (empty($pharmaProducts)) {
            return true; // Nessun prodotto da disattivare
        }
        
        $deactivatedCount = 0;
        
        foreach ($pharmaProducts as $pharmaProduct) {
            // Disattiva il prodotto farmacia
            $affected = db()->update(
                'jta_pharma_prods',
                ['is_active' => 0],
                'id = ?',
                [$pharmaProduct['id']]
            );
            
            if ($affected > 0) {
                $deactivatedCount++;
                
                // Log attività
                logActivity('pharma_product_auto_deactivated', [
                    'pharma_product_id' => $pharmaProduct['id'],
                    'global_product_id' => $globalProductId,
                    'pharma_id' => $pharmaProduct['pharma_id'],
                    'name' => $pharmaProduct['name']
                ]);
            }
        }
        
        // Log attività generale
        logActivity('global_product_deactivated_auto_deactivation', [
            'global_product_id' => $globalProductId,
            'deactivated_pharma_products' => $deactivatedCount,
            'total_pharma_products' => count($pharmaProducts)
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Errore nella disattivazione automatica prodotti farmacia: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottieni statistiche sui prodotti in attesa di approvazione
 */
function getPendingApprovalStats() {
    try {
        // Prodotti globali in attesa di approvazione
        $pendingGlobalProducts = db_fetch_one(
            "SELECT COUNT(*) as count FROM jta_global_prods WHERE is_active = 'pending_approval'"
        );
        
        // Prodotti farmacia inattivi (collegati a prodotti globali approvati)
        $pendingPharmaProducts = db_fetch_one(
            "SELECT COUNT(*) as count FROM jta_pharma_prods pp 
             INNER JOIN jta_global_prods gp ON pp.product_id = gp.id 
             WHERE pp.is_active = 0 AND gp.is_active = 'active'"
        );
        
        return [
            'pending_global_products' => intval($pendingGlobalProducts['count']),
            'pending_pharma_products' => intval($pendingPharmaProducts['count'])
        ];
        
    } catch (Exception $e) {
        error_log("Errore nel recupero statistiche approvazione: " . $e->getMessage());
        return [
            'pending_global_products' => 0,
            'pending_pharma_products' => 0
        ];
    }
}
?> 