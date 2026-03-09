<?php
/**
 * panel/api/pharma-products/tags-bulk.php
 *
 * API Bulk update tags / is_featured per prodotti farmacia
 * Assistente Farmacia Panel
 *
 * FIX rispetto alla versione precedente:
 *   - normalizeProductTagsInput() ora chiama canonicalizeTag() internamente
 *     (via product_tags_engine.php aggiornato) → alias legacy risolti in scrittura
 *   - Nessuna modifica alla logica di autenticazione, loop items, o DB update
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/product_tags_engine.php';
// taxonomy/tags.php è già caricata transitivamente da product_tags_engine.php

checkAccess(['pharmacist']);
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payload JSON non valido']);
        exit;
    }

    $pharmaId = (int)($payload['pharma_id'] ?? ($payload['pharmacy_id'] ?? 0));
    $items    = $payload['items'] ?? null;

    if ($pharmaId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'pharma_id è obbligatorio']);
        exit;
    }

    if (!is_array($items) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'items deve essere un array non vuoto']);
        exit;
    }

    $pharmacy        = getCurrentPharmacy();
    $sessionPharmaId = (int)($pharmacy['id'] ?? 0);

    if ($sessionPharmaId <= 0 || $sessionPharmaId !== $pharmaId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato per la farmacia specificata']);
        exit;
    }

    $updated = 0;
    $skipped = 0;
    $errors  = [];

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            $skipped++;
            $errors[] = "Item #{$index} non valido";
            continue;
        }

        $productId  = (int)($item['id'] ?? 0);
        $productSku = isset($item['sku']) ? trim((string)$item['sku']) : '';

        if ($productId <= 0 && $productSku === '') {
            $skipped++;
            $errors[] = "Item #{$index}: id o sku prodotto mancante/non valido";
            continue;
        }

        if ($productId > 0) {
            $existing = db_fetch_one(
                "SELECT id, tags, is_featured FROM jta_pharma_prods WHERE id = ? AND pharma_id = ?",
                [$productId, $pharmaId]
            );
        } else {
            $existing = db_fetch_one(
                "SELECT id, tags, is_featured FROM jta_pharma_prods WHERE sku = ? AND pharma_id = ?",
                [$productSku, $pharmaId]
            );
            if ($existing) {
                $productId = (int)$existing['id'];
            }
        }

        if (!$existing) {
            $skipped++;
            $errors[] = "Item #{$index}: prodotto non trovato per la farmacia specificata";
            continue;
        }

        $fields = [];

        if (array_key_exists('tags', $item)) {
            /**
             * normalizeProductTagsInput() chiama canonicalizeTag() su ogni slug
             * (via product_tags_engine.php aggiornato), quindi alias come
             * "dermocosmetica" vengono scritti in DB come "dermocosmesi".
             */
            $normalizedTags = normalizeProductTagsInput($item['tags']);
            $fields['tags'] = $normalizedTags !== null
                ? json_encode($normalizedTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
        }

        if (array_key_exists('is_featured', $item)) {
            $fields['is_featured'] = parseBoolishValue($item['is_featured']);
        }

        if (empty($fields)) {
            $skipped++;
            $errors[] = "Item #{$index}: nessun campo aggiornabile (tags/is_featured)";
            continue;
        }

        $affected = db()->update('jta_pharma_prods', $fields, 'id = ? AND pharma_id = ?', [$productId, $pharmaId]);
        if ($affected > 0) {
            $updated++;
        } else {
            $skipped++;
            $errors[] = "Item #{$index}: nessuna modifica applicata";
        }
    }

    echo json_encode([
        'success'     => true,
        'pharma_id'   => $pharmaId,
        'updated'     => $updated,
        'skipped'     => $skipped,
        'total_items' => count($items),
        'errors'      => $errors,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage(),
    ]);
}