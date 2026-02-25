#!/usr/bin/env php
<?php

require_once(__DIR__ . '/../api/_api_bootstrap.php');
require_once(__DIR__ . '/../api/helpers/_related_tags.php');

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "Questo script va eseguito da CLI.\n");
	exit(1);
}

$options = getopt('', ['pharma_id::', 'limit::', 'dry-run']);
$pharmaId = isset($options['pharma_id']) ? (int)$options['pharma_id'] : null;
$limit = isset($options['limit']) ? max(1, min(5000, (int)$options['limit'])) : 500;
$dryRun = array_key_exists('dry-run', $options);

global $pdo;

$hasCategoryColumn = false;
try {
	$stmt = $pdo->prepare("SHOW COLUMNS FROM jta_pharma_prods LIKE 'category'");
	$stmt->execute();
	$hasCategoryColumn = $stmt->rowCount() > 0;
} catch (Exception $e) {
	$hasCategoryColumn = false;
}

$selectCategory = $hasCategoryColumn ? 'category' : "'' AS category";
$where = [
	"(tags IS NULL
	  OR (JSON_VALID(tags) AND JSON_LENGTH(tags) = 0)
	  OR TRIM(CAST(tags AS CHAR)) = '')",
	"(name IS NOT NULL OR description IS NOT NULL)",
];
$params = [];

if (!is_null($pharmaId) && $pharmaId > 0) {
	$where[] = 'pharma_id = :pharma_id';
	$params[':pharma_id'] = $pharmaId;
}

$sql = "SELECT id, name, description, {$selectCategory}, tags
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

$total = count($rows);
$wouldUpdate = 0;
$updated = 0;
$skipped = 0;

if (!$dryRun) {
	$pdo->beginTransaction();
}

try {
	$updateStmt = $pdo->prepare("UPDATE jta_pharma_prods SET tags = :tags WHERE id = :id");

	foreach ($rows as $row) {
		$tags = related_tags_infer_from_product($row['name'] ?? '', $row['description'] ?? '', $row['category'] ?? '');
		if (empty($tags)) {
			$skipped++;
			continue;
		}

		$encodedTags = json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$wouldUpdate++;

		if ($dryRun) {
			echo '[dry-run] update id=' . (int)$row['id'] . ' tags=' . $encodedTags . PHP_EOL;
			continue;
		}

		$updateStmt->execute([
			':id' => (int)$row['id'],
			':tags' => $encodedTags,
		]);
		$updated += $updateStmt->rowCount() > 0 ? 1 : 0;
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

echo 'Backfill completato. scanned=' . $total
	. ' would_update=' . $wouldUpdate
	. ' updated=' . $updated
	. ' skipped=' . $skipped
	. ' dry_run=' . ($dryRun ? 'yes' : 'no')
	. ($pharmaId ? ' pharma_id=' . $pharmaId : '')
	. PHP_EOL;
