<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if (!$user) {
    echo json_encode([
        'code'    => 401,
        'status'  => FALSE,
        'error'   => 'Invalid or expired token',
        'message' => 'Accesso negato',
    ]);
    exit();
}

// ── Configurazione ──────────────────────────────────────────────
define('VOUCHER_GOAL',  100);   // punti per sbloccare un voucher
define('VOUCHER_VALUE', 10);    // valore in EUR del voucher

// ── Punti utente ────────────────────────────────────────────────
$total_points = (int) $user['points_current_month'];

// Ciclo corrente: quanti punti mancano al prossimo voucher
$cycle          = $total_points % VOUCHER_GOAL;
$at_threshold   = ($total_points > 0 && $cycle === 0);
$display_points = $at_threshold ? 0 : $cycle;
$remaining      = $at_threshold ? VOUCHER_GOAL : max(VOUCHER_GOAL - $cycle, 0);
$can_redeem     = ($total_points >= VOUCHER_GOAL && !$at_threshold) || $at_threshold;

// ── Voucher già emessi per questo utente ────────────────────────
$vouchers = get_user_vouchers($user['id']);

// ── Risposta ────────────────────────────────────────────────────
$data = [
    'points'         => $display_points,
    'points_total'   => $total_points,
    'goal'           => VOUCHER_GOAL,
    'voucher_value'  => VOUCHER_VALUE,
    'can_redeem'     => $can_redeem,
    'remaining'      => $remaining,
    'rewardText'     => 'Voucher da ' . VOUCHER_VALUE . ' EUR da usare in farmacia',
    'rewardNote'     => '* Presentalo al banco, valido 30 giorni dall\'emissione.',
    'rewardImage'    => rtrim(site_url(), '/') . '/uploads/images/week-challenge.jpg',
    'vouchers'       => $vouchers,
    'points_legend'  => get_points_legend(),
];

echo json_encode([
    'code'    => 200,
    'status'  => TRUE,
    'message' => NULL,
    'data'    => $data,
]);


// ── Helper: recupera voucher utente ─────────────────────────────
function get_user_vouchers(int $user_id): array {
    global $conn; // adatta al tuo DB handler

    /*
     * Schema atteso:
     *   CREATE TABLE IF NOT EXISTS `user_vouchers` (
     *     `id`           INT AUTO_INCREMENT PRIMARY KEY,
     *     `user_id`      INT NOT NULL,
     *     `pharmacy_id`  INT NOT NULL,
     *     `code`         VARCHAR(32) NOT NULL UNIQUE,
     *     `status`       TINYINT NOT NULL DEFAULT 0,  -- 0=attivo 1=usato 2=scaduto
     *     `points_cost`  INT NOT NULL DEFAULT 100,
     *     `value_eur`    DECIMAL(5,2) NOT NULL DEFAULT 10.00,
     *     `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     *     `date_start`   DATETIME NULL,
     *     `date_end`     DATETIME NULL,
     *     INDEX(`user_id`),
     *     INDEX(`code`)
     *   ) ENGINE=InnoDB;
     */

    if (!function_exists('db_query')) {
        // fallback se non hai un wrapper: adatta a mysqli/PDO
        return [];
    }

    $rows = db_query(
        "SELECT id, code, status, value_eur, date_created, date_start, date_end
           FROM user_vouchers
          WHERE user_id = ?
          ORDER BY date_created DESC
          LIMIT 20",
        [$user_id]
    );

    if (!is_array($rows)) return [];

    return array_map(function ($v) {
        return [
            'id'           => (int) $v['id'],
            'code'         => $v['code'],
            'status'       => (int) $v['status'],
            'value_eur'    => (float) $v['value_eur'],
            'date_created' => $v['date_created'],
            'date_start'   => $v['date_start'],
            'date_end'     => $v['date_end'],
        ];
    }, $rows);
}