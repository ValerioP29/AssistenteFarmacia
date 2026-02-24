<?php
	$success = null;
	$error = null;

	require_once('../_api_bootstrap.php');
	$all_categories = get_profiling_categories();
	$pharma_id = isset($_GET['pharma_id']) && is_numeric($_GET['pharma_id']) ? (int) $_GET['pharma_id'] : 1;

	if( isset($_GET['date']) && is_valid_date($_GET['date']) ){
		PillsModel::generateEmptyPillsByDate($_GET['date'], $pharma_id);
	}

	if( isset($_GET['tomorrow']) ){
		$today_date = new DateTime();
		$today_date->modify('+1 day');
		$tomorrow_date = $today_date->format('Y-m-d');
		PillsModel::generateEmptyPillsByDate($tomorrow_date, $pharma_id);
		exit;
	}

	if( isset($_GET['magic']) ){
		PillsModel::populateAnEmptyPill($pharma_id);
		echo 'Pillole ancora da generare: '.PillsModel::countEmptyPills($pharma_id);
		exit;
	}

	$default_date = '';
	$last_pill = PillsModel::getLatest(1, $pharma_id, FALSE);
	if( is_array($last_pill) && ! empty($last_pill) ){
		$default_date = new DateTime($last_pill[0]['day']);
		$default_date->modify('+1 day');
		$default_date = $default_date->format('Y-m-d');
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$date = $_POST['date'] ?? '';
		$category = trim($_POST['category'] ?? '');
		$psw = $_POST['password'] ?? FALSE;

		if ( ! $psw ) {
			$error = "Il campo password è obbligatorio.";
		}else if( $psw != 'jta25' ) {
			$error = "Il campo password è errato.";
		} else {
			try{
				$category = get_random_profiling_category();
				$pill_id = PillsModel::insertFromAI( $date, $category, $pharma_id );

				if (! $pill_id) {
					$error = "Errore nella generazione della pillola.";
				} else {
					$success = "✅ Pillola inserita per il giorno <strong>$date</strong>.";
				}
			}catch(Exception $e){
				$error = "Errore nella generazione della pillola.";
			}
		}
	}
?><!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
	<meta name="robots" content="noindex, nofollow" />

	<title>Genera Pillola</title>
	<style>
		*, *:before, *:after{ margin: 0; padding: 0; box-sizing: border-box; }
		body { font-family: sans-serif; margin: 2em; }
		form { max-width: 400px; margin-top: 2em; }
		label { display: block; margin-top: 1em; }
		input[type="text"], input[type="date"], input[type="number"], input[type="password"], select {
			width: 100%; padding: 0.5em; font-size: 1em;
		}
		button { margin-top: 1.5em; padding: 0.6em 1.2em; font-size: 1em; }
		.message { margin-top: 1em; font-weight: bold; }
		.container { width: 100%; max-width: 300px; padding: 0 8px; margin: 0 auto; }
		.success { color: green; }
		.error { color: red; }
	</style>
</head>
<body>
	<div class="container">
		<h1>Genera una nuova Pillola</h1>

		<?php if ($success): ?>
			<div class="message success"><?= $success ?></div>
		<?php elseif ($error): ?>
			<div class="message error"><?= $error ?></div>
		<?php endif; ?>

		<form method="POST">
			<label for="date">Data (YYYY-MM-DD):</label>
			<input type="date" id="date" name="date" value="<?php echo esc_attr($default_date); ?>">

			<label for="category">Categoria:</label>
			<select id="category" name="category">
				<option value="">-- RANDOM --</option>
				<?php foreach( $all_categories AS $_category ): ?>
					<option value="<?php echo esc_attr($_category); ?>"><?php echo esc_html($_category); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="password">Password:</label>
			<input type="password" id="password" name="password" required>

			<button type="submit">Genera Pillola</button>
		</form>
	</div>
</body>
</html>
