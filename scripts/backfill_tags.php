#!/usr/bin/env php
<?php
/**
 * scripts/backfill_tags.php
 *
 * Assegna o aggiorna i tag prodotti in jta_pharma_prods.
 *
 * UTILIZZO:
 *   php backfill_tags.php [opzioni]
 *
 * OPZIONI:
 *   --pharma_id=N       Limita a una singola farmacia (default: tutte)
 *   --limit=N           Max prodotti da processare, 1-5000 (default: 500)
 *   --dry-run           Mostra le modifiche senza scriverle
 *   --force-retag       Ri-tagga anche prodotti già taggati
 *   --update-names      Aggiorna anche display_name con normalize_product_name()
 *   --remap-legacy      Rimappa solo i vecchi slug → slug canonici (senza rigenerare i tag)
 *   --verbose           Stampa ogni prodotto processato
 *
 * SCENARI TIPICI:
 *   # Prima esecuzione: tagga tutti i prodotti senza tag
 *   php backfill_tags.php --limit=5000
 *
 *   # Dopo il refactor taxonomy: rimappa slug legacy senza rigenerare tutto
 *   php backfill_tags.php --remap-legacy --limit=5000
 *
 *   # Rigenera tutti i tag da zero (es. dopo aggiornamento regole)
 *   php backfill_tags.php --force-retag --limit=5000
 *
 *   # Aggiorna anche i nomi display leggibili
 *   php backfill_tags.php --update-names --limit=5000
 *
 *   # Segna come in promozione tutti i prodotti taggati
 *   php backfill_tags.php --mark-sale --limit=10000
 *
 *   # Test senza scrivere
 *   php backfill_tags.php --limit=100 --dry-run --verbose
 */

require_once(__DIR__ . '/../api/_api_bootstrap.php');
require_once(__DIR__ . '/../api/helpers/_related_tags.php');
// taxonomy/tags.php è già caricata transitivamente da _related_tags.php

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "Questo script va eseguito da CLI.\n");
	exit(1);
}

// ── Opzioni CLI ──────────────────────────────────────────────────────────────

$options     = getopt('', ['pharma_id::', 'limit::', 'dry-run', 'force-retag', 'update-names', 'remap-legacy', 'names-only', 'mark-sale', 'verbose']);
$pharmaId    = isset($options['pharma_id'])  ? (int)$options['pharma_id'] : null;
$limit       = isset($options['limit'])      ? max(1, min(10000, (int)$options['limit'])) : 500;
$dryRun      = array_key_exists('dry-run',      $options);
$forceRetag  = array_key_exists('force-retag',  $options);
$updateNames = array_key_exists('update-names', $options);
$remapLegacy = array_key_exists('remap-legacy', $options);
$namesOnly   = array_key_exists('names-only',   $options);
$markSale    = array_key_exists('mark-sale',    $options);
$verbose     = array_key_exists('verbose',      $options);

// --names-only: aggiorna SOLO display_name su tutti i prodotti, senza toccare i tag
if ($namesOnly) {
	$updateNames = true;
	$forceRetag  = false;
	$remapLegacy = false;
}

