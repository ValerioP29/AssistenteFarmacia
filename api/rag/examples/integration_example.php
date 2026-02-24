<?php
/**
 * Esempio di integrazione RAG Engine in altri progetti
 * 
 * Questo file mostra come utilizzare il RAG Engine in un progetto PHP esistente
 */

// 1. Includi il core RAG
require_once __DIR__ . '/../core/autoload.php';

// 2. Configurazione personalizzata (opzionale)
$customConfig = [
    'openai_api_key' => 'la-tua-chiave-api-qui',
    'base_prompt' => 'Sei un assistente specializzato in assistenza clienti. Rispondi sempre in modo professionale e cordiale.',
    'max_chunks' => 3,
    'data_dir' => __DIR__ . '/../data' // Percorso assoluto per i dati
];

// 3. Inizializza il motore RAG
$rag = new RAGEngine($customConfig);

// 4. Esempio di utilizzo
try {
    // Aggiungi un documento
    $result = $rag->addDocument(
        "Il nostro prodotto costa 99€ e include supporto 24/7. La garanzia è di 2 anni.",
        "prezzi.txt"
    );
    echo "Documento aggiunto: " . $result['chunks_created'] . " chunk creati\n";
    
    // Fai una domanda
    $response = $rag->query("Quanto costa il prodotto?", [
        'use_rag' => true,
        'debug' => false
    ]);
    
    echo "Risposta: " . $response['answer'] . "\n";
    echo "Token utilizzati: " . $response['tokens_used'] . "\n";
    echo "RAG utilizzato: " . ($response['rag_used'] ? 'Sì' : 'No') . "\n";
    
    // Ottieni statistiche
    $stats = $rag->getStats();
    echo "Documenti totali: " . $stats['total_documents'] . "\n";
    echo "Embedding totali: " . $stats['total_embeddings'] . "\n";
    
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?> 