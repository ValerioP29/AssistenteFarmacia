<?php
/**
 * TokenOptimizer - Gestisce l'ottimizzazione intelligente dei token
 */
class TokenOptimizer {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Ottimizza il prompt se supera il limite di token
     */
    public function optimize($prompt, $maxTokens) {
        $fullPrompt = $prompt['system'] . "\n\n" . $prompt['user'];
        $estimatedTokens = $this->estimateTokens($fullPrompt);
        
        if ($estimatedTokens <= $maxTokens) {
            return $prompt;
        }
        
        // Applica ottimizzazione intelligente
        return $this->applyIntelligentOptimization($prompt, $maxTokens);
    }
    
    /**
     * Applica ottimizzazione intelligente
     */
    private function applyIntelligentOptimization($prompt, $maxTokens) {
        $systemPrompt = $prompt['system'];
        $userPrompt = $prompt['user'];
        
        // Estrai il contesto dalla user prompt
        if (preg_match('/Contesto:\n(.*?)\n\nDomanda:/s', $userPrompt, $matches)) {
            $context = $matches[1];
            $question = preg_replace('/.*?Domanda:\s*/s', '', $userPrompt);
            
            // Prova prima la compressione selettiva
            $compressedContext = $this->compressChunkIntelligently($context, $question, $maxTokens);
            
            if ($this->estimateTokens($systemPrompt . "\n\nContesto:\n" . $compressedContext . "\n\nDomanda: " . $question) <= $maxTokens) {
                return [
                    'system' => $systemPrompt,
                    'user' => "Contesto:\n{$compressedContext}\n\nDomanda: {$question}",
                    'optimization_applied' => 'compressione_selettiva'
                ];
            }
            
            // Se ancora troppo lungo, usa estrazione frasi chiave
            $keySentences = $this->extractKeySentences($context, $question, $maxTokens);
            
            return [
                'system' => $systemPrompt,
                'user' => "Contesto:\n{$keySentences}\n\nDomanda: {$question}",
                'optimization_applied' => 'estrazione_frasi_chiave'
            ];
        }
        
        // Fallback: taglia semplicemente il testo
        return $this->simpleTruncation($prompt, $maxTokens);
    }
    
    /**
     * Compressione intelligente del chunk
     */
    private function compressChunkIntelligently($text, $question, $maxTokens) {
        // Dividi in frasi
        $sentences = preg_split('/[.!?]+/', $text);
        $questionWords = array_filter(explode(' ', strtolower($question)), function($word) {
            return strlen($word) > 2;
        });
        
        $relevantSentences = [];
        $totalLength = 0;
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;
            
            $relevance = 0;
            $sentenceLower = strtolower($sentence);
            
            // Bonus per parole chiave della domanda
            foreach ($questionWords as $word) {
                if (strpos($sentenceLower, $word) !== false) {
                    $relevance += 3;
                }
            }
            
            // Bonus per frasi informative
            $informativeWords = ['definizione', 'esempio', 'significa', 'importante', 'principale', 'caratteristica', 'funzione', 'ruolo', 'tipo', 'categoria'];
            foreach ($informativeWords as $word) {
                if (strpos($sentenceLower, $word) !== false) {
                    $relevance += 2;
                }
            }
            
            // Bonus per frasi con numeri o date
            if (preg_match('/\d+/', $sentence)) {
                $relevance += 1;
            }
            
            // Bonus per frasi con nomi propri
            if (preg_match('/\b[A-Z][a-z]+\b/', $sentence)) {
                $relevance += 1;
            }
            
            if ($relevance > 0) {
                $relevantSentences[] = [
                    'sentence' => $sentence,
                    'relevance' => $relevance,
                    'length' => strlen($sentence)
                ];
            }
        }
        
        // Ordina per rilevanza
        usort($relevantSentences, function($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });
        
        // Costruisci il testo compresso
        $compressedText = '';
        foreach ($relevantSentences as $item) {
            $newLength = $totalLength + $item['length'] + 2; // +2 per ". "
            
            if ($newLength > $maxTokens * 4) { // Stima approssimativa
                break;
            }
            
            $compressedText .= $item['sentence'] . '. ';
            $totalLength = $newLength;
        }
        
        return trim($compressedText);
    }
    
    /**
     * Estrazione frasi chiave
     */
    private function extractKeySentences($text, $question, $maxTokens) {
        // Dividi in chunk
        $chunks = explode("\n---\n", $text);
        $questionWords = array_filter(explode(' ', strtolower($question)), function($word) {
            return strlen($word) > 2;
        });
        
        $bestSentences = [];
        
        foreach ($chunks as $chunk) {
            $sentences = preg_split('/[.!?]+/', $chunk);
            
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (empty($sentence)) continue;
                
                $relevance = 0;
                $sentenceLower = strtolower($sentence);
                
                // Calcola rilevanza
                foreach ($questionWords as $word) {
                    if (strpos($sentenceLower, $word) !== false) {
                        $relevance += 2;
                    }
                }
                
                // Bonus per frasi con informazioni specifiche
                if (preg_match('/\d+/', $sentence)) {
                    $relevance += 1;
                }
                
                if (preg_match('/\b[A-Z][a-z]+\b/', $sentence)) {
                    $relevance += 1;
                }
                
                if ($relevance > 0) {
                    $bestSentences[] = [
                        'sentence' => $sentence,
                        'relevance' => $relevance
                    ];
                }
            }
        }
        
        // Ordina e prendi le migliori
        usort($bestSentences, function($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });
        
        $selectedSentences = array_slice($bestSentences, 0, 10); // Max 10 frasi
        $result = '';
        
        foreach ($selectedSentences as $item) {
            $result .= $item['sentence'] . '. ';
        }
        
        return trim($result);
    }
    
    /**
     * Troncamento semplice (fallback)
     */
    private function simpleTruncation($prompt, $maxTokens) {
        $fullPrompt = $prompt['system'] . "\n\n" . $prompt['user'];
        $maxChars = $maxTokens * 4; // Stima approssimativa
        
        if (strlen($fullPrompt) <= $maxChars) {
            return $prompt;
        }
        
        // Tronca il contesto mantenendo la domanda
        if (preg_match('/Contesto:\n(.*?)\n\nDomanda:/s', $prompt['user'], $matches)) {
            $context = $matches[1];
            $question = preg_replace('/.*?Domanda:\s*/s', '', $prompt['user']);
            
            $maxContextChars = $maxChars - strlen($prompt['system']) - strlen($question) - 20; // 20 per "Contesto:\n\nDomanda: "
            
            if (strlen($context) > $maxContextChars) {
                $context = substr($context, 0, $maxContextChars) . '...';
            }
            
            return [
                'system' => $prompt['system'],
                'user' => "Contesto:\n{$context}\n\nDomanda: {$question}",
                'optimization_applied' => 'troncamento_semplice'
            ];
        }
        
        // Fallback generale
        return [
            'system' => $prompt['system'],
            'user' => substr($prompt['user'], 0, $maxChars - strlen($prompt['system']) - 10) . '...',
            'optimization_applied' => 'troncamento_generale'
        ];
    }
    
    /**
     * Stima approssimativa dei token
     */
    private function estimateTokens($text) {
        return ceil(strlen($text) / 4);
    }
} 