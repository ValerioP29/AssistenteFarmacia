<?php
/**
 * Sync tags prodotti farmacia da catalogo globale + motore regole nome.
 *
 * Policy sicurezza (default): preserve-manual + dry-run.
 * - preserve-manual: non rimuove i tag già presenti; aggiunge solo auto-tag mancanti.
 * - dry-run di default: senza --apply non scrive su DB.
 * - Logga il diff per record (added / preserved / before / after).
 * - Usa --apply per applicare gli update reali.
 * - Usa --dry-run=0 solo insieme a --apply se vuoi forzare esplicitamente.
 *
 * Uso:
 * php panel/scripts/sync_pharma_product_tags.php --pharma_id=1 [--limit=1000] [--apply] [--dry-run=1]
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_tags_engine.php';

$options = getopt('', ['pharma_id::', 'limit::', 'dry-run::', 'apply::']);
$pharmaId = isset($options['pharma_id']) ? (int)$options['pharma_id'] : 0;
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 5000;

$applyFlagRaw = (string)($options['apply'] ?? '');
$applyEnabled = $applyFlagRaw !== ''
	? in_array(strtolower($applyFlagRaw), ['1', 'true', 'yes', 'on'], true)
	: array_key_exists('apply', $options);

// default ON: dry-run unless --apply is provided
$dryRun = !$applyEnabled;
if (array_key_exists('dry-run', $options)) {
	$dryRun = in_array((string)$options['dry-run'], ['1', 'true', 'yes', 'on'], true);
}

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
	$changes = [];

	foreach ($rows as $row) {
		$currentTags = normalizeTagArray($row['tags'] ?? null);
		$categoryTags = array_map('normalizeRelatedTag', mapGlobalCategoryToTags($row['category'] ?? null));
		$nameSuggestion = suggestTagsFromName((string)($row['name'] ?? ''));
		$nameTags = array_values(array_filter(array_map('normalizeRelatedTag', $nameSuggestion['suggested_tags'] ?? []), function ($tag) {
			return $tag !== 'altro' && $tag !== '';
		}));

		// preserve-manual: conserva SEMPRE i tag già presenti
		$preserved = [];
		foreach ($currentTags as $tag) {
			$normTag = normalizeRelatedTag((string)$tag);
			if ($normTag === '') continue;
			$preserved[$normTag] = true;
		}

		$autoPool = [];
		foreach (array_merge($categoryTags, $nameTags) as $tag) {
			if ($tag === '' || $tag === 'altro') continue;
			$autoPool[$tag] = true;
		}

		$before = array_keys($preserved);
		$finalMap = $preserved;
		$added = [];
		foreach (array_keys($autoPool) as $candidate) {
			if (!isset($finalMap[$candidate])) {
				$finalMap[$candidate] = true;
				$added[] = $candidate;
			}
		}
		$after = array_keys($finalMap);

		if (!empty($categoryTags)) $taggedFromCategory++;
		if (!empty($nameTags)) $taggedFromName++;

		if (empty($added)) {
			$skipped++;
			continue;
		}

		$changes[] = [
			'id' => (int)$row['id'],
			'name' => (string)($row['name'] ?? ''),
			'before' => $before,
			'added' => $added,
			'preserved' => $before,
			'after' => $after,
		];

		if (!$dryRun) {
			db()->update(
				'jta_pharma_prods',
				['tags' => !empty($after) ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null],
				'id = ? AND pharma_id = ?',
				[(int)$row['id'], $pharmaId]
			);
		}

		$updated++;
	}

	echo json_encode([
		'success' => true,
		'pharma_id' => $pharmaId,
		'limit' => $limit,
		'mode' => $dryRun ? 'dry-run (default preserve-manual)' : 'apply (preserve-manual)',
		'dry_run' => $dryRun,
		'total_rows' => count($rows),
		'updated' => $updated,
		'skipped' => $skipped,
		'tagged_from_global_category' => $taggedFromCategory,
		'tagged_from_name_rules' => $taggedFromName,
		'changes' => $changes,
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Exception $e) {
	fwrite(STDERR, 'Errore sync tags: ' . $e->getMessage() . "\n");
	exit(1);
}
