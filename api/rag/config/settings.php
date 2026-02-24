<?php
/**
 * Configurazione centralizzata per RAG Engine
 * 
 * Questo file contiene tutte le impostazioni di default.
 * Può essere sovrascritto passando parametri al costruttore di RAGEngine.
 */
return [
    // Chiave API OpenAI (sovrascrivibile)
    
    // Modelli OpenAI
    'gpt_model' => 'gpt-4-turbo-preview',
    'embedding_model' => 'text-embedding-ada-002',
    
    // Configurazione RAG
    'max_chunks' => 5, // Numero massimo di chunk da includere nel prompt
    'chunk_size' => 200, // Dimensione chunk in token
    'max_tokens' => 8000, // Limite token per il prompt
    
    // Prompt base (personalizzabile)
    'base_prompt' => "Sei un assistente esperto. Rispondi alla domanda dell'utente utilizzando le informazioni fornite nel contesto. Se le informazioni nel contesto non sono sufficienti per rispondere completamente, dillo chiaramente. Rispondi sempre in italiano in modo chiaro e conciso.",
    
    // URL API OpenAI
    'openai_api_url' => 'https://api.openai.com/v1',
    
    // Percorsi file (relativi alla directory del progetto)
    'data_dir' => 'data',
    'embeddings_dir' => 'data/embeddings',
    
    // Configurazioni aggiuntive
    'debug_mode' => false,
    'cache_enabled' => false,
    'cache_ttl' => 3600,
    
    // Limiti di sicurezza
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'max_documents' => 100,
    'max_embeddings_per_document' => 1000
]; 