// ── Mappa slug legacy → canonici ────────────────────────────────────────────
// Usata da --remap-legacy. Rispecchia gli alias in taxonomy/tags.php.
// Aggiungere qui se in futuro vengono rinominati altri slug.
const LEGACY_SLUG_MAP = [
	'dermocosmetica'        => 'dermocosmesi',
	'gastrointestinale'     => 'gastro',
	'pediatria'             => 'bambino',
	'neonati'               => 'bambino',
	'latte_formula'         => 'bambino',
	'pappe_svezzamento'     => 'bambino',
	'mamma_allattamento'    => 'bambino',
	'integratori'           => 'vitamine_integratori',
	'vitamini_minerali'     => 'vitamine_integratori',
	'antiossidanti'         => 'vitamine_integratori',
	'energia_sport'         => 'vitamine_integratori',
	'dimagrimento'          => 'vitamine_integratori',
	'colesterolo_trigliceridi' => 'vitamine_integratori',
	'sonno_stress_ansia'    => 'sonno_stress',
	'diabete_glicemia'      => 'diabete_supporto',
	'oculistica'            => 'occhi',
	'lenti_a_contatto'      => 'occhi',
	'tosse_raffreddore'     => 'tosse',
	'naso_gola'             => 'naso',
	'otorinolaringoiatria'  => 'gola',
	'ortopedia_fisioterapia'  => 'ortopedia',
	'apparato_muscolo_scheletrico' => 'ortopedia',
	'medicazione_ferite'    => 'medicazione',
	'pressione_arteriosa'   => 'pressione',
	'cardiovascolare'       => 'pressione',
	'cardioprotettori'      => 'pressione',
	'senza_glutine'         => 'celiachia',
	'dietetica'             => 'celiachia',
	'ginecologia'           => 'donna',
	'igiene_intima'         => 'donna',
	'viso'                  => 'dermocosmesi',
	'corpo'                 => 'dermocosmesi',
	'cicatrici_smagliature' => 'dermocosmesi',
	'make_up'               => 'dermocosmesi',
	'igiene_personale'      => 'dermocosmesi',
	'profumeria'            => 'dermocosmesi',
	'protezione_solare'     => 'protezione_solare', // già canonico, no-op
	'omeopatia_sali_schussler' => 'omeopatia',
	'omeopatia_bach'        => 'omeopatia',
	'fitoterapico'          => 'fitoterapia',
	'erboristeria'          => 'fitoterapia',
	'pannoloni'             => 'incontinenza',
	'aghi_siringhe'         => 'dispositivi_medici',
	'apparecchi_elettromedicali' => 'dispositivi_medici',
	'presidi_medici'        => 'dispositivi_medici',
	'protesi_dentali'       => 'igiene_orale',
	'farmaci'               => 'farmaco_prescrivibile',
	'ricetta'               => 'farmaco_prescrivibile',
	'neurologia'            => 'sonno_stress',
	'sistema_nervoso'       => 'sonno_stress',
	'tiroide'               => 'vitamine_integratori',
	'endocrinologia'        => 'vitamine_integratori',
	'apparato_respiratorio' => 'allergia',
	'asma_bpco'             => 'allergia',
	'urologia_reni'         => 'vitamine_integratori',
	'sessualita'            => 'donna',
	'senza_lattosio'        => 'celiachia',
	'epatico_biliare'       => 'gastro',
	'lassativi'             => 'gastro',
	'nausea_vomito'         => 'gastro',
	'antinfiammatori'       => 'dolore_febbre',
	'antibiotici'           => 'farmaco_prescrivibile',
	'antivirali'            => 'farmaco_prescrivibile',
	'veterinaria'           => 'altro',
];

/**
 * Rimappa un array di tag applicando LEGACY_SLUG_MAP.
 * Restituisce null se nessun tag era legacy (nessuna modifica necessaria).
 */
function remap_legacy_tags(array $tags): ?array
{
	$changed = false;
	$remapped = [];
	foreach ($tags as $tag) {
		$canonical = LEGACY_SLUG_MAP[$tag] ?? $tag;
		if ($canonical !== $tag) {
			$changed = true;
		}
		$remapped[] = $canonical;
	}
	if (!$changed) {
		return null;
	}
	return array_values(array_unique($remapped));
}

function decode_tags_raw(string $raw): array
{
	$raw = trim($raw);
	if ($raw === '') return [];
	$decoded = json_decode($raw, true);
	if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
		return array_values(array_filter(array_map('strval', $decoded)));
	}
	return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

// ── Verifica colonne ────────────────────────────────────────────────────────

global $pdo;

$hasCategoryColumn    = false;
$hasDisplayNameColumn = false;

try {
	$stmt = $pdo->prepare("SHOW COLUMNS FROM jta_pharma_prods LIKE 'category'");
	$stmt->execute();
	$hasCategoryColumn = $stmt->rowCount() > 0;
} catch (Exception $e) {}

try {
	$stmt = $pdo->prepare("SHOW COLUMNS FROM jta_pharma_prods LIKE 'display_name'");
	$stmt->execute();
	$hasDisplayNameColumn = $stmt->rowCount() > 0;
} catch (Exception $e) {}

if ($updateNames && !$hasDisplayNameColumn) {
	echo "[info] Colonna display_name non trovata — la creo.\n";
	if (!$dryRun) {
		try {
			$pdo->exec("ALTER TABLE jta_pharma_prods ADD COLUMN display_name VARCHAR(512) NULL DEFAULT NULL AFTER name");
			$hasDisplayNameColumn = true;
			echo "[info] Colonna display_name creata.\n";
		} catch (Exception $e) {
			fwrite(STDERR, "[errore] ALTER TABLE fallito: " . $e->getMessage() . "\n");
			exit(1);
		}
	} else {
		echo "[dry-run] ALTER TABLE jta_pharma_prods ADD COLUMN display_name ...\n";
		$hasDisplayNameColumn = true; // simula per dry-run
	}
}

