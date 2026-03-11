<?php
/**
 * api/helpers/openai_client.php
 *
 * Client OpenAI UNIFICATO — sostituisce:
 *   - openai_call() / openai_call_simple_result() in bot_ai_helpers.php
 *   - OpenAIClient in rag/core/OpenAIClient.php
 *
 * Miglioramenti rispetto alla versione originale:
 *   - CURLOPT_TIMEOUT + CURLOPT_CONNECTTIMEOUT espliciti
 *   - Retry automatico con exponential backoff su 429 / 500 / 503
 *   - Modello configurabile (non hardcoded)
 *   - Logging strutturato (request_id, latency, tokens, fallback_reason)
 *   - Separazione netta tra errori transitori (retry) e permanenti (no retry)
 */

if (!defined('JTA')) { header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

// ─────────────────────────────────────────────────────────────────────────────
// COSTANTI MODELLI
// Aggiorna qui quando OpenAI rilascia nuove versioni.
// ─────────────────────────────────────────────────────────────────────────────

define('OPENAI_MODEL_DEFAULT',   'gpt-5.1');           // buon bilanciamento qualità/costo
define('OPENAI_MODEL_MINI',      'gpt-5-mini');        // per task semplici e test
define('OPENAI_MODEL_EMBEDDING', 'text-embedding-3-small');
define('OPENAI_MODEL_VISION',    'gpt-5.1');           // gestisce anche immagini

// Timeout in secondi
define('OPENAI_CONNECT_TIMEOUT', 5);   // connessione TCP
define('OPENAI_REQUEST_TIMEOUT', 30);  // risposta completa

// Retry
define('OPENAI_MAX_RETRIES', 2);
define('OPENAI_RETRY_BASE_MS', 500);   // backoff base in ms: 500, 1000

// ─────────────────────────────────────────────────────────────────────────────
// FUNZIONE BASE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Esegue una chiamata cURL verso OpenAI con timeout, retry e logging strutturato.
 *
 * @param string $endpoint  Percorso relativo es. '/chat/completions' o '/embeddings'
 * @param array  $payload   Body JSON da inviare
 * @param array  $options   Opzioni: ['timeout' => int, 'connect_timeout' => int, 'max_retries' => int]
 * @return array            ['ok' => bool, 'data' => array|null, 'http_code' => int, 'error' => string|null, 'latency_ms' => int]
 */
function openai_raw_call(string $endpoint, array $payload, array $options = []): array
{
    $apiKey  = $_ENV['JTA_APP_OPENAI_API_KEY'] ?? '';
    $baseUrl = 'https://api.openai.com/v1';

    $timeout         = (int)($options['timeout']         ?? OPENAI_REQUEST_TIMEOUT);
    $connectTimeout  = (int)($options['connect_timeout'] ?? OPENAI_CONNECT_TIMEOUT);
    $maxRetries      = (int)($options['max_retries']     ?? OPENAI_MAX_RETRIES);

    $requestId = bin2hex(random_bytes(6)); // per correlazione nei log
    $attempt   = 0;
    $lastError = null;

    while ($attempt <= $maxRetries) {
        $t0 = microtime(true);

        $ch = curl_init($baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        $latencyMs   = (int)round((microtime(true) - $t0) * 1000);
        curl_close($ch);

        // ── Errore cURL (rete / timeout) ──────────────────────────────────
        if ($curlError) {
            $lastError = 'cURL error: ' . $curlError;
            openai_log('warn', 'curl_error', [
                'request_id' => $requestId,
                'endpoint'   => $endpoint,
                'attempt'    => $attempt + 1,
                'error'      => $curlError,
                'latency_ms' => $latencyMs,
            ]);
            $attempt++;
            if ($attempt <= $maxRetries) _openai_backoff($attempt);
            continue;
        }

        $data = json_decode($rawResponse, true);

        // ── Errori transitori → retry ──────────────────────────────────────
        if (in_array($httpCode, [429, 500, 503], true)) {
            $lastError = "HTTP {$httpCode}";
            openai_log('warn', 'transient_error', [
                'request_id' => $requestId,
                'endpoint'   => $endpoint,
                'http_code'  => $httpCode,
                'attempt'    => $attempt + 1,
                'latency_ms' => $latencyMs,
            ]);
            $attempt++;
            if ($attempt <= $maxRetries) {
                // Rispetta Retry-After se presente (rate limit)
                $retryAfter = (int)($data['error']['message'] ?? 0);
                if ($retryAfter > 0) {
                    sleep(min($retryAfter, 10));
                } else {
                    _openai_backoff($attempt);
                }
            }
            continue;
        }

        // ── Risposta non-200 non transitoria → no retry ───────────────────
        if ($httpCode !== 200) {
            openai_log('error', 'api_error', [
                'request_id' => $requestId,
                'endpoint'   => $endpoint,
                'http_code'  => $httpCode,
                'response'   => substr($rawResponse, 0, 300),
                'latency_ms' => $latencyMs,
            ]);
            return ['ok' => false, 'data' => $data, 'http_code' => $httpCode, 'error' => "HTTP {$httpCode}", 'latency_ms' => $latencyMs];
        }

        // ── Successo ──────────────────────────────────────────────────────
        openai_log('info', 'ok', [
            'request_id'  => $requestId,
            'endpoint'    => $endpoint,
            'model'       => $data['model'] ?? ($payload['model'] ?? '?'),
            'tokens'      => $data['usage']['total_tokens'] ?? null,
            'latency_ms'  => $latencyMs,
            'attempts'    => $attempt + 1,
        ]);

        return ['ok' => true, 'data' => $data, 'http_code' => 200, 'error' => null, 'latency_ms' => $latencyMs];
    }

    // Tutti i retry esauriti
    openai_log('error', 'all_retries_failed', [
        'request_id' => $requestId,
        'endpoint'   => $endpoint,
        'last_error' => $lastError,
    ]);
    return ['ok' => false, 'data' => null, 'http_code' => 0, 'error' => 'All retries failed: ' . $lastError, 'latency_ms' => 0];
}

// ─────────────────────────────────────────────────────────────────────────────
// WRAPPER CHAT COMPLETIONS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Chiamata chat completions.
 * Drop-in replacement per openai_call() e openai_call_simple_result().
 *
 * @param string       $promptUser    Messaggio utente
 * @param string       $promptSystem  Messaggio sistema
 * @param array        $argsExtra     Override: model, max_tokens, temperature, image_data,
 *                                    use_history, user_id, pharma_id, session_id
 * @return array|false Array con ['risposta_html' => ..., 'raw' => ...] oppure false
 */
function openai_chat(string $promptUser, string $promptSystem, array $argsExtra = [])
{
    $apiKey = $_ENV['JTA_APP_OPENAI_API_KEY'] ?? '';

    // ── Storico conversazione ─────────────────────────────────────────────
    $useHistory = (bool)($argsExtra['use_history'] ?? false);
    $userId     = $argsExtra['user_id']    ?? null;
    $pharmaId   = $argsExtra['pharma_id']  ?? null;
    $sessionId  = $argsExtra['session_id'] ?? null;

    $messages = [];

    if ($useHistory && $userId && $sessionId) {
        $messages = get_chat_history_for_openai($userId, $sessionId, 20);
    }

    array_unshift($messages, ['role' => 'system', 'content' => $promptSystem]);

    // ── Gestione immagine ────────────────────────────────────────────────
    $imageData       = $argsExtra['image_data'] ?? null;
    $hasImage        = !empty($imageData);
    $imageSaveData   = $imageData;

    if ($hasImage) {
        if (strpos($imageData, 'data:') !== 0) {
            $imageData = 'data:image/jpeg;base64,' . $imageData;
        }
        $messages[] = [
            'role'    => 'user',
            'content' => [
                ['type' => 'text',      'text'      => $promptUser],
                ['type' => 'image_url', 'image_url' => ['url' => $imageData]],
            ],
        ];
    } else {
        $messages[] = ['role' => 'user', 'content' => $promptUser];
    }

    // ── Payload ───────────────────────────────────────────────────────────
    $model = $argsExtra['model'] ?? ($hasImage ? OPENAI_MODEL_VISION : OPENAI_MODEL_DEFAULT);

    // Rimuovi chiavi di gestione interna prima del merge
    $skipKeys = ['use_history', 'user_id', 'pharma_id', 'session_id', 'image_data', 'messages'];
    $extra    = array_diff_key($argsExtra, array_flip($skipKeys));

    $payload = array_merge([
        'model'       => $model,
        'messages'    => $messages,
        'max_completion_tokens' => 1200,
        'temperature' => 0.2,
    ], $extra);

    // ── Chiamata ──────────────────────────────────────────────────────────
    $result = openai_raw_call('/chat/completions', $payload);

    if (!$result['ok'] || !isset($result['data']['choices'][0]['message']['content'])) {
        return false;
    }

    $rawContent = $result['data']['choices'][0]['message']['content'];
    $tokensUsed = $result['data']['usage']['total_tokens'] ?? 0;

    // ── Salva storico ─────────────────────────────────────────────────────
    if ($useHistory && $userId && $pharmaId && $sessionId) {
        $contentType = $hasImage ? 'mixed' : 'text';
        save_chat_message($userId, $pharmaId, $sessionId, 'user', $promptUser, $contentType, $imageSaveData);
        save_chat_message($userId, $pharmaId, $sessionId, 'assistant', $rawContent, 'text', null, $tokensUsed, $model);
    }

    // ── Parsing risposta ──────────────────────────────────────────────────
    // Prova JSON, fallback stringa
    $clean = _openai_clean_content($rawContent);
    $decoded = json_decode($clean, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    return ['risposta_html' => $rawContent];
}

/**
 * Chiamata semplificata — restituisce solo il testo del content (per pill, quiz, challenge).
 * Alias per mantenere compatibilità con openai_call().
 *
 * @return array  ['code' => 200, 'status' => true, 'message' => string, 'data' => [...]]
 *                oppure ['code' => 500, 'status' => false, ...]
 */
function openai_call_v2(string $promptUser, string $promptSystem, array $argsExtra = []): array
{
    $model = $argsExtra['model'] ?? OPENAI_MODEL_DEFAULT;
    unset($argsExtra['messages']);

    $payload = array_merge([
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $promptSystem],
            ['role' => 'user',   'content' => $promptUser],
        ],
        'max_tokens'  => 800,
        'temperature' => 0.2,
    ], $argsExtra);

    $result = openai_raw_call('/chat/completions', $payload);

    if (!$result['ok'] || !isset($result['data']['choices'][0]['message']['content'])) {
        return [
            'code'    => 500,
            'status'  => false,
            'error'   => $result['error'] ?? 'No content',
            'message' => $result['error'] ?? 'Risposta non pervenuta.',
        ];
    }

    return [
        'code'    => 200,
        'status'  => true,
        'message' => $result['data']['choices'][0]['message']['content'],
        'data'    => ['gptData' => $result['data']],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// WRAPPER EMBEDDINGS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Genera embedding per un testo.
 * Sostituisce OpenAIClient::generateEmbedding() nel RAG.
 *
 * @param string $text
 * @param string $model  Default: OPENAI_MODEL_EMBEDDING (text-embedding-3-small)
 * @return float[]|null  Vettore embedding oppure null se errore
 */
function openai_embedding(string $text, string $model = OPENAI_MODEL_EMBEDDING): ?array
{
    $result = openai_raw_call('/embeddings', [
        'model' => $model,
        'input' => $text,
    ]);

    if (!$result['ok'] || !isset($result['data']['data'][0]['embedding'])) {
        return null;
    }

    return $result['data']['data'][0]['embedding'];
}

// ─────────────────────────────────────────────────────────────────────────────
// UTILITY INTERNE
// ─────────────────────────────────────────────────────────────────────────────

/** Exponential backoff tra retry */
function _openai_backoff(int $attempt): void
{
    $ms = OPENAI_RETRY_BASE_MS * (2 ** ($attempt - 1)); // 500ms, 1000ms
    $ms = min($ms, 5000); // cap a 5 secondi
    usleep($ms * 1000);
}

/** Pulisce il content da markdown code fences e caratteri di controllo */
function _openai_clean_content(string $content): string
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
    $content = preg_replace('/\s*```\s*$/m', '', $content);
    $content = preg_replace('/[\x00-\x1F\x7F]/', '', $content);
    return trim($content);
}

/**
 * Logging strutturato per OpenAI.
 * Scrive su error_log solo se non in produzione o se livello >= 'error'.
 * Formato: [openai][LEVEL][event] JSON
 */
function openai_log(string $level, string $event, array $context = []): void
{
    // In produzione logga solo warning ed errori
    $isProd = !is_localhost();
    if ($isProd && $level === 'info') return;

    error_log(sprintf(
        '[openai][%s][%s] %s',
        strtoupper($level),
        $event,
        json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ));
}