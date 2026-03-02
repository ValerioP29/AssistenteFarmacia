<?php

// Preflight CORS — deve stare PRIMA di qualsiasi require
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    http_response_code(204);
    exit();
}
require_once('_api_bootstrap.php');
require_once(__DIR__ . '/helpers/_model_voucher.php');setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if (!$user) {
    echo json_encode(['code' => 401, 'status' => FALSE, 'error' => 'Unauthorized', 'message' => 'Accesso negato.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['code' => 405, 'status' => FALSE, 'message' => 'Metodo non consentito.']);
    exit();
}

define('VOUCHER_GOAL', 100);
define('VOUCHER_VALUE_EUR', 10.00);
define('VOUCHER_VALIDITY_DAYS', 30);

$user_id     = (int) get_my_id();
$pharma      = getMyPharma();
$pharmacy_id = (int) ($pharma['id'] ?? 1);
$total_pts   = (int) $user['points_current_month'];

if ($total_pts < VOUCHER_GOAL) {
    echo json_encode([
        'code' => 422, 'status' => FALSE, 'error' => 'not_enough_points',
        'message' => 'Non hai ancora raggiunto i ' . VOUCHER_GOAL . ' punti necessari.',
        'data' => ['points' => $total_pts, 'goal' => VOUCHER_GOAL],
    ]);
    exit();
}

$cycle_floor = (int)(floor($total_pts / VOUCHER_GOAL) * VOUCHER_GOAL);
$cycle_ceil  = $cycle_floor + VOUCHER_GOAL;

$existing = VoucherModel::getActiveForCycle($user_id, $cycle_floor, $cycle_ceil);
if ($existing) {
    echo json_encode(['code' => 200, 'status' => TRUE, 'message' => 'Voucher già emesso per questo ciclo.', 'data' => ['voucher' => $existing]]);
    exit();
}

$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$rnd = '';
for ($i = 0; $i < 8; $i++) $rnd .= $chars[random_int(0, strlen($chars) - 1)];
$code = 'FAR-' . $rnd;

$date_start = date('Y-m-d H:i:s');
$date_end   = date('Y-m-d H:i:s', strtotime('+' . VOUCHER_VALIDITY_DAYS . ' days'));

$voucher_id = VoucherModel::insert([
    'user_id'              => $user_id,
    'pharmacy_id'          => $pharmacy_id,
    'code'                 => $code,
    'status'               => 0,
    'points_cost'          => VOUCHER_GOAL,
    'points_at_generation' => $total_pts,
    'value_eur'            => VOUCHER_VALUE_EUR,
    'date_start'           => $date_start,
    'date_end'             => $date_end,
]);

if (!$voucher_id) {
    echo json_encode(['code' => 500, 'status' => FALSE, 'error' => 'db_error', 'message' => 'Errore durante la creazione del voucher. Riprova.']);
    exit();
}

VoucherModel::deductPoints($user_id, VOUCHER_GOAL);

echo json_encode([
    'code' => 200, 'status' => TRUE, 'message' => 'Voucher generato con successo!',
    'data' => ['voucher' => ['id' => $voucher_id, 'code' => $code, 'value_eur' => VOUCHER_VALUE_EUR, 'status' => 0, 'date_start' => $date_start, 'date_end' => $date_end]],
]);