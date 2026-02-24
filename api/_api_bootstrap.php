<?php

define('JTA', TRUE);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Rome');

require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once('helpers/db_connect.php');
require_once('helpers/models.php');
require_once('helpers/jwt.php');
require_once('helpers/wa_helpers.php');
require_once('helpers/bot_ai_helpers.php');
require_once('helpers/file_helpers.php');
require_once('helpers/rag_helpers.php');
require_once('helpers/misc_helpers.php');
require_once('helpers/_model_chat_history.php');
require_once('helpers/chat_image_helpers.php');


function site_url(){
	// $url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "";
	// $url = str_replace( '\\', '/', $url );
	// return $url;

	// Determina lo schema
	$scheme = 'http';
	if (
		(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
		(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
	) {
		$scheme = 'https';
	}

	// Host
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

	// Path base (senza lo script)
	$path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

	return $scheme . '://' . $host . ($path ? $path : '');
}

function site_path(){
	$url = realpath(__DIR__);
	$url = str_replace('\\', '/', $url);
	return $url;
}

function is_localhost() {
	return in_array($_SERVER['REMOTE_ADDR'], ['localhost', '127.0.0.1', '0.0.0.0', '::1']);
}

function get_my_id(){
	$token = getAppJwtToken();
	$decoded = getJwtDecoded($token);
	return $decoded ? $decoded->sub : FALSE;
}

function get_my_data(){
	$user_id = get_my_id();
	if( ! $user_id ) return FALSE;
	$user = get_user_by_id($user_id);
	if( ! $user ) return FALSE;
	return $user;
}

function get_my_wa(){
	$user = get_my_data();
	if( ! $user ) return FALSE;
	return $user['phone_number'] ?? FALSE;
}

function get_my_profiling(){
	$user = get_my_data();
	if( ! $user ) return [];

	$init_profiling = NULL;
	if (!empty($user['init_profiling'])) {
		try {
			$init_profiling = json_decode($user['init_profiling'], TRUE);
		} catch (Exception $e) {
			$init_profiling = $user['init_profiling'];
		}
	}

	$init_profiling = empty($init_profiling) ? NULL : [
		'genere'     => $init_profiling['genere'],
		'fascia_eta' => $init_profiling['fascia_eta'],
		'lifestyle'  => $init_profiling['lifestyle'],
		'argomenti'  => $init_profiling['argomenti'],
	];

	return $init_profiling;
}

function get_my_profiling_args(){
	$profiling = get_my_profiling();
	if( ! $profiling ) return [];
	return $profiling['argomenti'];
}

function generateUniqueId() {
	$letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	return $letters[random_int(0, strlen($letters) - 1)] . substr(str_shuffle(str_repeat($chars, 8)), 0, 7);
}
