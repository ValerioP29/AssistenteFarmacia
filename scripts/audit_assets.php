<?php

declare(strict_types=1);

$apiBase = rtrim(getenv('API_BASE_URL') ?: 'http://localhost:8002', '/');
$jwt = getenv('TEST_JWT') ?: '';

if ($jwt === '') {
    fwrite(STDERR, "Missing TEST_JWT env var.\n");
    exit(1);
}

$headers = [
    'Authorization: Bearer ' . $jwt,
    'Accept: application/json',
];

function getJson(string $url, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'code' => 0, 'error' => $error, 'data' => null];
    }

    $json = json_decode((string) $body, true);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'error' => null, 'data' => $json];
}

function headStatus(string $url): int {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $errno === 0 ? $code : 0;
}

function originOf(string $url, string $apiBase): string {
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    $port = parse_url($url, PHP_URL_PORT);
    $apiHost = parse_url($apiBase, PHP_URL_HOST) ?: '';
    if ($host === $apiHost) return 'api';
    if ((string) $port === '8001') return 'panel';
    return 'external';
}

$report = [];
$add = function(string $assetType, ?string $url) use (&$report, $apiBase): void {
    $url = trim((string) $url);
    $status = $url === '' ? 0 : headStatus($url);
    $problems = [];
    if ($url === '') $problems[] = 'EMPTY_URL';
    if (str_contains($url, '/panel/uploads')) $problems[] = 'LOCAL_PANEL_PREFIX_ERROR';
    if ($url !== '' && $status >= 400) $problems[] = 'HTTP_' . $status;
    if ($url !== '' && $status === 0) $problems[] = 'UNREACHABLE';

    $report[] = [
        'asset_type' => $assetType,
        'url' => $url,
        'http_status' => $status,
        'origin' => $url === '' ? '-' : originOf($url, $apiBase),
        'problems' => $problems,
    ];
};

$pharmaResp = getJson($apiBase . '/pharma-get.php?id=1', $headers);
if (!$pharmaResp['ok']) {
    fwrite(STDERR, "pharma-get failed HTTP {$pharmaResp['code']}\n");
    exit(2);
}
$pharma = $pharmaResp['data']['data'] ?? [];
$add('pharmacy logo', $pharma['image_logo'] ?? '');
$add('avatar', $pharma['image_avatar'] ?? '');
$add('cover', $pharma['image_cover'] ?? '');
$add('bot', $pharma['image_bot'] ?? '');

$servicesResp = getJson($apiBase . '/services-list.php?featured=1', $headers);
if ($servicesResp['ok']) {
    $services = $servicesResp['data']['data'] ?? [];
    foreach ($services as $s) {
        if (!empty($s['is_featured'])) {
            $add('service cover', $s['cover_image']['src'] ?? '');
        }
    }
}

$eventsResp = getJson($apiBase . '/events-list.php?featured=1', $headers);
if ($eventsResp['ok']) {
    $events = $eventsResp['data']['data'] ?? [];
    foreach ($events as $e) {
        if (!empty($e['is_featured'])) {
            $add('event cover', $e['cover_image']['src'] ?? '');
        }
    }
}

$promosResp = getJson($apiBase . '/promos-list.php?ref=home&limit=100', $headers);
if ($promosResp['ok']) {
    $products = $promosResp['data']['data']['products'] ?? [];
    foreach ($products as $p) {
        if (!empty($p['is_featured'])) {
            $add('product image', $p['image']['src'] ?? '');
        }
    }
}

echo "asset_type\thttp_status\torigin\turl\tproblems\n";
foreach ($report as $row) {
    echo implode("\t", [
        $row['asset_type'],
        (string) $row['http_status'],
        $row['origin'],
        $row['url'],
        implode(',', $row['problems']),
    ]) . "\n";
}
