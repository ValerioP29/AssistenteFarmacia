<?php
	$success = null;
	$error = null;

	require_once('../_api_bootstrap.php');
	$pharma_id = isset($_GET['pharma_id']) && is_numeric($_GET['pharma_id']) ? (int) $_GET['pharma_id'] : 1;

	$default_date = '';
	$last_quiz = QuizzesModel::getLastAvailable($pharma_id, FALSE);
	if( $last_quiz ){
		$default_date = new DateTime($last_quiz['date']);
		$default_date->modify('+1 day');
		$default_date = $default_date->format('Y-m-d');
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$date = $_POST['date'] ?? '';
		$points = (int) ($_POST['points'] ?? '');
		$topic = trim($_POST['topic'] ?? '');
		$psw = $_POST['password'] ?? FALSE;

		if ( ! $psw ) {
			$error = "Tutti i campi sono obbligatori.";
		}else if ( $psw != 'jta25' ) {
			$error = "Il campo password è errato.";
		} else {
			$quiz_id = QuizzesModel::insertFromAI($date, $points, $topic, $pharma_id);

			if (! $quiz_id) {
				$error = "Errore nella generazione del quiz.";
			} else {
				$success = "✅ Quiz inserito per il giorno <strong>$date</strong>.";
			}
		}
	}elseif( isset($_GET['today']) ){
		$today_quiz = QuizzesModel::getToday($pharma_id);
		if( ! $today_quiz ){
			$quiz_id = QuizzesModel::insertFromAI(date('Y-m-d'), 0, '', $pharma_id);
			if( $quiz_id ){ echo 'Generazione effettuata #'.$quiz_id.'.'; }
			else{ echo 'Generazione fallita.'; }
			exit;
		}
		echo 'Nope #46';
		exit;
	}
?><!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
	<meta name="robots" content="noindex, nofollow" />

	<title>Genera Quiz</title>
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
		<h1>Genera un nuovo Quiz</h1>

		<?php if ($success): ?>
			<div class="message success"><?= $success ?></div>
		<?php elseif ($error): ?>
			<div class="message error"><?= $error ?></div>
		<?php endif; ?>

		<form method="POST">
			<label for="date">Data (YYYY-MM-DD):</label>
			<input type="date" id="date" name="date" value="<?php echo esc_attr($default_date); ?>">

			<label for="points">Punti:</label>
			<input type="number" id="points" name="points" min="1">

			<label for="topic">Tema:</label>
			<input type="text" id="topic" name="topic">
			<p>Es. "Pelle e sole", "Stress estivo", "Digestione in vacanza"</p>

			<label for="password">Password:</label>
			<input type="password" id="password" name="password" required>

			<button type="submit">Genera Quiz</button>
		</form>
	</div>
</body>
</html>
