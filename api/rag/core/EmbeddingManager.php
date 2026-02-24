<?php
/**
 * EmbeddingManager - Gestisce il caricamento e la ricerca degli embedding
 */
class EmbeddingManager {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Carica tutti gli embedding
     */
    public function loadAll() {
        $embeddings = [];
        $embeddingsDir = $this->config['embeddings_dir'];
        
        if (!is_dir($embeddingsDir)) {
            return $embeddings;
        }
        
        $files = glob($embeddingsDir . '/*.json');
        
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if ($content) {
                $embeddings[] = $content;
            }
        }
        
        return $embeddings;
    }
    
    /**
     * Trova i chunk più simili
     */
    public function findSimilar($questionEmbedding, $embeddings, $maxChunks = 5, $question = '') {
        $similarities = [];
        
        foreach ($embeddings as $embedding) {
            $similarity = $this->cosineSimilarity($questionEmbedding, $embedding['embedding']);
            
            // Applica filtri intelligenti
            $filteredSimilarity = $this->applyIntelligentFilters($similarity, $embedding['text'], $question);
            
            if ($filteredSimilarity > 0) {
                $similarities[] = [
                    'id' => $embedding['id'],
                    'source' => $embedding['source'],
                    'text' => $embedding['text'],
                    'similarity' => $filteredSimilarity,
                    'original_similarity' => $similarity
                ];
            }
        }
        
        // Ordina per similarità decrescente
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        // Prendi solo i primi maxChunks
        return array_slice($similarities, 0, $maxChunks);
    }
    
    /**
     * Calcola la similarità del coseno
     */
    private function cosineSimilarity($vec1, $vec2) {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        
        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);
        
        if ($norm1 == 0 || $norm2 == 0) return 0;
        
        return $dotProduct / ($norm1 * $norm2);
    }
    
    /**
     * Applica filtri intelligenti per migliorare la rilevanza
     */
    private function applyIntelligentFilters($similarity, $chunkText, $question) {
        $filteredSimilarity = $similarity;
        
        // Converti tutto in minuscolo per il confronto
        $questionLower = strtolower($question);
        $chunkLower = strtolower($chunkText);
        
        // Estrai parole chiave dalla domanda
        $questionWords = array_filter(explode(' ', $questionLower), function($word) {
            return strlen($word) > 2 && !in_array($word, ['che', 'chi', 'cosa', 'come', 'dove', 'quando', 'perché', 'quale', 'quali', 'del', 'della', 'dello', 'delle', 'degli', 'al', 'alla', 'allo', 'alle', 'agli', 'dal', 'dalla', 'dallo', 'dalle', 'dagli', 'nel', 'nella', 'nello', 'nelle', 'negli', 'con', 'senza', 'tra', 'fra', 'su', 'sopra', 'sotto', 'dentro', 'fuori', 'prima', 'dopo', 'durante', 'mentre', 'se', 'ma', 'e', 'o', 'non', 'anche', 'pure', 'solo', 'soltanto', 'ancora', 'già', 'sempre', 'mai', 'forse', 'probabilmente', 'certamente', 'sicuramente']);
        });
        
        // Bonus per parole chiave esatte
        $keywordBonus = 0;
        foreach ($questionWords as $word) {
            if (strpos($chunkLower, $word) !== false) {
                $keywordBonus += 0.1;
            }
        }
        
        // Bonus per frasi complete
        $sentenceBonus = 0;
        $questionSentences = explode('.', $question);
        foreach ($questionSentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 10 && strpos($chunkLower, strtolower($sentence)) !== false) {
                $sentenceBonus += 0.2;
            }
        }
        
        // Bonus per nomi propri (iniziale maiuscola)
        $nameBonus = 0;
        preg_match_all('/\b[A-Z][a-z]+\b/', $question, $matches);
        foreach ($matches[0] as $name) {
            if (strpos($chunkText, $name) !== false) {
                $nameBonus += 0.15;
            }
        }
        
        // Bonus per numeri e date
        $numberBonus = 0;
        preg_match_all('/\d+/', $question, $matches);
        foreach ($matches[0] as $number) {
            if (strpos($chunkText, $number) !== false) {
                $numberBonus += 0.1;
            }
        }
        
        // Applica i bonus
        $filteredSimilarity += $keywordBonus + $sentenceBonus + $nameBonus + $numberBonus;
        
        // Limita a 1.0
        return min(1.0, $filteredSimilarity);
    }
} 