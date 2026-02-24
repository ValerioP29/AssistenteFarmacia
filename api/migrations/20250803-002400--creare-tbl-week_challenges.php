<?php
require_once('../_api_bootstrap.php');
global $pdo;

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS jta_week_challenges (
	id INT AUTO_INCREMENT PRIMARY KEY,
	date_start DATE NOT NULL UNIQUE,
	points SMALLINT NOT NULL DEFAULT '10',
	title VARCHAR(255) NOT NULL,
	description TEXT,
	instructions JSON,
	reward TEXT,
	icon VARCHAR(50),
	metadata JSON NULL,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `jta_week_challenges` (`id`, `date_start`, `title`, `description`, `instructions`, `reward`, `icon`, `metadata`, `created_at`, `updated_at`) VALUES (NULL, '2025-08-04', 'Idratazione Consapevole 💧', 'A Terracina, sotto il sole di fine luglio, il caldo si fa sentire! Idratarsi ogni giorno è fondamentale per il benessere di tutto il corpo: migliora l’energia, la digestione, la pelle e la concentrazione. Questa settimana, impara a bere nel modo giusto con una semplice ma potente abitudine quotidiana.', '[\"Appena sveglio/a, bevi un bicchiere d’acqua a temperatura ambiente.\", \"Porta sempre con te una bottiglia e bevi piccoli sorsi durante la giornata, senza aspettare di avere sete.\", \"Prima di andare a dormire, chiediti: oggi ho bevuto almeno 1,5 litri d’acqua? Se sì, segna la giornata come completata!\"]', 'Ogni giornata completata ti fa guadagnare 10 punti benessere. Completando tutti e 7 i giorni ricevi 70 punti e una pelle (e mente) più fresca [ \"Appena sveglio/a, bevi un bicchiere d’acqua a temperatura ambiente.\", \"Porta sempre con te una bottiglia e bevi piccoli sorsi durante la giornata, senza aspettare di avere sete.\", \"Prima di andare a dormire, chiediti: oggi ho bevuto almeno 1,5 litri d’acqua? Se sì, segna la giornata come completata!\" ]e luminosa!', 'fa-glass-water', '{\"icon\": \"fa-glass-water\", \"title\": \"Idratazione Consapevole 💧\", \"reward\": \"Ogni giornata completata ti fa guadagnare 10 punti benessere. Completando tutti e 7 i giorni ricevi 70 punti e una pelle (e mente) più fresca e luminosa!\", \"description\": \"A Terracina, sotto il sole di fine luglio, il caldo si fa sentire! Idratarsi ogni giorno è fondamentale per il benessere di tutto il corpo: migliora l’energia, la digestione, la pelle e la concentrazione. Questa settimana, impara a bere nel modo giusto con una semplice ma potente abitudine quotidiana.\", \"instructions\": [\"Appena sveglio/a, bevi un bicchiere d’acqua a temperatura ambiente.\", \"Porta sempre con te una bottiglia e bevi piccoli sorsi durante la giornata, senza aspettare di avere sete.\", \"Prima di andare a dormire, chiediti: oggi ho bevuto almeno 1,5 litri d’acqua? Se sì, segna la giornata come completata!\"]}', '2025-08-04 00:00:00', '2025-08-04 00:00:00');

CREATE TABLE IF NOT EXISTS jta_week_challenge_progress (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	challenge_id INT NOT NULL,
	d1 TINYINT(1) DEFAULT 0,
	d2 TINYINT(1) DEFAULT 0,
	d3 TINYINT(1) DEFAULT 0,
	d4 TINYINT(1) DEFAULT 0,
	d5 TINYINT(1) DEFAULT 0,
	d6 TINYINT(1) DEFAULT 0,
	d7 TINYINT(1) DEFAULT 0,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	UNIQUE KEY uq_user_challenge (user_id, challenge_id),
	FOREIGN KEY (challenge_id) REFERENCES jta_week_challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

$pdo->exec($sql);
echo "Migrazione completata con successo.";
