<?php
/**
 * api/rag/core/OpenAIClient.php
 *
 * AGGIORNATO: ora usa openai_raw_call() dal client unificato (openai_client.php)
 * invece di implementare un secondo client cURL separato.
 *
 * Mantiene l'interfaccia pubblica identica (chat(), generateEmbedding())
 * così RAGEngine non richiede modifiche.
 *
 * Benefici:
 *   - Timeout + retry ereditati dal client unificato
 *   - Un solo punto di manutenzione per le chiamate OpenAI
 *   - Logging strutturato incluso
 */
class OpenAIClient
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        // Inietta la API key nell'env se viene passata dalla config RAG
        // (il client unificato legge da $_ENV)
        if (!empty($config['openai_api_key'])) {
            $_ENV['JTA_APP_OPENAI_API_KEY'] = $config['openai_api_key'];
        }
    }

    /**
     * Chiama l'API chat completions.
     * Stessa firma dell'originale: accetta array con 'model', 'messages', 'max_tokens'.
     *
     * @throws RuntimeException su errore non recuperabile
     */
    public function chat(array $data): array
    {
        // Override URL base se configurato diversamente (es. Azure OpenAI)
        $result = openai_raw_call('/chat/completions', $data);

        if (!$result['ok']) {
            throw new RuntimeException('Errore API OpenAI chat: ' . ($result['error'] ?? 'unknown'));
        }

        return $result['data'];
    }

    /**
     * Genera embedding per un testo.
     * Usa il modello configurato in settings.php (text-embedding-3-small).
     *
     * @return float[]
     * @throws RuntimeException su errore
     */
    public function generateEmbedding(string $text): array
    {
        $model = $this->config['embedding_model'] ?? OPENAI_MODEL_EMBEDDING;

        $payload = ['model' => $model, 'input' => $text];

        // Se il modello supporta dimensions (text-embedding-3-*), aggiungilo
        if (!empty($this->config['embedding_dimensions']) && str_contains($model, 'text-embedding-3')) {
            $payload['dimensions'] = (int)$this->config['embedding_dimensions'];
        }

        $result = openai_raw_call('/embeddings', $payload);

        if (!$result['ok'] || !isset($result['data']['data'][0]['embedding'])) {
            throw new RuntimeException('Errore API OpenAI embeddings: ' . ($result['error'] ?? 'unknown'));
        }

        return $result['data']['data'][0]['embedding'];
    }
}