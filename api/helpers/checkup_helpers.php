<?php
/**
 * Helper per le funzionalità di checkup con analisi immagini
 */

/**
 * Analizza un'immagine per il checkup di benessere
 * 
 * @param string $prompt Il prompt per l'analisi
 * @param string|null $image_data Immagine in base64 (opzionale)
 * @param string|null $image_format Formato immagine (opzionale)
 * @return array|false Risposta dell'analisi o false in caso di errore
 */
function analyze_checkup_image($prompt, $image_data = null, $image_format = null) {
    // Validazione input
    if (empty(trim($prompt))) {
        return false;
    }
    
    // Validazione immagine se presente
    if ($image_data) {
        $image_validation = validateImageBase64($image_data, $image_format);
        if (!$image_validation['valid']) {
            return false;
        }
    }
    
    // Prompt di sistema specifico per checkup
    $system_prompt = "Sei un assistente virtuale specializzato in analisi di immagini per il benessere e la salute. 
Analizza l'immagine fornita e rispondi al prompt dell'utente in modo professionale e utile.
Fornisci consigli generali di benessere, ma ricorda di non fare diagnosi mediche.
Se l'immagine non è chiara o non puoi analizzarla, comunicalo onestamente.
Mantieni un tono rassicurante e professionale.

IMPORTANTE: Formatta sempre la tua risposta in HTML utilizzando i seguenti tag:
- <h3> per i titoli principali
- <h4> per i sottotitoli
- <p> per i paragrafi di testo
- <ul> e <li> per le liste
- <strong> per il testo in grassetto
- <em> per il testo in corsivo
- <br> per le interruzioni di riga quando necessario

Esempio di formattazione:
<h3>Analisi del Benessere</h3>
<p>Basandomi sull'immagine fornita, posso osservare che...</p>
<h4>Raccomandazioni</h4>
<ul>
<li><strong>Prima raccomandazione</strong>: Descrizione dettagliata</li>
<li><strong>Seconda raccomandazione</strong>: Descrizione dettagliata</li>
</ul>
<p><em>Nota: Questi sono consigli generali di benessere. Per consigli specifici, consulta sempre un professionista della salute.</em></p>";
    
    // Chiama OpenAI
    $response = openai_new_chatbot_request($prompt, $system_prompt, $image_data);
    
    return $response;
}

/**
 * Prepara la risposta strutturata per il checkup
 * 
 * @param string $prompt Il prompt originale
 * @param array|string $analysis L'analisi di ChatGPT
 * @param bool $has_image Se è stata fornita un'immagine
 * @return array Risposta strutturata
 */
function format_checkup_response($prompt, $analysis, $has_image = false) {
    // Estrai il contenuto dell'analisi
    $content = is_array($analysis) ? ($analysis['risposta_html'] ?? $analysis) : $analysis;
    
    // Formatta il contenuto in HTML se necessario
    $formatted_content = format_text_to_html($content);
    
    return [
        'code' => 200,
        'status' => true,
        'message' => null,
        'data' => [
            'id' => generateUniqueId(),
            'hasImage' => $has_image,
            'analysis' => $formatted_content,
            'timestamp' => date('Y-m-d H:i:s')
        ],
    ];
}

/**
 * Valida i parametri di input per il checkup
 * 
 * @param array $input Dati di input
 * @return array Risultato validazione
 */
function validate_checkup_input($input) {
    $errors = [];
    
    // Validazione prompt
    if (empty(trim($input['prompt'] ?? ''))) {
        $errors[] = 'Il prompt è obbligatorio';
    }
    
    // Validazione immagine se presente
    if (!empty($input['image'])) {
        $image_validation = validateImageBase64($input['image'], $input['imageFormat'] ?? null);
        if (!$image_validation['valid']) {
            $errors[] = 'Immagine non conforme: ' . $image_validation['error'];
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Ottiene il prompt di sistema personalizzato per tipo di checkup
 * 
 * @param string $checkup_type Tipo di checkup (pelle, benessere, etc.)
 * @return string Prompt di sistema
 */
function get_checkup_system_prompt($checkup_type = 'general') {
    $html_formatting = "

IMPORTANTE: Formatta sempre la tua risposta in HTML utilizzando i seguenti tag:
- <h3> per i titoli principali
- <h4> per i sottotitoli
- <p> per i paragrafi di testo
- <ul> e <li> per le liste
- <strong> per il testo in grassetto
- <em> per il testo in corsivo
- <br> per le interruzioni di riga quando necessario

Esempio di formattazione:
<h3>Analisi del Benessere</h3>
<p>Basandomi sull'immagine fornita, posso osservare che...</p>
<h4>Raccomandazioni</h4>
<ul>
<li><strong>Prima raccomandazione</strong>: Descrizione dettagliata</li>
<li><strong>Seconda raccomandazione</strong>: Descrizione dettagliata</li>
</ul>
<p><em>Nota: Questi sono consigli generali di benessere. Per consigli specifici, consulta sempre un professionista della salute.</em></p>";

    $prompts = [
        'pelle' => "Sei un assistente virtuale specializzato nell'analisi della pelle. 
Analizza l'immagine fornita e fornisci consigli generali per il benessere della pelle.
Non fare diagnosi mediche, ma suggerisci prodotti o abitudini che potrebbero essere utili.
Se vedi qualcosa che richiede attenzione medica, consiglia di consultare un dermatologo." . $html_formatting,
        
        'benessere' => "Sei un assistente virtuale specializzato in analisi per il benessere generale. 
Analizza l'immagine fornita e fornisci consigli di benessere e stile di vita.
Focalizzati su alimentazione, attività fisica, gestione dello stress e abitudini salutari.
Non fornire consigli medici specifici." . $html_formatting,
        
        'general' => "Sei un assistente virtuale specializzato in analisi di immagini per il benessere e la salute. 
Analizza l'immagine fornita e rispondi al prompt dell'utente in modo professionale e utile.
Fornisci consigli generali di benessere, ma ricorda di non fare diagnosi mediche.
Se l'immagine non è chiara o non puoi analizzarla, comunicalo onestamente." . $html_formatting
    ];
    
    return $prompts[$checkup_type] ?? $prompts['general'];
}

/**
 * Formatta il testo in HTML se non è già formattato
 * 
 * @param string $text Il testo da formattare
 * @return string Il testo formattato in HTML
 */
function format_text_to_html($text) {
    // Se il testo contiene già tag HTML, restituiscilo così com'è
    if (preg_match('/<[^>]+>/', $text)) {
        return $text;
    }
    
    // Altrimenti, formatta il testo in HTML
    $html = '<p>' . nl2br(htmlspecialchars($text)) . '</p>';
    
    // Converti le righe che iniziano con - o * in liste
    $html = preg_replace('/^[\s]*[-*][\s]+(.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
    
    // Converti le righe che sembrano titoli (tutto maiuscolo o con numeri)
    $html = preg_replace('/^[\s]*([A-Z][A-Z\s\d]+)[\s]*$/m', '<h4>$1</h4>', $html);
    
    // Pulisci eventuali tag HTML duplicati
    $html = preg_replace('/<p>\s*<h[34]>/', '<h$1', $html);
    $html = preg_replace('/<\/h[34]>\s*<\/p>/', '</h$1>', $html);
    
    return $html;
} 