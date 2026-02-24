<?php
/**
 * Endpoint API per la gestione dei documenti
 * 
 * Gestisce il caricamento, la rimozione e la lista dei documenti integrato
 */

// Il bootstrap è già incluso dal router

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Usa il sistema integrato
    
    switch ($method) {
        case 'GET':
            // Lista documenti
            $response = rag_list_documents();
            
            $result = [
                'success' => $response['status'],
                'data' => [
                    'documents' => $response['data'] ?? [],
                    'stats' => [
                        'total_documents' => count($response['data'] ?? []),
                        'total_embeddings' => 0
                    ]
                ]
            ];
            
            if (!$response['status']) {
                $result['error'] = $response['message'];
            }
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'POST':
            // Carica documento
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['content'])) {
                throw new Exception('Parametri mancanti: content è obbligatorio');
            }
            
            $content = $input['content'];
            $filename = $input['filename'] ?? '';
            $metadata = $input['metadata'] ?? [];
            
            $response = rag_add_document($content, $filename, $metadata);
            
            $result = [
                'success' => $response['status'],
                'data' => $response['data'] ?? []
            ];
            
            if (!$response['status']) {
                $result['error'] = $response['message'];
            }
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'DELETE':
            // Rimuovi documento
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['id'])) {
                throw new Exception('Parametri mancanti: id è obbligatorio');
            }
            
            $response = rag_remove_document($input['id']);
            
            $result = [
                'success' => $response['status'],
                'message' => $response['message']
            ];
            
            if (!$response['status']) {
                $result['error'] = $response['message'];
            }
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        default:
            throw new Exception('Metodo non supportato');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
} 