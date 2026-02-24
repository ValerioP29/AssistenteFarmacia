<?php
/**
 * Endpoint API per la chat RAG
 * 
 * Gestisce le richieste di chat con il motore RAG integrato
 */

// Il bootstrap è già incluso dal router

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo non supportato');
    }
    
    // Leggi i dati della richiesta
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['question'])) {
        throw new Exception('Parametri mancanti: question è obbligatorio');
    }
    
    $question = $input['question'];
    $options = [
        'use_rag' => $input['use_rag'] ?? true,
        'debug' => $input['debug'] ?? false,
        'max_chunks' => $input['max_chunks'] ?? 5,
        'max_tokens' => $input['max_tokens'] ?? 8000
    ];
    
    // Configurazione personalizzata (se fornita)
    $customConfig = [];
    if (isset($input['config'])) {
        $customConfig = $input['config'];
    }
    
    // Usa il sistema integrato
    $response = hybrid_chatbot($question, $options);
    
    // Formato compatibile con l'interfaccia web
    $result = [
        'success' => $response['status'],
        'data' => [
            'answer' => $response['message'],
            'tokens_used' => $response['data']['tokens_used'] ?? 0,
            'rag_used' => $response['data']['rag_used'] ?? false,
            'chunks_used' => $response['data']['chunks_used'] ?? [],
            'debug_info' => $response['data']['debug_info'] ?? null
        ]
    ];
    
    if (!$response['status']) {
        $result['error'] = $response['message'];
    }
    
    // Restituisci la risposta
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
} 