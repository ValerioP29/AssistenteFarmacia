<?php
/**
 * API Suggerimento Tags Prodotti Farmacia (rule-based)
 * Assistente Farmacia Panel
 */

require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/product_tags_engine.php';

checkAccess(['pharmacist']);
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
        exit;
    }

    $pharmacy = getCurrentPharmacy();
    $sessionPharmaId = (int)($pharmacy['id'] ?? 0);

    $pharmaId = (int)($_GET['pharma_id'] ?? ($_GET['pharmacy_id'] ?? 0));
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
    $search = trim($_GET['search'] ?? '');

    if ($pharmaId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'pharma_id è obbligatorio']);
        exit;
    }

    if ($sessionPharmaId <= 0 || $sessionPharmaId !== $pharmaId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato per la farmacia specificata']);
        exit;
    }

    $sql = "SELECT id, sku, name, image, tags, is_featured FROM jta_pharma_prods WHERE pharma_id = ?";
    $params = [$pharmaId];

    if ($search !== '') {
        $sql .= " AND (name LIKE ? OR sku LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= " ORDER BY name ASC LIMIT ?";
    $params[] = $limit;

    $products = db_fetch_all($sql, $params);

    $data = array_map(function ($product) {
        $suggestion = suggestTagsFromName((string)($product['name'] ?? ''));

        return [
            'id' => (int)$product['id'],
            'sku' => $product['sku'] !== null ? (string)$product['sku'] : null,
            'name' => (string)$product['name'],
            'image' => $product['image'] ?: null,
            'current_tags' => normalizeTagArray($product['tags'] ?? null),
            'suggested_tags' => $suggestion['suggested_tags'],
            'confidence' => $suggestion['confidence'],
            'matched_keywords' => $suggestion['matched_keywords'],
            'is_featured' => (int)($product['is_featured'] ?? 0),
        ];
    }, $products);

    echo json_encode([
        'success' => true,
        'count' => count($data),
        'data' => $data,
        'meta' => [
            'pharma_id' => $pharmaId,
            'limit' => $limit,
            'search' => $search,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore interno del server: ' . $e->getMessage(),
    ]);
}
