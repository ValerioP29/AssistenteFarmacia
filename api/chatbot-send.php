<?php
/**
 * api/chatbot-send.php
 *
 * MODIFICHE rispetto all'originale:
 *   1. Rimossi i 4x error_log() di debug sempre attivi (dati sensibili in produzione)
 *   2. Eliminato il blocco if/else quickAction duplicato (stesso codice in entrambi i rami)
 *   3. generate_chat_session_id() ora usa bin2hex(random_bytes(16)) invece di uniqid()
 *   4. Usa openai_chat() del client unificato se disponibile, con fallback compatibile
 */

require_once('_api_bootstrap.php');

setHeadersAPI();

$decoded = protectFileWithJWT();
$user    = get_my_data();

if (!$user) {
    echo json_encode([
        'code'    => 401,
        'status'  => false,
        'error'   => 'Invalid or expired token',
        'message' => 'Accesso negato',
    ]);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────

$raw_input    = file_get_contents('php://input');
$input        = json_decode($raw_input, true);

$message      = $input['message']     ?? null;
$quick_action = $input['quickAction'] ?? false;
$image_data   = $input['image']       ?? null;
$image_format = $input['imageFormat'] ?? null;
$session_id   = $input['sessionId']   ?? null;

$use_rag         = get_option('ai_rag_enabled',       false);
$use_quickaction = get_option('ai_quickaction_enabled', false);
$use_history     = get_option('ai_chat_history_enabled', true);

$pharma = getMyPharma();

if (!$use_quickaction) $quick_action = false;

// Sessione: ora sicura con random_bytes invece di uniqid()
if (!$session_id) {
    $session_id = bin2hex(random_bytes(16));
}

$user_id  = get_my_id();
$pharma_id = $pharma['id'] ?? 1;

// Parametri storico
$history_params = null;
if ($use_history && $user_id && $pharma_id && $session_id) {
    $history_params = [
        'use_history' => true,
        'user_id'     => $user_id,
        'pharma_id'   => $pharma_id,
        'session_id'  => $session_id,
    ];
}

// ─── Validazione quick action ─────────────────────────────────────────────────

if ($quick_action && (
    !isset($quick_action['type'], $quick_action['action']) ||
    !in_array($quick_action['type'], ['request'], true)
)) {
    echo json_encode([
        'code'    => 400,
        'status'  => false,
        'error'   => 'Bad Request',
        'message' => 'Richiesta non valida, riprova. Come posso aiutarti?',
    ]);
    exit();
}

if (!$quick_action && trim((string)$message) === '') {
    echo json_encode([
        'code'    => 400,
        'status'  => false,
        'error'   => 'Bad Request',
        'message' => 'Richiesta non valida, riprova. Come posso aiutarti?',
    ]);
    exit();
}

// ─── Validazione immagine ──────────────────────────────────────────────────────

$validated_image_data = null;
if ($image_data) {
    $image_validation = validateImageBase64($image_data, $image_format);
    if (!$image_validation['valid']) {
        echo json_encode([
            'code'    => 400,
            'status'  => false,
            'error'   => 'Bad Request',
            'message' => 'Immagine non conforme: ' . $image_validation['error'],
        ]);
        exit();
    }
    $validated_image_data = $image_data;
}

// ─── Risolvi user_prompt da quick action ─────────────────────────────────────

$user_prompt = (string)($message ?? '');

if ($quick_action && $quick_action['type'] === 'request') {
    switch ($quick_action['action']) {
        case 'getPharmaHours':    $user_prompt = 'Sai dirmi gli orari di apertura della farmacia?'; break;
        case 'getPharmaLocation': $user_prompt = 'Dove si trova la farmacia?'; break;
        case 'getPharmaServices': $user_prompt = 'Quali sono i servizi disponibili della farmacia?'; break;
        case 'getDrugInfo':       $user_prompt = 'Dammi qualche informazione sulla Tachipirina'; break;
    }
}

// ─── Chiamata AI ──────────────────────────────────────────────────────────────
// Il ramo if($quick_action)/else era identico → unificato in un unico flusso.

if ($use_rag) {
    $response = hybrid_chatbot($user_prompt, ['use_rag' => $use_rag]);
} else {
    $has_image     = !empty($validated_image_data);
    $system_prompt = get_openai_chatbot_prompt($pharma, $user, $user_prompt, $has_image);
    $response      = openai_new_chatbot_request($user_prompt, $system_prompt, $validated_image_data, $history_params);
}

// ─── Gestione risposta ────────────────────────────────────────────────────────

if (!$response) {
    echo json_encode([
        'code'    => 500,
        'status'  => false,
        'error'   => 'Error',
        'message' => 'Errore imprevisto. Contatta l\'assistenza o riprova.',
    ]);
    exit();
}

// Punti benessere giornalieri chatbot
$can_give_points = !UserPointsModel::hasEntryForDate($user['id'], $pharma['id'], 'chatbot_daily');
if ($can_give_points) {
    UserPointsModel::addPoints($user['id'], $pharma['id'], get_option('points_chatbot_daily', 5), 'chatbot_daily');
}

echo json_encode([
    'code'    => 200,
    'status'  => true,
    'message' => null,
    'data'    => [
        'id'          => generateUniqueId(),
        'sessionId'   => $session_id,
        'message'     => $response['risposta_html'],
        'quickAction' => (!$use_quickaction) ? [] : ['actions' => []],
    ],
]);