<?php
/**
 * DocumentProcessor - Gestisce il caricamento e la rimozione dei documenti
 */
class DocumentProcessor {
    private $config;
    private $openaiClient;
    
    public function __construct($config) {
        $this->config = $config;
        $this->openaiClient = new OpenAIClient($config);
    }
    
    /**
     * Processa un documento (caricamento)
     */
    public function processDocument($content, $filename = '', $metadata = []) {
        // Estrai testo se necessario
        $text = $this->extractTextFromFile($content, $filename);
        
        // Dividi in chunk
        $chunks = $this->splitIntoChunks($text, $this->config['chunk_size']);
        
        $results = [];
        foreach ($chunks as $chunk) {
            // Genera embedding
            $embedding = $this->openaiClient->generateEmbedding($chunk);
            
            // Salva embedding
            $id = $this->generateUUID();
            $this->saveEmbedding($id, $filename ?: 'testo_inserito', $chunk, $embedding);
            
            $results[] = [
                'id' => $id,
                'chunk' => $chunk,
                'source' => $filename ?: 'testo_inserito'
            ];
        }
        
        return [
            'success' => true,
            'chunks_created' => count($results),
            'source' => $filename ?: 'testo_inserito',
            'results' => $results
        ];
    }
    
    /**
     * Rimuove un documento
     */
    public function removeDocument($source) {
        $embeddingsDir = $this->config['embeddings_dir'];
        $removedCount = 0;
        
        if (!is_dir($embeddingsDir)) {
            return false;
        }
        
        $files = glob($embeddingsDir . '/*.json');
        
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if ($content && $content['source'] === $source) {
                if (unlink($file)) {
                    $removedCount++;
                }
            }
        }
        
        return $removedCount > 0;
    }
    
    /**
     * Lista tutti i documenti
     */
    public function listDocuments() {
        $documents = [];
        $embeddingsDir = $this->config['embeddings_dir'];
        
        if (!is_dir($embeddingsDir)) {
            return $documents;
        }
        
        $files = glob($embeddingsDir . '/*.json');
        
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if ($content) {
                $source = $content['source'];
                
                if (!isset($documents[$source])) {
                    $documents[$source] = [
                        'source' => $source,
                        'chunks' => 0,
                        'created_at' => $content['created_at'] ?? 'unknown'
                    ];
                }
                
                $documents[$source]['chunks']++;
            }
        }
        
        return array_values($documents);
    }
    
    /**
     * Metodi privati di utilità
     */
    private function extractTextFromFile($content, $filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'txt':
            case 'csv':
                return $content;
                
            case 'pdf':
                // Per ora restituiamo il contenuto grezzo
                return $content;
                
            case 'doc':
            case 'docx':
                // Per ora restituiamo il contenuto grezzo
                return $content;
                
            case 'rtf':
                // Rimuovi tag RTF e mantieni solo il testo
                $text = strip_tags($content);
                $text = preg_replace('/\{[^}]*\}/', '', $text);
                return $text;
                
            case 'odt':
                // Per ora restituiamo il contenuto grezzo
                return $content;
                
            default:
                return $content;
        }
    }
    
    private function splitIntoChunks($text, $chunkSize = 200) {
        $words = explode(' ', $text);
        $chunks = [];
        $currentChunk = '';
        $currentSize = 0;
        
        foreach ($words as $word) {
            $wordSize = strlen($word) + 1; // +1 per lo spazio
            
            if ($currentSize + $wordSize > $chunkSize && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $word;
                $currentSize = $wordSize;
            } else {
                $currentChunk .= ' ' . $word;
                $currentSize += $wordSize;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        return $chunks;
    }
    
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x4000) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function saveEmbedding($id, $source, $text, $embedding) {
        $data = [
            'id' => $id,
            'source' => $source,
            'text' => $text,
            'embedding' => $embedding,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $filename = $this->config['embeddings_dir'] . '/' . $id . '.json';
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
} 