<?php
/**
 * OpenAIClient - Gestisce le chiamate alle API OpenAI
 */
class OpenAIClient {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Chiama l'API chat di OpenAI
     */
    public function chat($data) {
        return $this->callAPI('/chat/completions', $data);
    }
    
    /**
     * Genera embedding per un testo
     */
    public function generateEmbedding($text) {
        $response = $this->callAPI('/embeddings', [
            'model' => $this->config['embedding_model'],
            'input' => $text
        ]);
        
        return $response['data'][0]['embedding'];
    }
    
    /**
     * Chiama generica alle API OpenAI
     */
    private function callAPI($endpoint, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['openai_api_url'] . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        if( $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['openai_api_key']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Errore API OpenAI: ' . $response);
        }
        
        return json_decode($response, true);
    }
} 