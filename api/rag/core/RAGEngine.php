<?php
/**
 * RAGEngine - Motore principale per Retrieval-Augmented Generation
 * 
 * Questa classe incapsula tutta la logica RAG e può essere facilmente
 * integrata in altri progetti PHP tramite inclusione diretta.
 */
class RAGEngine {
    private $config;
    private $openaiClient;
    
    public function __construct($config = []) {
        $this->config = $this->mergeConfig($config);
        $this->openaiClient = new OpenAIClient($this->config);
    }
    
    /**
     * Esegue una query RAG
     * 
     * @param string $question La domanda dell'utente
     * @param array $options Opzioni aggiuntive (use_rag, debug, etc.)
     * @return array Risposta con dati e metadati
     */
    public function query($question, $options = []) {
        $defaultOptions = [
            'use_rag' => true,
            'debug' => false,
            'max_chunks' => $this->config['max_chunks'],
            'max_tokens' => $this->config['max_tokens']
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        if (!$options['use_rag']) {
            // Modalità senza RAG - solo GPT
            return $this->simpleQuery($question, $options);
        }
        
        // Modalità RAG completa
        return $this->ragQuery($question, $options);
    }
    
    /**
     * Query semplice senza RAG
     */
    private function simpleQuery($question, $options) {
        $response = $this->openaiClient->chat([
            'model' => $this->config['gpt_model'],
            'messages' => [
                ['role' => 'system', 'content' => $this->config['base_prompt']],
                ['role' => 'user', 'content' => $question]
            ],
            'max_tokens' => 1000
        ]);
        
        return [
            'answer' => $response['choices'][0]['message']['content'],
            'tokens_used' => $response['usage']['total_tokens'],
            'rag_used' => false,
            'chunks_used' => [],
            'debug_info' => null
        ];
    }
    
    /**
     * Query RAG completa
     */
    private function ragQuery($question, $options) {
        // Genera embedding per la domanda
        $questionEmbedding = $this->openaiClient->generateEmbedding($question);
        
        // Carica tutti gli embedding esistenti
        $embeddings = $this->loadAllEmbeddings();
        
        if (empty($embeddings)) {
            return [
                'answer' => 'Non ci sono documenti caricati. Carica alcuni documenti per utilizzare il RAG.',
                'tokens_used' => 0,
                'rag_used' => false,
                'chunks_used' => [],
                'debug_info' => null
            ];
        }
        
        // Trova i chunk più simili
        $similarChunks = $this->findSimilarChunks($questionEmbedding, $embeddings, $options['max_chunks'], $question);
        
        if (empty($similarChunks)) {
            return [
                'answer' => 'Non ho trovato informazioni rilevanti nei documenti caricati per rispondere alla tua domanda.',
                'tokens_used' => 0,
                'rag_used' => true,
                'chunks_used' => [],
                'debug_info' => null
            ];
        }
        
        // Costruisci il prompt con i chunk
        $context = $this->buildContext($similarChunks);
        $prompt = $this->buildPrompt($context, $question);
        
        // Ottimizza se necessario
        $optimizedPrompt = $this->optimizePrompt($prompt, $options['max_tokens']);
        
        // Chiama OpenAI
        $response = $this->openaiClient->chat([
            'model' => $this->config['gpt_model'],
            'messages' => [
                ['role' => 'system', 'content' => $optimizedPrompt['system']],
                ['role' => 'user', 'content' => $optimizedPrompt['user']]
            ],
            'max_tokens' => 1000
        ]);
        
        $result = [
            'answer' => $response['choices'][0]['message']['content'],
            'tokens_used' => $response['usage']['total_tokens'],
            'rag_used' => true,
            'chunks_used' => $similarChunks,
            'debug_info' => null
        ];
        
        // Aggiungi info debug se richiesto
        if ($options['debug']) {
            $result['debug_info'] = [
                'prompt' => $optimizedPrompt,
                'optimization_applied' => $optimizedPrompt['optimization_applied'] ?? false,
                'estimated_tokens' => $this->estimateTokens($optimizedPrompt['system'] . $optimizedPrompt['user']),
                'chunks_details' => array_map(function($chunk) {
                    return [
                        'source' => $chunk['source'],
                        'similarity' => $chunk['similarity'],
                        'text' => $chunk['text']
                    ];
                }, $similarChunks)
            ];
        }
        
        return $result;
    }
    
    /**
     * Aggiunge un documento al RAG
     * 
     * @param string $content Contenuto del documento
     * @param string $filename Nome del file (opzionale)
     * @param array $metadata Metadati aggiuntivi
     * @return array Risultato dell'operazione
     */
    public function addDocument($content, $filename = '', $metadata = []) {
        $documentProcessor = new DocumentProcessor($this->config);
        return $documentProcessor->processDocument($content, $filename, $metadata);
    }
    
    /**
     * Rimuove un documento dal RAG
     * 
     * @param string $id ID del documento
     * @return bool Successo dell'operazione
     */
    public function removeDocument($id) {
        $documentProcessor = new DocumentProcessor($this->config);
        return $documentProcessor->removeDocument($id);
    }
    
    /**
     * Lista tutti i documenti
     * 
     * @return array Lista documenti
     */
    public function listDocuments() {
        $documentProcessor = new DocumentProcessor($this->config);
        return $documentProcessor->listDocuments();
    }
    
    /**
     * Ottiene statistiche del RAG
     * 
     * @return array Statistiche
     */
    public function getStats() {
        $embeddings = $this->loadAllEmbeddings();
        $documents = $this->listDocuments();
        
        return [
            'total_embeddings' => count($embeddings),
            'total_documents' => count($documents),
            'documents' => $documents
        ];
    }
    
    /**
     * Metodi privati di utilità
     */
    private function mergeConfig($userConfig) {
        $defaultConfig = include __DIR__ . '/../config/settings.php';
        return array_merge($defaultConfig, $userConfig);
    }
    
    private function loadAllEmbeddings() {
        $embeddingManager = new EmbeddingManager($this->config);
        return $embeddingManager->loadAll();
    }
    
    private function findSimilarChunks($questionEmbedding, $embeddings, $maxChunks, $question) {
        $embeddingManager = new EmbeddingManager($this->config);
        return $embeddingManager->findSimilar($questionEmbedding, $embeddings, $maxChunks, $question);
    }
    
    private function buildContext($chunks) {
        $context = [];
        foreach ($chunks as $chunk) {
            $context[] = "Fonte: {$chunk['source']}\nContenuto: {$chunk['text']}\n";
        }
        return implode("\n---\n", $context);
    }
    
    private function buildPrompt($context, $question) {
        return [
            'system' => $this->config['base_prompt'],
            'user' => "Contesto:\n{$context}\n\nDomanda: {$question}"
        ];
    }
    
    private function optimizePrompt($prompt, $maxTokens) {
        $tokenOptimizer = new TokenOptimizer($this->config);
        return $tokenOptimizer->optimize($prompt, $maxTokens);
    }
    
    private function estimateTokens($text) {
        return ceil(strlen($text) / 4);
    }
} 