// ── Query prodotti ───────────────────────────────────────────────────────────

$selectCategory    = $hasCategoryColumn    ? 'category'     : "'' AS category";
$selectDisplayName = $hasDisplayNameColumn ? 'display_name' : "NULL AS display_name";

$where  = ["(name IS NOT NULL OR description IS NOT NULL)"];
$params = [];

if ($remapLegacy) {
	// --remap-legacy: prende solo i prodotti che HANNO già tag (da rimappare)
	$where[] = "(tags IS NOT NULL AND TRIM(CAST(tags AS CHAR)) <> '' AND NOT (JSON_VALID(tags) AND JSON_LENGTH(tags) = 0))";
} elseif ($namesOnly) {
	// --names-only: tutti i prodotti (tagged + untagged), nessun filtro tag
	// niente da aggiungere al WHERE
} elseif ($markSale) {
	// --mark-sale: solo prodotti già taggati e con is_on_sale != 1
	$where[] = "(tags IS NOT NULL AND TRIM(CAST(tags AS CHAR)) <> '' AND NOT (JSON_VALID(tags) AND JSON_LENGTH(tags) = 0))";
	$where[] = "(is_on_sale IS NULL OR is_on_sale != 1)";
} elseif (!$forceRetag) {
	// Default: solo prodotti senza tag
	$where[] = "(tags IS NULL OR (JSON_VALID(tags) AND JSON_LENGTH(tags) = 0) OR TRIM(CAST(tags AS CHAR)) = '')";
}

if (!is_null($pharmaId) && $pharmaId > 0) {
	$where[]                = 'pharma_id = :pharma_id';
	$params[':pharma_id']   = $pharmaId;
}

$sql = "SELECT id, name, description, {$selectCategory}, {$selectDisplayName}, tags
	FROM jta_pharma_prods
	WHERE " . implode(' AND ', $where) . "
	ORDER BY id ASC
	LIMIT :limit";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
	$stmt->bindValue($key, $val, PDO::PARAM_INT);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Contatori ────────────────────────────────────────────────────────────────

$total       = count($rows);
$updated     = 0;
$skipped     = 0;
$remapped    = 0;
$namesUpdate = 0;

echo sprintf(
	"[info] Modalità: %s | prodotti da esaminare: %d | limit: %d%s\n",
	$remapLegacy ? 'remap-legacy' : ($namesOnly ? 'names-only' : ($markSale ? 'mark-sale' : ($forceRetag ? 'force-retag' : 'default'))),
	$total,
	$limit,
	$dryRun ? ' | DRY-RUN' : ''
);

if ($total === 0) {
	echo "[info] Nessun prodotto da processare.\n";
	exit(0);
}

// ── Transazione ──────────────────────────────────────────────────────────────

if (!$dryRun) {
	$pdo->beginTransaction();
}

