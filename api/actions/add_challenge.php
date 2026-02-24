<?php

$success = null;
$error = null;

require_once('../_api_bootstrap.php');
$pharma_id = isset($_GET['pharma_id']) && is_numeric($_GET['pharma_id']) ? (int) $_GET['pharma_id'] : 1;

if( isset($_GET['curr']) OR isset($_GET['next']) ){
	$to_do = FALSE;

	if( isset($_GET['curr']) ){
		$current = ChallengesModel::getCurrentWeek($pharma_id);
		if( ! $current ) $to_do = TRUE;

		$date = date('Y-m-d');
	}elseif( isset($_GET['next']) ){
		$next = ChallengesModel::getNextWeek($pharma_id);
		if( ! $next ) $to_do = TRUE;

		$dt = new DateTime();
		$dt->modify('next monday');
		$date = $dt->format('Y-m-d');
	}

	if( ! $to_do ){
		exit;
	}

	$dates_range = get_week_range($date);
	$result = ChallengesModel::insertFromAI($dates_range[0], 0, $pharma_id);

	if( ! $result ){
		echo 'Sfida non generata.';
	}else{
		$challenge = ChallengesModel::getById($result, $pharma_id);
		if( ! $challenge ){
			echo 'Sfida #'.$result.' non trovata.';
		}else{
			print("<pre>");
			print_r(($challenge));
			// print_r(normalize_challenge_data($challenge));
			print("</pre>");
		}
	}

	echo ($result) ? ('Sfida generata #'.$result.'.') : 'Sfida non generata.';
	exit;
}
