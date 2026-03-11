<?php
require_once('_api_bootstrap.php');

$key = $_ENV['JTA_APP_OPENAI_API_KEY'] ?? 'NON TROVATA';
echo 'Key caricata: ' . substr($key, 0, 12) . '...' . PHP_EOL;

$result = openai_raw_call('/chat/completions', [
    'model' => 'gpt-5-mini',
    'messages'   => [['role' => 'user', 'content' => 'Rispondi solo: OK']],
    'max_completion_tokens' => 5,
]);

echo $result['ok'] ? '✅ Funziona: ' . $result['data']['choices'][0]['message']['content'] : '❌ Errore: ' . $result['error'];