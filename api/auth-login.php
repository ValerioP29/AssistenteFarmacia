<?php

// ===== CORS (DEV) - deve stare PRIMA di qualsiasi output =====
$allowedOrigins = [
    'http://127.0.0.1:8000',
    'http://localhost:8000',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}

// Se usi cookie/sessions cross-origin, serve anche questo (non fa male averlo)
header('Access-Control-Allow-Credentials: true');

// Metodi e headers ammessi
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Risposta al preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ===== App bootstrap e headers API =====
require_once('_api_bootstrap.php');
setHeadersAPI();

// ===== Body =====
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'code'    => 400,
        'status'  => false,
        'error'   => 'Bad request',
        'message' => 'Payload JSON non valido',
    ]);
    exit;
}

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

$user = get_user_by_username($username);

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode([
        'code'    => 401,
        'status'  => false,
        'error'   => 'Invalid credentials',
        'message' => 'Dati di accesso non validi',
    ]);
    exit;
}

update_user($user['id'], [
    'last_access' => date('Y-m-d H:i:s'),
]);

// Access token (1h)
$access_payload = [
    'sub'      => $user['id'],
    'username' => $user['slug_name'],
    'exp'      => time() + getJWTtimelife(),
];

$access_token = getJwtEncoded($access_payload);

$refresh_token = generateRefreshToken();
insertAuthRefreshToken($user['id'], $refresh_token);

$pharma = get_fav_pharma_by_user_id($user['id']);
if ($pharma && isset($pharma['id'])) {
    $login_points = UserPointsModel::getPointsForAction('login_daily');
    UserPointsModel::addPointsOncePerDay($user['id'], $pharma['id'], $login_points, 'login_daily');
}

http_response_code(200);
echo json_encode([
    'code'          => 200,
    'status'        => true,
    'message'       => null,
    'user_id'       => $user['id'],
    'access_token'  => $access_token,
    'refresh_token' => $refresh_token,
]);