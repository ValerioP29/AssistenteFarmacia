<?php
/**
 * Genera un pool iniziale di tagging (100-300) da CSV e produce:
 * - payload bulk JSON pronto per /panel/api/pharma-products/tags-bulk.php
 * - report markdown con metriche e esempi
 *
 * Uso:
 * php panel/scripts/generate_initial_tagging_pool.php \
 *   --pharma_id=1 \
 *   --source=panel/import/prodotti-giovinazzi.csv \
 *   --pool=200 \
 *   --featured=100
 */

require_once __DIR__ . '/../includes/product_tags_engine.php';

$options = getopt('', ['pharma_id::', 'source::', 'pool::', 'featured::']);

$pharmaId = isset($options['pharma_id']) ? (int)$options['pharma_id'] : 1;
$source = $options['source'] ?? (__DIR__ . '/../import/prodotti-giovinazzi.csv');
$poolSize = isset($options['pool']) ? (int)$options['pool'] : 200;
$featuredTarget = isset($options['featured']) ? (int)$options['featured'] : 100;

$poolSize = max(100, min(300, $poolSize));
$featuredTarget = max(0, min(150, $featuredTarget));

if (!file_exists($source)) {
    fwrite(STDERR, "Source file non trovato: {$source}\n");
    exit(1);
}

$handle = fopen($source, 'r');
if (!$handle) {
    fwrite(STDERR, "Impossibile aprire il file sorgente: {$source}\n");
    exit(1);
}

$rows = [];
while (($data = fgetcsv($handle, 0, ',')) !== false) {
    if (count($data) < 2) {
        continue;
    }

    $name = trim((string)$data[0]);
    $sku = trim((string)$data[1]);
    $priceRaw = $data[2] ?? null;

    if ($name === '' || $sku === '') {
        continue;
    }

    if (isset($rows[$sku])) {
        continue;
    }

    $rows[$sku] = [
        'sku' => $sku,
        'name' => $name,
        'price' => $priceRaw,
    ];
}
fclose($handle);

$rows = array_values($rows);
$rows = array_slice($rows, 0, $poolSize);

$processed = [];
foreach ($rows as $row) {
    $suggestion = suggestTagsFromName($row['name']);

    $appliedTags = [];
    if ($suggestion['confidence'] === 'high') {
        $appliedTags = $suggestion['suggested_tags'];
    } elseif ($suggestion['confidence'] === 'medium') {
        $filtered = array_values(array_filter($suggestion['suggested_tags'], function ($tag) {
            return $tag !== 'altro';
        }));
        $appliedTags = $filtered;
    }

    $processed[] = [
        'sku' => $row['sku'],
        'name' => $row['name'],
        'confidence' => $suggestion['confidence'],
        'matched_keywords' => $suggestion['matched_keywords'],
        'suggested_tags' => $suggestion['suggested_tags'],
        'applied_tags' => $appliedTags,
        'is_featured' => 0,
    ];
}

// Featured: criterio fallback documentato
// 1) Priorità a prodotti taggati (confidence alta/media)
// 2) Riempimento fino al target con prodotti "presentabili" da nome (non vuoto, lunghezza >= 8)
$eligibleFeatured = array_values(array_filter($processed, function ($p) {
    return strlen(trim($p['name'])) >= 8;
}));

usort($eligibleFeatured, function ($a, $b) {
    $score = ['high' => 2, 'medium' => 1, 'low' => 0];
    $sa = $score[$a['confidence']] ?? 0;
    $sb = $score[$b['confidence']] ?? 0;

    if ($sa !== $sb) return $sb <=> $sa;
    return strlen($b['name']) <=> strlen($a['name']);
});

$featuredSkus = [];

// Prima passata: solo taggati
foreach ($eligibleFeatured as $item) {
    if (count($featuredSkus) >= $featuredTarget) break;
    if (empty($item['applied_tags'])) continue;
    $featuredSkus[$item['sku']] = true;
}

// Seconda passata: fill fino al target con criteri presentabilità fallback
foreach ($eligibleFeatured as $item) {
    if (count($featuredSkus) >= $featuredTarget) break;
    if (isset($featuredSkus[$item['sku']])) continue;
    $featuredSkus[$item['sku']] = true;
}

