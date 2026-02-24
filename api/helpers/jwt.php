<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function getJWTsecret(){
	return $_ENV['JTA_APP_JWT_SECRET'];
}

function getAuthorizationHeader() {
	if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
		return $_SERVER["HTTP_AUTHORIZATION"];
	} elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
		return $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
	} elseif (function_exists('apache_request_headers')) {
		$headers = apache_request_headers();
		if (isset($headers['Authorization'])) {
			return trim($headers['Authorization']);
		}
	}
	return null;
}

function setHeadersAPI() {
	header("Access-Control-Allow-Origin: *");
	header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
	header("Access-Control-Allow-Headers: Content-Type, Authorization");
	header('Content-Type: application/json');
	http_response_code(200);
}

function getAppJwtToken(){
	$auth = getAuthorizationHeader();

	if( is_null($auth) OR ! preg_match('/Bearer\s(\S+)/', $auth, $matches ) ){
		return FALSE;
	}

	return $matches[1];
}

function getJwtEncoded( $payload = NULL ){
	return JWT::encode($payload, getJWTsecret(), 'HS256');
}

function generateRefreshToken(){
	return bin2hex(random_bytes(32));
}

function getJwtDecoded( $token = NULL ){
	if( ! $token ) return FALSE;

	try{
		$decoded = JWT::decode($token, new Key(getJWTsecret(), 'HS256'));

		if( $decoded ){
			return $decoded;
		}
	}catch( Exception $e ){
		return FALSE;
	}

	return FALSE;
}

function protectFileWithJWT(){
	$token = getAppJwtToken();
	if( ! $token ){
		echo json_encode([
			'code'    => 401,
			'status'  => false,
			'error'   => 'Token required',
			'message' => 'Accesso negato',
		]);
		exit;
	}

	$decoded = getJwtDecoded( $token );
	if( ! $decoded ){
		echo json_encode([
			'code'    => 401,
			'status'  => false,
			'error'   => 'Invalid or expired token',
			'message' => 'Accesso negato',
		]);
		exit;
	}

	return $decoded;
}

function getJWTtimelife(){
	return 3600;
}

function getRefreshTokenTimelife(){
	return 30 * (60 * 60 * 24);
}