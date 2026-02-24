<?php
$files = glob(__DIR__ . '/*.php');

// Ordina alfabeticamente
sort($files);

// Rimuove index.php dall'elenco
$files = array_filter($files, function ($file) {
	return basename($file) !== 'index.php';
});
?>
<!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="UTF-8">
	<title>Migrazioni disponibili</title>
	<style>
		body { font-family: sans-serif; margin: 2em; }
		h1 { font-size: 1.5em; }
		ul { list-style: none; padding-left: 0; }
		li { margin-bottom: .5em; }
		a { text-decoration: none; color: #0066cc; }
		a:hover { text-decoration: underline; }
	</style>
</head>
<body>
	<h1>Script di migrazione</h1>
	<ul>
		<?php foreach ($files as $file): ?>
			<li>
				<a href="<?= htmlspecialchars(basename($file)) ?>">
					<?= htmlspecialchars(basename($file)) ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</body>
</html>
