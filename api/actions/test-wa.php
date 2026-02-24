<?php
	session_start();

	$success = null;
	$error = null;
	$users = NULL;
	if( isset($_SESSION['poipoipoi']) && $_SESSION['poipoipoi'] == 654 ){
		require_once('../_api_bootstrap.php');

		$message = "TEST\nIgnora questo messaggio. Si tratta di un test automatico per verificare il servizio.";
		$to = '+393208018938'; // giacinto
		// $to = '+393288962012'; // valerio
		// $to = '+393202838555'; // alberto
		$pharma_id = 2;
		$resp = wa_send($message, $to, $pharma_id);

		echo 'L\'impostazione di invio WhatsApp è '. (get_option('wa_send_enabled', TRUE) ? 'abilitata' : 'disabilitata' ).'<br>';
		echo 'L\'esito del test di invio è: <br>';
		print("<pre>");print_r([$resp]);print("</pre>");

		unset($_SESSION['poipoipoi']);

		exit;
	}else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$psw = $_POST['password'] ?? FALSE;
		if ( $psw && $psw == 'jta25' ) {
			$_SESSION['poipoipoi'] = 654;
			header('Location: ' . $_SERVER['PHP_SELF']);
			exit;
		}
	}

?><!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
	<meta name="robots" content="noindex, nofollow" />

	<title>Assistente</title>
	<style>
		*, *:before, *:after{ margin: 0; padding: 0; box-sizing: border-box; }
		body { font-family: sans-serif; margin: 2em 0; }
		form { max-width: 800px; margin-top: 2em; }
		label { display: block; margin-top: 1em; }
		input[type="text"], input[type="date"], input[type="number"], input[type="password"], select {
			width: 100%; padding: 0.5em; font-size: 1em;
		}
		button { margin-top: 1.5em; padding: 0.6em 1.2em; font-size: 1em; }
		.message { margin-top: 1em; font-weight: bold; }
		.container { width: 100%; max-width: 400px; padding: 0 8px; margin: 0 auto; }
		.success { color: green; }
		.error { color: red; }
		ol, ul{
			margin-top: 24px;
			text-indent: 0;
			padding-left: 16px;
		}
		td,th{
			padding: 5px 5px;
		}
		table{
			margin-top: 24px;
		}
		table, th, td {
			border-collapse: collapse;
			border: 1px solid black;
		}

		th {
			cursor: pointer;
			user-select: none;
			position: relative;
			padding-right: 18px;
		}
		th .sort-icon {
			position: absolute;
			right: 4px;
			font-size: 0.8em;
		}
	</style>
</head>
<body>
	<div class="container">
		<?php if ($success): ?>
			<div class="message success"><?= $success ?></div>
		<?php elseif ($error): ?>
			<div class="message error"><?= $error ?></div>
		<?php endif; ?>

		<form method="POST">
			<label for="password">Password:</label>
			<input type="password" id="password" name="password" required>
			<button type="submit">Accedi</button>
		</form>
	</div>
</body>
</html>