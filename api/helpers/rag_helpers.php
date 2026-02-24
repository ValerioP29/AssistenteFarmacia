<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

// Includi il core RAG
require_once(site_path() . '/rag/core/autoload.php');

// Costante per abilitare/disabilitare RAG globalmente
if (!defined('RAG_ENABLED')) {
    define('RAG_ENABLED', true); // Cambia qui per disabilitare: false
}

/**
 * Helper per l'integrazione del sistema RAG
 * Mantiene la compatibilità con il sistema esistente
 */

/**
 * Inizializza il motore RAG con configurazione personalizzata per la farmacia
 */
function init_rag_engine($config = []) {
    $defaultConfig = [
        'openai_api_key' => $_ENV['JTA_APP_OPENAI_API_KEY'],
        'gpt_model' => 'gpt-4-turbo-preview',
        'embedding_model' => 'text-embedding-ada-002',
        'max_chunks' => 5,
        'chunk_size' => 200,
        'max_tokens' => 8000,
        'base_prompt' => get_chatbot_context(), // Usa il prompt esistente della farmacia
        'data_dir' => site_path() . '/rag/data',
        'embeddings_dir' => site_path() . '/rag/data/embeddings',
        'documents_dir' => site_path() . '/rag/data/documents',
        'debug_mode' => false
    ];
    
    $finalConfig = array_merge($defaultConfig, $config);
    
    return new RAGEngine($finalConfig);
}

/**
 * Esegue una query RAG con il contesto della farmacia
 * Mantiene la compatibilità con la funzione openai_chatbot esistente
 */
