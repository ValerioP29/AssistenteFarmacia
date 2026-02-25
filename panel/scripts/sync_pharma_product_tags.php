<?php
/**
 * Sync tags prodotti farmacia da catalogo globale + motore regole nome.
 *
 * Uso:
 * php panel/scripts/sync_pharma_product_tags.php --pharma_id=1 [--limit=1000] [--dry-run=1]
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_tags_engine.php';

$options = getopt('', ['pharma_id::', 'limit::', 'dry-run::']);
$pharmaId = isset($options['pharma_id']) ? (int)$options['pharma_id'] : 0;
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 5000;
$dryRun = in_array((string)($options['dry-run'] ?? '0'), ['1', 'true', 'yes', 'on'], true);

if ($pharmaId <= 0) {
	fwrite(STDERR, "Parametro obbligatorio: --pharma_id=<id>\n");
	exit(1);
}

function normalizeRelatedTag(string $value): string {
	$value = strtolower(trim($value));
	$value = str_replace(['-', ' '], '_', $value);
	return $value;
}

function mapGlobalCategoryToTags(?string $category): array {
	if ($category === null) return [];
	$raw = strtolower(trim($category));
	if ($raw === '') return [];

	$categoryMap = [
		'dolore_febbre' => ['dolore', 'febbre', 'antidolorifici', 'analgesici', 'antinfiammatori'],
		'raffreddore_influenza' => ['raffreddore', 'influenza', 'decongestionanti', 'respiratorio'],
		'gola' => ['gola', 'faringe', 'orofaringeo'],
		'tosse' => ['tosse', 'espettoranti', 'sciroppi'],
		'gastro' => ['gastro', 'digestione', 'reflusso', 'intestino'],
		'dermocosmesi' => ['dermocosmesi', 'pelle', 'viso', 'beauty'],
		'vitamine_integratori' => ['vitamine', 'integratori', 'benessere', 'minerali'],
		'bambino' => ['bambino', 'infanzia', 'baby', 'pediatrico'],
		'medicazione' => ['medicazione', 'cerotti', 'disinfettanti', 'garze'],
		'igiene_orale' => ['igiene orale', 'orale', 'dentifricio', 'collutorio', 'gengive'],
		'naso' => ['naso', 'nasale', 'rinite', 'sinusite'],
		'occhi' => ['occhi', 'oculare', 'colliri'],
	];

	$matched = [];
	foreach ($categoryMap as $tag => $keywords) {
		foreach ($keywords as $keyword) {
			if (strpos($raw, strtolower($keyword)) !== false) {
				$matched[$tag] = true;
				break;
			}
		}
	}

	return array_keys($matched);
}

try {
	$hasTagsColumn = false;
	$check = db_query("SHOW COLUMNS FROM jta_pharma_prods LIKE 'tags'");
	$hasTagsColumn = $check->rowCount() > 0;
	if (!$hasTagsColumn) {
		fwrite(STDERR, "Colonna tags mancante su jta_pharma_prods. Esegui migrazione 20260224_add_tags_to_jta_pharma_prods.sql\n");
		exit(2);
	}

	$sql = "SELECT pp.id, pp.name, pp.tags, gp.category
			FROM jta_pharma_prods pp
			LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id
			WHERE pp.pharma_id = ? AND pp.is_active = 1
			ORDER BY pp.id ASC
			LIMIT ?";
	$rows = db_fetch_all($sql, [$pharmaId, $limit]);

	$updated = 0;
	$skipped = 0;
	$taggedFromCategory = 0;
	$taggedFromName = 0;

	foreach ($rows as $row) {
		$currentTags = normalizeTagArray($row['tags'] ?? null);
		$categoryTags = mapGlobalCategoryToTags($row['category'] ?? null);
		$nameSuggestion = suggestTagsFromName((string)($row['name'] ?? ''));
		$nameTags = array_values(array_filter($nameSuggestion['suggested_tags'] ?? [], function ($tag) {
			return normalizeRelatedTag((string)$tag) !== 'altro';
		}));

		$merged = [];
		foreach (array_merge($currentTags, $categoryTags, $nameTags) as $tag) {
			$tag = normalizeRelatedTag((string)$tag);
			if ($tag === '' || $tag === 'altro') continue;
			$merged[$tag] = true;
		}
		$finalTags = array_keys($merged);

		if (!empty($categoryTags)) $taggedFromCategory++;
		if (!empty($nameTags)) $taggedFromName++;

		$same = json_encode($currentTags) === json_encode($finalTags);
		if ($same) {
			$skipped++;
			continue;
		}

		if (!$dryRun) {
			db()->update('jta_pharma_prods', ['tags' => !empty($finalTags) ? json_encode($finalTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null], 'id = ? AND pharma_id = ?', [(int)$row['id'], $pharmaId]);
		}

		$updated++;
	}

	echo json_encode([
		'success' => true,
		'pharma_id' => $pharmaId,
		'limit' => $limit,
		'dry_run' => $dryRun,
		'total_rows' => count($rows),
		'updated' => $updated,
		'skipped' => $skipped,
		'tagged_from_global_category' => $taggedFromCategory,
		'tagged_from_name_rules' => $taggedFromName,
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Exception $e) {
	fwrite(STDERR, 'Errore sync tags: ' . $e->getMessage() . "\n");
	exit(1);
}