try {
	$updateTagsStmt = $pdo->prepare(
		"UPDATE jta_pharma_prods SET tags = :tags WHERE id = :id"
	);
	$updateBothStmt = $pdo->prepare(
		"UPDATE jta_pharma_prods SET tags = :tags, display_name = :display_name WHERE id = :id"
	);
	$updateNameStmt = $pdo->prepare(
		"UPDATE jta_pharma_prods SET display_name = :display_name WHERE id = :id"
	);

	foreach ($rows as $row) {
		$id          = (int)$row['id'];
		$name        = (string)($row['name'] ?? '');
		$description = (string)($row['description'] ?? '');
		$category    = (string)($row['category'] ?? '');
		$rawTags     = (string)($row['tags'] ?? '');
		$rawDisplay  = $row['display_name'] ?? null;

		// ── Calcola nuovo display_name ──────────────────────────────────
		$newDisplayName = null;
		if ($updateNames && $hasDisplayNameColumn) {
			$computed = normalize_product_name($name);
			if ($computed !== '' && $computed !== $rawDisplay) {
				$newDisplayName = $computed;
			}
		}

		// ── Calcola nuovi tag ───────────────────────────────────────────
		$newTagsJson = null;

		// --mark-sale: imposta is_on_sale=1, salta tutta la logica tag/nomi
		if ($markSale) {
			if ($dryRun) {
				echo "[dry-run] is_on_sale=1 id={$id} name=\"{$name}\"\n";
				$updated++;
			} else {
				$pdo->prepare("UPDATE jta_pharma_prods SET is_on_sale = 1 WHERE id = :id")
				    ->execute([':id' => $id]);
				$updated++;
				if ($verbose) echo "[ok] is_on_sale=1 id={$id} name=\"{$name}\"\n";
			}
			continue;
		}

		// --names-only: salta tutta la logica tag, aggiorna solo display_name
		if ($namesOnly) {
			if ($newDisplayName !== null) {
				if ($dryRun) {
					echo "[dry-run] display_name id={$id} → \"{$newDisplayName}\"\n";
				} else {
					$updateNameStmt->execute([':id' => $id, ':display_name' => $newDisplayName]);
					if ($updateNameStmt->rowCount() > 0) { $namesUpdate++; $updated++; }
				}
			} else {
				$skipped++;
				if ($verbose) echo "[skip] id={$id} — display_name invariato\n";
			}
			continue;
		}

		if ($remapLegacy) {
			$existingTags = decode_tags_raw($rawTags);
			$remappedTags = remap_legacy_tags($existingTags);
			if ($remappedTags !== null) {
				$newTagsJson = json_encode($remappedTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				$remapped++;
			}
		} else {
			$tags = related_tags_infer_from_product($name, $description, $category);
			if (empty($tags)) {
				if ($verbose) {
					echo "[skip] id={$id} name=\"{$name}\" — nessun tag inferito\n";
				}
				$skipped++;
				// Se --update-names, aggiorna il display_name anche se non ci sono tag
				if ($newDisplayName !== null && !$dryRun) {
					$updateNameStmt->execute([':id' => $id, ':display_name' => $newDisplayName]);
					$namesUpdate++;
				} elseif ($newDisplayName !== null && $dryRun) {
					echo "[dry-run] display_name id={$id} → \"{$newDisplayName}\"\n";
				}
				continue;
			}
			$newTagsJson = json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		// ── Scrivi in DB ────────────────────────────────────────────────
		if ($dryRun) {
			if ($newTagsJson !== null) {
				echo "[dry-run] tags id={$id} → {$newTagsJson}\n";
			}
			if ($newDisplayName !== null) {
				echo "[dry-run] display_name id={$id} → \"{$newDisplayName}\"\n";
			}
			$updated++;
			continue;
		}

		$affected = false;
		if ($newTagsJson !== null && $newDisplayName !== null) {
			$updateBothStmt->execute([':id' => $id, ':tags' => $newTagsJson, ':display_name' => $newDisplayName]);
			$affected = $updateBothStmt->rowCount() > 0;
			$namesUpdate++;
		} elseif ($newTagsJson !== null) {
			$updateTagsStmt->execute([':id' => $id, ':tags' => $newTagsJson]);
			$affected = $updateTagsStmt->rowCount() > 0;
		} elseif ($newDisplayName !== null) {
			$updateNameStmt->execute([':id' => $id, ':display_name' => $newDisplayName]);
			$namesUpdate += $updateNameStmt->rowCount() > 0 ? 1 : 0;
		}

		if ($affected) {
			$updated++;
			if ($verbose) {
				echo "[ok] id={$id} name=\"{$name}\" tags={$newTagsJson}\n";
			}
		} else {
			$skipped++;
		}
	}

	if (!$dryRun) {
		$pdo->commit();
	}

} catch (Exception $e) {
	if (!$dryRun && $pdo->inTransaction()) {
		$pdo->rollBack();
	}
	fwrite(STDERR, 'Errore backfill tags: ' . $e->getMessage() . PHP_EOL);
	exit(1);
}

// ── Riepilogo ────────────────────────────────────────────────────────────────

$summary = sprintf(
	"Backfill completato. scanned=%d updated=%d skipped=%d%s%s dry_run=%s%s",
	$total,
	$updated,
	$skipped,
	$remapLegacy    ? " remapped_legacy={$remapped}" : '',
	$markSale       ? " marked_sale={$updated}" : '',
	$updateNames    ? " names_updated={$namesUpdate}" : '',
	$dryRun         ? 'yes' : 'no',
	(!is_null($pharmaId) && $pharmaId > 0) ? " pharma_id={$pharmaId}" : ''
);

echo $summary . PHP_EOL;