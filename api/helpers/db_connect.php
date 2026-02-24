<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

global $pdo;

$pdo = new PDO(
	'mysql:host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_NAME'].';charset=utf8mb4',
	$_ENV['DB_USER'],
	$_ENV['DB_PSW'],
	[
		PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
	]
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