function rag_chatbot($prompt_from_user, $options = []) {
    try {
        $rag = init_rag_engine();
        
        $defaultOptions = [
            'use_rag' => RAG_ENABLED, // Usa la costante globale
            'debug' => false,
            'max_chunks' => 5,
            'max_tokens' => 800
        ];
        
        $finalOptions = array_merge($defaultOptions, $options);
        
        // Se la query non è farmaceutica, usa un prompt più generico
        if (!is_pharmacy_query($prompt_from_user)) {
            $customConfig = [
                'base_prompt' => "Sei un assistente AI esperto. Rispondi alla domanda dell'utente utilizzando le informazioni fornite nel contesto. Fornisci informazioni specifiche e dettagliate quando disponibili. Se hai informazioni rilevanti nel contesto, usale per rispondere in modo completo e utile. Rispondi sempre in italiano in modo chiaro e diretto."
            ];
            $rag = init_rag_engine($customConfig);
        }
        
        $response = $rag->query($prompt_from_user, $finalOptions);
        
        return [
            'code' => 200,
            'status' => true,
            'message' => $response['answer'],
            'data' => [
                'tokens_used' => $response['tokens_used'],
                'rag_used' => $response['rag_used'],
                'chunks_used' => $response['chunks_used'],
                'debug_info' => $response['debug_info'] ?? null
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'code' => 500,
            'status' => false,
            'error' => $e->getMessage(),
            'message' => 'Errore nel sistema RAG. Riprova più tardi.'
        ];
    }
}

/**
 * Aggiunge un documento al sistema RAG
 */
function rag_add_document($content, $filename = '', $metadata = []) {
    try {
        $rag = init_rag_engine();
        
        $result = $rag->addDocument($content, $filename, $metadata);
        
        return [
            'code' => 200,
            'status' => true,
            'message' => 'Documento aggiunto con successo',
            'data' => $result
        ];
        
    } catch (Exception $e) {
        return [
            'code' => 500,
            'status' => false,
            'error' => $e->getMessage(),
            'message' => 'Errore nell\'aggiunta del documento'
        ];
    }
}

/**
 * Rimuove un documento dal sistema RAG
 */
function rag_remove_document($document_id) {
    try {
        $rag = init_rag_engine();
        
        $result = $rag->removeDocument($document_id);
        
        return [
            'code' => 200,
            'status' => true,
            'message' => 'Documento rimosso con successo',
            'data' => $result
        ];
        
    } catch (Exception $e) {
        return [
            'code' => 500,
            'status' => false,
            'error' => $e->getMessage(),
            'message' => 'Errore nella rimozione del documento'
        ];
    }
}

/**
 * Lista tutti i documenti nel sistema RAG
 */
function rag_list_documents() {
    try {
        $rag = init_rag_engine();
        
        $documents = $rag->listDocuments();
        
        return [
            'code' => 200,
            'status' => true,
            'message' => 'Documenti recuperati con successo',
            'data' => $documents
        ];
        
    } catch (Exception $e) {
        return [
            'code' => 500,
            'status' => false,
            'error' => $e->getMessage(),
            'message' => 'Errore nel recupero dei documenti'
        ];
    }
}

/**
 * Ottiene statistiche del sistema RAG
 */
function rag_get_stats() {
    try {
        $rag = init_rag_engine();
        
        $stats = $rag->getStats();
        
        return [
            'code' => 200,
            'status' => true,
            'message' => 'Statistiche recuperate con successo',
            'data' => $stats
        ];
        
    } catch (Exception $e) {
        return [
            'code' => 500,
            'status' => false,
            'error' => $e->getMessage(),
            'message' => 'Errore nel recupero delle statistiche'
        ];
    }
}

/**
 * Funzione ibrida che usa RAG se disponibile, altrimenti fallback al sistema esistente
 */
function hybrid_chatbot($prompt_from_user, $options = []) {
    // Controllo se RAG è abilitato
    if (!RAG_ENABLED) {
        return openai_chatbot($prompt_from_user);
    }
    
    // Prova prima con RAG
    $rag_response = rag_chatbot($prompt_from_user, $options);
    
    // Se RAG funziona e ha trovato informazioni rilevanti, usalo
    if ($rag_response['status'] && $rag_response['data']['rag_used']) {
        // Controlla se la risposta è troppo generica per una query specifica
        $is_specific_query = is_specific_query($prompt_from_user);
        $is_generic_response = is_generic_response($rag_response['message']);
        
        if ($is_specific_query && $is_generic_response) {
            // Prova a migliorare la risposta con più contesto
            $improved_response = improve_specific_response($prompt_from_user, $rag_response);
            if ($improved_response) {
                return $improved_response;
            }
        }
        
        return $rag_response;
    }
    
    // Altrimenti fallback al sistema esistente
    return openai_chatbot($prompt_from_user);
}

/**
 * Determina se una query è specifica (nome, luogo, concetto specifico)
 */
function is_specific_query($query) {
    $query = strtolower(trim($query));
    
    // Query che sembrano specifiche
    $specific_patterns = [
        '/^[a-zàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]+$/i', // Solo nome
        '/^[a-zàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]+\s+[a-zàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]+$/i', // Nome e cognome
        '/^chi\s+è\s+/i', // "Chi è..."
        '/^cos\'\s+è\s+/i', // "Cos'è..."
        '/^dove\s+si\s+trova\s+/i', // "Dove si trova..."
        '/^quando\s+/i', // "Quando..."
    ];
    
    foreach ($specific_patterns as $pattern) {
        if (preg_match($pattern, $query)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Determina se una risposta è troppo generica
 */
function is_generic_response($response) {
    $response = strtolower($response);
    
    $generic_phrases = [
        'mi dispiace, ma sembra che la tua richiesta sia incompleta',
        'potresti fornirmi più dettagli',
        'come posso assisterti oggi',
        'non ho capito bene la tua domanda',
        'potresti essere più specifico'
    ];
    
    foreach ($generic_phrases as $phrase) {
        if (strpos($response, $phrase) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Migliora una risposta specifica con più contesto
 */
function improve_specific_response($query, $original_response) {
    try {
        $rag = init_rag_engine();
        
        // Crea una query più specifica
        $improved_query = "Informazioni dettagliate su: " . $query;
        
        $response = $rag->query($improved_query, [
            'use_rag' => true,
            'debug' => false,
            'max_chunks' => 3,
            'max_tokens' => 500
        ]);
        
        if ($response['rag_used'] && !is_generic_response($response['answer'])) {
            return [
                'code' => 200,
                'status' => true,
                'message' => $response['answer'],
                'data' => [
                    'tokens_used' => $response['tokens_used'],
                    'rag_used' => $response['rag_used'],
                    'chunks_used' => $response['chunks_used'],
                    'debug_info' => $response['debug_info'] ?? null,
                    'improved' => true
                ]
            ];
        }
        
        return null;
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Determina se una query è relativa alla farmacia
 */
function is_pharmacy_query($query) {
    $query = strtolower($query);
    
    $pharmacy_keywords = [
        'farmacia', 'farmaco', 'medicina', 'medicinale', 'prescrizione', 'ricetta',
        'sintomo', 'malattia', 'salute', 'benessere', 'vitamina', 'integratore',
        'orari', 'apertura', 'chiusura', 'promozione', 'sconto', 'prezzo',
        'consulenza', 'farmacista', 'terracina', 'giovinazzi', 'contatto',
        'indirizzo', 'telefono', 'email', 'servizio', 'prodotto'
    ];
    
    foreach ($pharmacy_keywords as $keyword) {
        if (strpos($query, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica se il sistema RAG è configurato correttamente
 */
function rag_check_status() {
    try {
        $rag = init_rag_engine();
        $stats = $rag->getStats();
        
        return [
            'code' => 200,
            'status' => true,
            'message' => 'Sistema RAG funzionante',
            'data' => [
                'documents_count' => $stats['total_documents'],
                'embeddings_count' => $stats['total_embeddings'],
                'config_loaded' => true
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'code' => 500,
            'status' => false,
            'error' => $e->getMessage(),
            'message' => 'Sistema RAG non disponibile'
        ];
    }
} 