<?php
/**
 * api/rag/config/settings.php
 *
 * Configurazione RAG — aggiornata con modelli e parametri moderni.
 *
 * MODIFICHE rispetto all'originale:
 *   - gpt_model: 'gpt-4-turbo-preview' → OPENAI_MODEL_DEFAULT (gpt-4.1)
 *   - embedding_model: 'text-embedding-ada-002' → OPENAI_MODEL_EMBEDDING (text-embedding-3-small)
 *   - chunk_size: 200 → 512 (ottimale per text-embedding-3-small, che supporta fino a 8191 token)
 *   - max_tokens: 8000 → 16000 (gpt-4.1 ha context window molto più ampia)
 *   - Aggiunto embedding_dimensions per text-embedding-3-small (1536 default, riducibile a 512)
 *
 * NOTA su text-embedding-3-small vs ada-002:
 *   - Stesso costo o inferiore
 *   - Performance MTEB benchmark: 62.3% vs 61.0%
 *   - Supporta Matryoshka: puoi ridurre le dimensioni a 512 senza perdita significativa
 *     (risparmio storage ~66% se usi dimensions=512)
 */

// Usa le costanti del client unificato se disponibile, fallback a stringhe
$gptModel       = defined('OPENAI_MODEL_DEFAULT')   ? OPENAI_MODEL_DEFAULT   : 'gpt-4o';
$embeddingModel = defined('OPENAI_MODEL_EMBEDDING')  ? OPENAI_MODEL_EMBEDDING : 'text-embedding-3-small';

return [
    // ── Modelli ───────────────────────────────────────────────────────────
    'gpt_model'           => $gptModel,
    'embedding_model'     => $embeddingModel,

    // Dimensioni embedding (solo per text-embedding-3-small/large)
    // 1536 = default completo | 512 = ridotto (risparmio storage, leggera perdita precisione)
    'embedding_dimensions' => 1536,

    // ── Configurazione RAG ────────────────────────────────────────────────
    'max_chunks'  => 5,    // Numero chunk da includere nel prompt
    'chunk_size'  => 512,  // Token per chunk (era 200, ora ottimale per text-embedding-3-small)
    'max_tokens'  => 16000, // Context window gpt-4.1 (era 8000 per gpt-4-turbo-preview)

    // ── Prompt base ───────────────────────────────────────────────────────
    'base_prompt' => "Sei un assistente esperto di farmacia. Rispondi alla domanda dell'utente "
        . "utilizzando le informazioni fornite nel contesto. "
        . "Se le informazioni nel contesto non sono sufficienti, dillo chiaramente. "
        . "Rispondi sempre in italiano in modo chiaro e conciso.",

    // ── URL API ────────────────────────────────────────────────────────────
    'openai_api_url' => 'https://api.openai.com/v1',

    // ── Percorsi file ──────────────────────────────────────────────────────
    'data_dir'       => 'data',
    'embeddings_dir' => 'data/embeddings',

    // ── Feature flags ──────────────────────────────────────────────────────
    'debug_mode'   => false,
    'cache_enabled'=> false,
    'cache_ttl'    => 3600,

    // ── Limiti sicurezza ───────────────────────────────────────────────────
    'max_file_size'               => 10 * 1024 * 1024,
    'max_documents'               => 100,
    'max_embeddings_per_document' => 1000,
];