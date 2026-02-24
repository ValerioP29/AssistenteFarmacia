<?php
	session_start();

	$success = null;
	$error = null;
	$users = NULL;

	if( isset($_SESSION['poipoipoi']) && $_SESSION['poipoipoi'] == 654 ){
		require_once('../_api_bootstrap.php');
		$users = get_users();
		if( $users && is_array($users) ) $users = array_reverse($users);

		function tmp_get_last_chat( $user_id = NULL ){
			global $pdo;

			$stmt = $pdo->prepare("SELECT created_at FROM jta_chat_history WHERE user_id = ? LIMIT 1");
			$stmt->execute([$user_id]);
			$chat = $stmt->fetch(PDO::FETCH_ASSOC);

			return ($chat) ? $chat['created_at'] : FALSE;
		}

		function tmp_count_chat( $user_id = NULL ){
			global $pdo;

			$stmt = $pdo->prepare("SELECT id FROM jta_chat_history WHERE user_id = ?");
			$stmt->execute([$user_id]);
			$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

			return empty($chats) ? NULL : count($chats);
		}

		// echo 'query';
		// exit;
	}else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$psw = $_POST['password'] ?? FALSE;
		if ( $psw && $psw == 'jta25' ) {
			$_SESSION['poipoipoi'] = 654;
			header('location: ./utenti.php');
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
		form { max-width: 400px; margin: 2em auto 0; }
		label { display: block; margin-top: 1em; }
		input[type="text"], input[type="date"], input[type="number"], input[type="password"], select {
			width: 100%; padding: 0.5em; font-size: 1em;
		}
		button { margin-top: 1.5em; padding: 0.6em 1.2em; font-size: 1em; }
		.message { margin-top: 1em; font-weight: bold; }
		.container { width: 100%; max-width: 1200px; padding: 0 8px; margin: 0 auto; }
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

		<?php if( isset($users) && $users !== NULL ): ?>
			<p>Utenti registrati: <?php echo count($users); ?></p>
			<table>
				<thead>
					<tr>
						<th data-col="0" class="desc">ID <span class="sort-icon">▼</span></th>
						<th data-col="1">Username</th>
						<th data-col="2">Data registrazione</th>
						<th data-col="3">Ultimo accesso</th>
						<th data-col="4">Punti</th>
						<th data-col="5">Genere</th>
						<th data-col="6">Et&agrave;</th>
						<th data-col="7">Argomenti</th>
						<th data-col="8">No email o fullname</th>
						<th data-col="9">Ultima chat</th>
						<th data-col="10">Tot chat</th>
						<th data-col="11">Email</th>
						<th data-col="12">Fullname</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach( $users AS $_idx => $_user ): ?>
						<?php
							if( $_user['init_profiling'] != NULL ){
								$_user['init_profiling'] = json_decode($_user['init_profiling'], TRUE);
							}
							if( ! is_array($_user['init_profiling']) ) $_user['init_profiling'] = [];
						?>
						<tr>
							<td>#<?php echo esc_html($_user['id']); ?></td>
							<td><?php echo esc_html($_user['slug_name']); ?></td>
							<td><?php echo esc_html(date('d/m/Y H:i', strtotime($_user['created_at']))); ?></td>
							<td><?php echo esc_html(date('d/m/Y', strtotime($_user['last_access']))); ?></td>
							<td style="text-align:right;"><?php echo esc_html($_user['points_current_month']); ?></td>
							<td style="width:80px;"><?php if( isset($_user['init_profiling']['genere']) ){
								echo esc_html($_user['init_profiling']['genere']);
							} ?></td>
							<td style="width:80px;"><?php if( isset($_user['init_profiling']['fascia_eta']) ){
								echo esc_html($_user['init_profiling']['fascia_eta']);
							} ?></td>
							<td style="min-width:80px;"><?php if( isset($_user['init_profiling']['argomenti']) ){
								echo implode('<br>', array_map(function($el){ return '• '.esc_html($el); }, $_user['init_profiling']['argomenti']));
							} ?></td>
							<td style="width:50px;"><?php if( !$_user['email'] OR !$_user['name'] OR !$_user['surname'] ){
								echo 'Miss';
							} ?></td>
							<td><?php
								$_last_chat_date = tmp_get_last_chat($_user['id']);
								if( $_last_chat_date ) echo date('Y-m-d', strtotime($_last_chat_date));
							?></td>
							<td><?php echo tmp_count_chat($_user['id']) ?? ''; ?></td>
							<td><?php echo $_user['email'] ?? ''; ?></td>
							<td><?php
								if(isset($_user['name'])) echo $_user['name'];
								echo ' ';
								if(isset($_user['surname'])) echo $_user['surname'];
							?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<script>
				document.querySelectorAll("table th").forEach(th => {
					th.addEventListener("click", () => {
						const table = th.closest("table");
						const tbody = table.querySelector("tbody");
						const rows = Array.from(tbody.querySelectorAll("tr"));
						const colIndex = parseInt(th.dataset.col);
						const asc = !th.classList.contains("asc"); // inverti stato

						// reset icone
						table.querySelectorAll("th").forEach(x => {
							x.classList.remove("asc","desc");
							const icon = x.querySelector(".sort-icon");
							if (icon) icon.remove();
						});

						// set stato attuale
						th.classList.add(asc ? "asc" : "desc");
						const icon = document.createElement("span");
						icon.className = "sort-icon";
						icon.textContent = asc ? "▲" : "▼";
						th.appendChild(icon);

						rows.sort((a, b) => {
							let A = a.children[colIndex].innerText.trim();
							let B = b.children[colIndex].innerText.trim();

							if (colIndex == 0) { A = parseInt(A.replace("#","")); B = parseInt(B.replace("#","")); }
							if ([4, 10].includes(colIndex)) { A = parseInt(A); B = parseInt(B); }
							if ([2].includes(colIndex)) {
								const toDate = s => {
									const [d,m,yh] = s.split("/");
									const [y,h] = yh.split(" ");
									return new Date(`${y}-${m}-${d} ${h}`);
								};
								A = toDate(A); B = toDate(B);
							}
							if ([3, 9].includes(colIndex)) {
								const toDate = s => {
									if( s == '' ) return '';
									const [d,m,y] = s.split("/");
									return new Date(`${y}-${m}-${d}`);
								};
								A = toDate(A); B = toDate(B);
							}

							if (A < B) return asc ? -1 : 1;
							if (A > B) return asc ? 1 : -1;
							return 0;
						});

						rows.forEach(r => tbody.appendChild(r));
					});
				});
			</script>

		<?php else: ?>
			<form method="POST">
				<label for="password">Password:</label>
				<input type="password" id="password" name="password" required>
				<button type="submit">Accedi</button>
			</form>
		<?php endif; ?>
	</div>
</body>
</html>