$tagDistribution = [];
$taggedCount = 0;
$withoutTagCount = 0;
$featuredCount = 0;

$bulkItems = [];
foreach ($processed as &$item) {
    $item['is_featured'] = isset($featuredSkus[$item['sku']]) ? 1 : 0;

    if (!empty($item['applied_tags'])) {
        $taggedCount++;
        foreach ($item['applied_tags'] as $tag) {
            if (!isset($tagDistribution[$tag])) $tagDistribution[$tag] = 0;
            $tagDistribution[$tag]++;
        }
    } else {
        $withoutTagCount++;
    }

    if ($item['is_featured'] === 1) {
        $featuredCount++;
    }

    // Bulk payload: usa sku per compatibilità con endpoint bulk esteso
    if (!empty($item['applied_tags']) || $item['is_featured'] === 1) {
        $bulkItems[] = [
            'sku' => $item['sku'],
            'tags' => !empty($item['applied_tags']) ? $item['applied_tags'] : null,
            'is_featured' => $item['is_featured'],
        ];
    }
}
unset($item);

arsort($tagDistribution);

$examples = array_values(array_filter($processed, function ($p) {
    return !empty($p['applied_tags']);
}));
$examples = array_slice($examples, 0, 20);

$timestamp = date('Ymd-His');
$reportDir = __DIR__ . '/../reports';
if (!is_dir($reportDir)) mkdir($reportDir, 0775, true);

$payloadPath = $reportDir . '/initial_tagging_payload_' . $timestamp . '.json';
$reportPath = $reportDir . '/initial_tagging_report_' . $timestamp . '.md';

$payload = [
    'pharma_id' => $pharmaId,
    'items' => $bulkItems,
    'meta' => [
        'source' => $source,
        'pool_size' => $poolSize,
        'featured_target' => $featuredTarget,
        'generated_at' => date('c'),
        'note' => 'Pool da CSV: non disponibile stato is_active/image, usato criterio fallback documentato.',
    ],
];

file_put_contents($payloadPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$distributionLines = [];
foreach ($tagDistribution as $tag => $count) {
    $distributionLines[] = "- {$tag}: {$count}";
}
if (empty($distributionLines)) {
    $distributionLines[] = '- (nessun tag assegnato)';
}

$exampleLines = [];
foreach ($examples as $ex) {
    $exampleLines[] = "- {$ex['name']} -> [" . implode(', ', $ex['applied_tags']) . "]";
}
if (empty($exampleLines)) {
    $exampleLines[] = '- (nessun esempio disponibile)';
}

$report = "# Report Tagging Iniziale\n\n";
$report .= "## Parametri\n";
$report .= "- Fonte: `{$source}`\n";
$report .= "- Pharma ID: `{$pharmaId}`\n";
$report .= "- Pool richiesto: `{$poolSize}`\n";
$report .= "- Featured target: `{$featuredTarget}`\n\n";
$report .= "## Risultati\n";
$report .= "- Prodotti analizzati: **" . count($processed) . "**\n";
$report .= "- Prodotti taggati: **{$taggedCount}**\n";
$report .= "- Prodotti senza tag: **{$withoutTagCount}**\n";
$report .= "- Prodotti featured: **{$featuredCount}**\n\n";
$report .= "## Distribuzione per tag\n" . implode("\n", $distributionLines) . "\n\n";
$report .= "## 20 esempi (nome -> tag)\n" . implode("\n", $exampleLines) . "\n\n";
$report .= "## Criteri usati\n";
$report .= "- Tagging: `high` sempre applicato, `medium` applicato se tag != `altro`, `low` lasciato vuoto per review.\n";
$report .= "- Featured (fallback): priorità ai prodotti taggati con confidence `high|medium`; riempimento fino al target con nomi >= 8 caratteri (presentabilità base).\n";
$report .= "- Nota: dal CSV non sono deducibili campi `is_active`/`image`, quindi applicato criterio fallback documentato.\n\n";
$report .= "## File generati\n";
$report .= "- Payload bulk: `{$payloadPath}`\n";
$report .= "- Report: `{$reportPath}`\n";

file_put_contents($reportPath, $report);

echo "Payload generato: {$payloadPath}\n";
echo "Report generato: {$reportPath}\n";
