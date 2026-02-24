<?php
/**
 * Esempio di integrazione RAG Engine via API REST
 * 
 * Questo file mostra come utilizzare il RAG Engine tramite chiamate API
 */

class RAGClient {
    private $baseUrl;
    
    public function __construct($baseUrl = 'http://localhost/rag') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * Esegue una query RAG
     */
    public function query($question, $options = []) {
        $data = array_merge([
            'question' => $question,
            'use_rag' => true,
            'debug' => false
        ], $options);
        
        return $this->makeRequest('POST', '/api/chat', $data);
    }
    
    /**
     * Aggiunge un documento
     */
    public function addDocument($content, $filename = '', $metadata = []) {
        $data = [
            'content' => $content,
            'filename' => $filename,
            'metadata' => $metadata
        ];
        
        return $this->makeRequest('POST', '/api/documents', $data);
    }
    
    /**
     * Lista i documenti
     */
    public function listDocuments() {
        return $this->makeRequest('GET', '/api/documents');
    }
    
    /**
     * Rimuove un documento
     */
    public function removeDocument($id) {
        return $this->makeRequest('DELETE', '/api/documents', ['id' => $id]);
    }
    
    /**
     * Chiamata generica alle API
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => 'Content-Type: application/json',
                'ignore_errors' => true
            ]
        ];
        
        if ($data && $method !== 'GET') {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Errore nella chiamata API');
        }
        
        $result = json_decode($response, true);
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Errore sconosciuto');
        }
        
        return $result['data'];
    }
}

// Esempio di utilizzo
try {
    // Inizializza il client
    $ragClient = new RAGClient('http://localhost/rag');
    
    // Aggiungi un documento
    $result = $ragClient->addDocument(
        "Il nostro servizio di hosting costa 29€ al mese e include SSL gratuito.",
        "hosting.txt"
    );
    echo "Documento aggiunto con successo\n";
    
    // Fai una domanda
    $response = $ragClient->query("Quanto costa l'hosting?");
    echo "Risposta: " . $response['answer'] . "\n";
    
    // Lista documenti
    $documents = $ragClient->listDocuments();
    echo "Documenti disponibili: " . count($documents['documents']) . "\n";
    
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?> 