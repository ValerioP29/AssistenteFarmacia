<?php
/**
 * Script per caricare documenti di esempio nel sistema RAG
 * Esegui questo script una volta per popolare il sistema con documenti base
 */

require_once('_api_bootstrap.php');

echo "<h1>📚 Caricamento Documenti RAG</h1>\n";

// Verifica stato sistema
$status = rag_check_status();
if (!$status['status']) {
    echo "<p style='color: red;'>❌ Sistema RAG non disponibile</p>\n";
    exit;
}

echo "<p style='color: green;'>✅ Sistema RAG funzionante</p>\n";

// Documenti da caricare
$documents = [
    [
        'content' => file_get_contents('rag/data/documents/farmacia_servizi.txt'),
        'filename' => 'farmacia_servizi.txt',
        'metadata' => ['category' => 'servizi', 'type' => 'informazioni_base']
    ],
    [
        'content' => file_get_contents('rag/data/documents/promozioni_attuali.txt'),
        'filename' => 'promozioni_attuali.txt',
        'metadata' => ['category' => 'promozioni', 'type' => 'offerte_attuali']
    ]
];

$loaded_count = 0;
$error_count = 0;

foreach ($documents as $doc) {
    echo "<h3>Caricamento: " . htmlspecialchars($doc['filename']) . "</h3>\n";
    
    $result = rag_add_document($doc['content'], $doc['filename'], $doc['metadata']);
    
    if ($result['status']) {
        echo "<p style='color: green;'>✅ Caricato con successo</p>\n";
        $loaded_count++;
    } else {
        echo "<p style='color: red;'>❌ Errore: " . htmlspecialchars($result['message']) . "</p>\n";
        $error_count++;
    }
}

// Statistiche finali
echo "<h2>📊 Risultati</h2>\n";
echo "<p>Documenti caricati: <strong>$loaded_count</strong></p>\n";
echo "<p>Errori: <strong>$error_count</strong></p>\n";

$final_stats = rag_get_stats();
echo "<h3>Statistiche Finali Sistema RAG:</h3>\n";
echo "<pre>" . json_encode($final_stats, JSON_PRETTY_PRINT) . "</pre>\n";

if ($loaded_count > 0) {
    echo "<h2>🎉 Caricamento Completato!</h2>\n";
    echo "<p>Il sistema RAG è ora popolato con documenti informativi sulla farmacia.</p>\n";
    echo "<p>Puoi ora testare il chatbot con domande sui servizi e le promozioni.</p>\n";
} else {
    echo "<h2>⚠️ Attenzione</h2>\n";
    echo "<p>Nessun documento è stato caricato. Verifica la configurazione del sistema RAG.</p>\n";
} 