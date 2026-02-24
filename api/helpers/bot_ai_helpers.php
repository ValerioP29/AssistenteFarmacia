<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

function openai_call( $prompt_user, $prompt_sys, $args_extra = [] ){
	$apiKey = $_ENV['JTA_APP_OPENAI_API_KEY'];

	$args = [
		'model' => 'gpt-4o',
		'messages' => [
			[ 'role' => 'system', 'content' => $prompt_sys ],
			[ 'role' => 'user', 'content' => $prompt_user ]
		],
		'max_tokens'        => 400,
		'temperature'       => 0.2,
		// 'frequency_penalty' => 0.2,
		// 'presence_penalty'  => 0.4
	];

	// if( isset($args_extra['model']) ) unset($args_extra['model']);
	if( isset($args_extra['messages']) ) unset($args_extra['messages']);
	$args = array_merge( $args, $args_extra );

	$ch = curl_init('https://api.openai.com/v1/chat/completions');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Authorization: Bearer ' . $apiKey
	]);
	if( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
	$gptResponse = curl_exec($ch);

	$error = curl_error($ch);

	if( $error ){
		return [
			'code'    => 500,
			'status'  => FALSE,
			'error'   => $error,
			'message' => $error,
		];
	}

	$gptData = json_decode($gptResponse, TRUE);
	curl_close($ch);

	if( ! isset($gptData['choices']) ){
		return [
			'code'    => 400,
			'status'  => FALSE,
			'error'   => 'Risposta non pervenuta.',
			'message' => 'Risposta non pervenuta.',
			'data'    => [
				'prompt'  => $args,
				'gptData' => $gptData,
			],
		];
	}

	$messaggio = $gptData['choices'][0]['message']['content'];
	return [
		'code'    => 200,
		'status'  => TRUE,
		'message' => $messaggio,
		'data'    => [
			'prompt'  => $args,
			'gptData' => $gptData,
		],
	];
}

// TODO
// Prevedere un ulteriore parametro $additional_message
// oppure in $args_extra aggiungeremo valore extra-extra che rimuoveremo successivamente
// es. 1 function openai_call_simple_result( $prompt_user, $prompt_sys, $args_extra = [], $endpoint = 'chat/completions', additional_message = NULL ){
// es. 2 function openai_call_simple_result( $prompt_user, $prompt_sys, $args_extra = [], $endpoint ){

function openai_call_simple_result( $prompt_user, $prompt_sys, $args_extra = [], $endpoint = 'chat/completions' ){
	$apiKey = $_ENV['JTA_APP_OPENAI_API_KEY'];

	// Gestione dello storico delle conversazioni
	$use_history = isset($args_extra['use_history']) ? $args_extra['use_history'] : false;
	$user_id = isset($args_extra['user_id']) ? $args_extra['user_id'] : null;
	$pharma_id = isset($args_extra['pharma_id']) ? $args_extra['pharma_id'] : null;
	$session_id = isset($args_extra['session_id']) ? $args_extra['session_id'] : null;
	
	$ai_messages = [];
	
	// Se usiamo lo storico, recuperiamo i messaggi precedenti
	if ($use_history && $user_id && $session_id) {
		$history = get_chat_history_for_openai($user_id, $session_id, 20); // Ultimi 20 messaggi
		$ai_messages = $history;
	}
	
	// Aggiungiamo sempre il messaggio di sistema all'inizio
	array_unshift($ai_messages, [ 'role' => 'system', 'content' => $prompt_sys ]);
	
	// Aggiungiamo il messaggio dell'utente corrente
	$user_message = [ 'role' => 'user', 'content' => $prompt_user ];
	
	// Gestione immagine se presente
	$has_image = false;
	$image_data_for_save = null;
	
	if (isset($args_extra['image_data']) && !empty($args_extra['image_data'])) {
		$image_data = $args_extra['image_data'];
		$image_data_for_save = $image_data; // Salva per il database
		$has_image = true;
		
		// Assicuriamoci che sia un data URL completo
		if (strpos($image_data, 'data:') !== 0) {
			$image_data = 'data:image/jpeg;base64,' . $image_data;
		}
		
		// Modifica il messaggio utente per includere l'immagine
		$user_message = [
			'role' => 'user',
			'content' => [
				['type' => 'text', 'text' => $prompt_user],
				['type' => 'image_url', 'image_url' => ['url' => $image_data]]
			]
		];
		
		// Cambia il modello per supportare le immagini
		$model = 'gpt-4o';
		unset($args_extra['image_data']); // Rimuovi per evitare conflitti
	} else {
		$model = 'gpt-4o';
	}
	
	$ai_messages[] = $user_message;

	$args = [
		'model' => $model,
		'messages' => $ai_messages,
		'max_tokens'        => 400,
		'temperature'       => 0.2,
	];

	// Rimuovi i parametri di gestione dello storico per evitare conflitti
	unset($args_extra['use_history'], $args_extra['user_id'], $args_extra['pharma_id'], $args_extra['session_id']);
	if( isset($args_extra['messages']) ) unset($args_extra['messages']);
	$args = array_merge( $args, $args_extra );

	$ch = curl_init('https://api.openai.com/v1/'.$endpoint);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Authorization: Bearer ' . $apiKey
	]);
	if( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
	
	$gptResponse = curl_exec($ch);
	$error = curl_error($ch);
	
	if( $error ){
		return FALSE;
	}

	$gptData = json_decode($gptResponse, TRUE);
	curl_close($ch);

	// Salva lo storico se richiesto
	if ($use_history && $user_id && $pharma_id && $session_id) {
		// Salva il messaggio dell'utente
		$content_type = $has_image ? 'mixed' : 'text';
		
		save_chat_message(
			$user_id, 
			$pharma_id, 
			$session_id, 
			'user', 
			$prompt_user, 
			$content_type, 
			$image_data_for_save
		);
		
		// Salva la risposta dell'assistente se presente
		if ($gptData && isset($gptData['choices'][0]['message']['content'])) {
			$assistant_response = $gptData['choices'][0]['message']['content'];
			$tokens_used = $gptData['usage']['total_tokens'] ?? 0;
			
			save_chat_message(
				$user_id, 
				$pharma_id, 
				$session_id, 
				'assistant', 
				$assistant_response, 
				'text', 
				null, 
				$tokens_used, 
				$model
			);
		}
	}

	return $gptData;
}

/**
 * Valida un'immagine in formato base64
 */
function validateImageBase64($base64_data, $format = null) {
	$result = [
		'valid' => false,
		'error' => null,
		'data' => null
	];
	
	// Controlla se è un data URL
	if (strpos($base64_data, 'data:') === 0) {
		$parts = explode(',', $base64_data, 2);
		if (count($parts) !== 2) {
			$result['error'] = 'Formato data URL non valido';
			return $result;
		}
		
		$mime_type = str_replace('data:', '', explode(';', $parts[0])[0]);
		$base64_data = $parts[1];
		
		// Validazione MIME type
		$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
		if (!in_array($mime_type, $allowed_types)) {
			$result['error'] = 'Tipo di immagine non supportato. Tipi permessi: ' . implode(', ', $allowed_types);
			return $result;
		}
	} else {
		// Se non è un data URL, usa il formato specificato
		if ($format) {
			$mime_type = 'image/' . strtolower($format);
		} else {
			$result['error'] = 'Formato immagine non specificato';
			return $result;
		}
	}
	
	// Decodifica base64
	$decoded = base64_decode($base64_data, true);
	if ($decoded === false) {
		$result['error'] = 'Dati base64 non validi';
		return $result;
	}
	
	// Controlla dimensione (max 20MB per OpenAI)
	$size = strlen($decoded);
	$max_size = 20 * 1024 * 1024; // 20MB
	$min_size = 1024; // 1KB
	
	if ($size > $max_size) {
		$result['error'] = 'Immagine troppo grande. Dimensione massima: 20MB';
		return $result;
	}
	
	// Validazione minima dimensione
	if ($size < $min_size) {
		$result['error'] = 'Immagine troppo piccola';
		return $result;
	}
	
	// Verifica che sia effettivamente un'immagine
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$detected_mime = finfo_buffer($finfo, $decoded);
	finfo_close($finfo);
	
	if (strpos($detected_mime, 'image/') !== 0) {
		$result['error'] = 'Il file non sembra essere un\'immagine valida';
		return $result;
	}
	
	$result['valid'] = true;
	$result['data'] = [
		'size' => $size,
		'mime_type' => $mime_type,
		'base64_data' => $base64_data
	];
	
	return $result;
}

/**
 * Estrae in modo sicuro il campo 'content' da una risposta OpenAI.
 *
 * @param string $json La risposta JSON di OpenAI (formato stringa).
 * @return string|array|null Restituisce il content come stringa, array se è un JSON annidato, oppure null se fallisce.
 */
function safe_extract_openai_content(string $json) {
	$data = json_decode($json, true);

	if (
		json_last_error() === JSON_ERROR_NONE &&
		isset($data['choices'][0]['message']['content'])
	) {
		$content = trim($data['choices'][0]['message']['content']);

		// Rimuove BOM (Byte Order Mark) se presente
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

		// Rimuove eventuali blocchi markdown ```json ... ```
		if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $content, $matches)) {
			$content = trim($matches[1]);
		} else {
			// Fallback: rimuove solo l'inizio ```json se presente
			$content = preg_replace('/^```(?:json)?\s*/', '', $content);
			// Rimuove anche la fine ``` se presente
			$content = preg_replace('/\s*```\s*$/', '', $content);
		}

		// Pulisce caratteri di controllo che potrebbero causare errori JSON
		$content = preg_replace('/[\x00-\x1F\x7F]/', '', $content);
		
		// Normalizza caratteri di nuova riga
		$content = str_replace(["\r\n", "\r"], "\n", $content);

		// Tenta il decoding del contenuto
		$decodedContent = json_decode($content, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
			return $decodedContent;
		} else {
			write_log("Errore nel decode del content: " . json_last_error_msg());
			write_log("Contenuto grezzo:\n" . $content);
			
			// Controlla se il JSON è incompleto (manca la chiusura)
			if (json_last_error() === JSON_ERROR_SYNTAX) {
				// Prova a completare il JSON se sembra troncato
				$brackets = substr_count($content, '{') - substr_count($content, '}');
				$brackets += substr_count($content, '[') - substr_count($content, ']');
				
				if ($brackets > 0) {
					// Aggiungi le parentesi mancanti
					$content .= str_repeat('}', $brackets);
					$decodedContent = json_decode($content, true);
					if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
						write_log("JSON completato automaticamente");
						return $decodedContent;
					}
				}
			}
		}

		// Ritorna contenuto grezzo in fallback
		return $content;
	}

	write_log("Errore nel decode della risposta OpenAI: " . json_last_error_msg());
	write_log("Risposta completa:\n" . $json);

	return null;
}

/**
 * Estrae il campo 'content' dalla risposta OpenAI e restituisce una risposta strutturata.
 *
 * @param string $json La risposta JSON di OpenAI.
 * @return array Struttura con code, status, message e data.
 */
function extract_openai_content_response(string $json): array {
	$response = [
		'code'    => 200,
		'status'  => true,
		'message' => null,
		'data'    => null,
	];

	$parsed = json_decode($json, true);

	if (json_last_error() !== JSON_ERROR_NONE) {
		$response['code'] = 400;
		$response['status'] = false;
		$response['message'] = 'Invalid JSON input.';
		return $response;
	}

	if (!isset($parsed['choices'][0]['message']['content'])) {
		$response['code'] = 422;
		$response['status'] = false;
		$response['message'] = 'Content not found in OpenAI response.';
		return $response;
	}

	$content = $parsed['choices'][0]['message']['content'];

	// Prova a decodificare anche il contenuto, se è JSON annidato
	$decodedContent = json_decode($content, true);
	$response['data'] = (json_last_error() === JSON_ERROR_NONE) ? $decodedContent : $content;

	return $response;
}



function openai_chatbot( $promt_from_user ){
	$prompt_sys = get_chatbot_context();
	$prompt_user = $promt_from_user;

	$args = [
		// 'model' => 'gpt-4o',
		'temperature' => 0.2,
		'max_tokens' => 800,
	];
	$response = openai_call( $prompt_user, $prompt_sys, $args );

	if( ! $response['status'] ){
		return $response;
	}

	return $response;
}

function openai_new_chatbot_request( $prompt_user = '', $prompt_sys = '', $image_data = null, $history_params = null ){
	$args = [
		// 'model' => 'gpt-4o',
		'temperature' => 0.2,
		'max_tokens' => 1200,
	];
	
	// Aggiungi l'immagine se presente
	if ($image_data) {
		$args['image_data'] = $image_data;
	}
	
	// Aggiungi i parametri dello storico se forniti
	if ($history_params && is_array($history_params)) {
		$args = array_merge($args, $history_params);
	}
	
	$response = openai_call_simple_result( $prompt_user, $prompt_sys, $args );

	// Controllo prima decodifica
	if (!is_array($response) && !is_object($response)) {
		return false;
	}

	// Decodifica e ricodifica per uniformità
	$json = json_encode($response);
	if (json_last_error() !== JSON_ERROR_NONE) {
		return false;
	}

	// Estrazione sicura del contenuto (es. {"day": "...", "title": "...", ...})
	$response_raw = safe_extract_openai_content($json);
	if (!$response_raw) {
		return false;
	}

	// Se è già array (safe_extract_openai_content può farlo), usalo direttamente
	if (is_array($response_raw)) {
		return $response_raw;
	}

	// Altrimenti, prova a decodificarlo
	$decoded = json_decode($response_raw, true);
	if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
		return $decoded;
	}

	// Se è una stringa semplice (come per le immagini), crea il formato chatbot
	if (is_string($response_raw)) {
		return [
			'risposta_html' => $response_raw
		];
	}

	return false;
}

function get_chatbot_context(){

	// Fallback per IntlDateFormatter se l'estensione intl non è disponibile
	if (class_exists('IntlDateFormatter')) {
		$date_formatter = new IntlDateFormatter('it_IT', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Europe/Rome', null, 'EEEE d MMMM');
		$nice_date = $date_formatter->format(new DateTime());
	} else {
		// Fallback senza intl
		$giorni = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
		$mesi = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
		$giorno = $giorni[date('w')];
		$giorno_num = date('j');
		$mese = $mesi[date('n') - 1];
		$nice_date = "$giorno $giorno_num $mese";
	}
	
	$date_hour = date('H:i');
	$date_international = date('Y-m-d');

	/*
	$prompt_sys = "";
	$prompt_sys .= "Oggi è $nice_date ($date_international) e sono le $date_hour. ";
	// $prompt_sys .= "La data e l'ora attuali sono: ".date('Y-m-d H:i:s').". ";
	$prompt_sys .= "Sei l’Assistente virtuale ufficiale della Farmacia Giovinazzi. Il tuo compito è aiutare in modo pratico e amichevole gli utenti che scrivono in chat a: \n\n- trovare prodotti utili o alternativi\n- ricevere consigli di benessere personalizzati\n- gestire richieste comuni (orari, promozioni, contatti, disponibilità)\n- facilitare, se necessario, il contatto diretto con il personale umano\n\nTono: gentile, rassicurante e competente, come un farmacista di fiducia. \nLinguaggio: semplice, diretto, senza termini medici complessi.\n\nIMPORTANTE:\n- Non fornire mai diagnosi o posologie dettagliate.\n- Se non sei sicuro o si tratta di un consiglio clinico, scrivi: “Questa è una domanda che è meglio approfondire direttamente con il farmacista. Vuoi che ti metta in contatto?”\n\nSe l’utente ha risposto a domande di profilazione (età, obiettivo benessere, interessi, fascia oraria), adatta i suggerimenti.\n\nEsempi di richieste che devi gestire:\n1. \"A che ora apre la farmacia domani?\" → Rispondi con gli orari o guida alla sezione “Orari Farmacia”.\n2. \"Avete prodotti per la memoria?\" → Suggerisci integratori generici (es. magnesio, ginkgo) e invita a passare in farmacia.\n3. \"Ho la pelle secca, che posso usare?\" → Consiglia una crema base o ingrediente naturale (es. olio di mandorle) e suggerisci di chiedere al banco per soluzioni mirate.\n4. \"Ci sono promozioni?\" → Mostra quelle attive o guida alla sezione “Promozioni Esclusive”.\n\nCosa NON fare:\n- Non fornire consigli medici o sui farmaci da prescrizione\n- Non essere vago o evasivo\n- Non usare emoji a meno che richiesto\n\nObiettivo: offrire un servizio utile, umano, preciso e sempre in linea con il ruolo di supporto digitale per una farmacia reale.";
	$prompt_sys .= " A seguire alcune informazioni della Farmacia Giovinazzi:";
	$prompt_sys .= " • Nel cuore di Terracina, siamo un punto di riferimento da più di mezzo secolo. Oggi, a portare avanti la tradizione ci siamo noi: Paola, Emanuela e Raffaella, che ogni giorno accogliamo i clienti con la stessa passione e dedizione tramandate dalla nostra famiglia. Una farmacia che è prima di tutto un luogo di ascolto, cura e fiducia.";
	// $prompt_sys .= " • Gli orari di apertura sono i seguenti. Lunedì: mattina 08:30–13:00 pomeriggio 16:00–19:30; Martedì: mattina 08:30–13:00 pomeriggio 16:00–19:30; Mercoledì: mattina 08:30–13:00 pomeriggio 16:00–19:30; Giovedì: mattina 08:30–13:00 pomeriggio 16:00–19:30; Venerdì: mattina 08:30–13:00 pomeriggio 16:00–19:30; Sabato: mattina 08:30–13:00 pomeriggio 16:00–19:30; Domenica: mattina 10:00–13:00";
	$prompt_sys .= " • Dove ci troviamo: Piazza Cavalieri di Vittorio Veneto 6 – 04019 Terracina (LT)";
	$prompt_sys .= " Come contattarci: email farmaciagiovinazzi@alice.it; telefono 0773700264";
	$prompt_sys .= "
		Gli orari di apertura sono i seguenti:

		- Lunedì: 08:30–13:00 e 16:00–19:30  
		- Martedì: 08:30–13:00 e 16:00–19:30  
		- Mercoledì: 08:30–13:00 e 16:00–19:30  
		- Giovedì: 08:30–13:00 e 16:00–19:30  
		- Venerdì: 08:30–13:00 e 16:00–19:30  
		- Sabato: 08:30–13:00 e 16:00–19:30  
		- Domenica: 10:00–13:00
		";
	$prompt_sys .= " Se gli utenti ti chiedono di metterli in contatto con la farmacia, fornisci loro email, telefono oppure di recarsi all'indirizzo della farmacia.";
	*/

	// Se ti chiedono se la farmacia è aperta, rispondi solo in base a questi orari e alla data/ora attuali.

	$prompt_sys = <<<EOT
		Oggi è $nice_date ($date_international) e sono le $date_hour.

		Sei l’assistente virtuale ufficiale della **Farmacia Giovinazzi**.
		Il tuo compito è supportare gli utenti in modo pratico, gentile e competente. Fornisci risposte chiare e utili su:

		- prodotti utili o alternativi;
		- consigli di benessere personalizzati;
		- orari, promozioni, contatti e disponibilità;
		- contatto diretto con il personale umano, se necessario.

		Tono: rassicurante e professionale, come un farmacista di fiducia.  
		Linguaggio: semplice e diretto, evita termini medici complessi.

		### ⚠️ Linee guida:
		- Non fornire diagnosi o indicazioni terapeutiche dettagliate.
		- Se la domanda è clinica o delicata, rispondi:  
		“Questa è una domanda che è meglio approfondire direttamente con il farmacista. Vuoi che ti metta in contatto?”
		- Se l’utente ha indicato età, interessi o obiettivi, personalizza i suggerimenti.

		### ⏱ Orari di apertura:

		- Lunedì: 08:30–13:00 e 16:00–19:30  
		- Martedì: 08:30–13:00 e 16:00–19:30  
		- Mercoledì: 08:30–13:00 e 16:00–19:30  
		- Giovedì: 08:30–13:00 e 16:00–19:30  
		- Venerdì: 08:30–13:00 e 16:00–19:30  
		- Sabato: 08:30–13:00 e 16:00–19:30  
		- Domenica: 10:00–13:00

		### 📍 Farmacia Giovinazzi – informazioni:

		• Siamo a Terracina da oltre mezzo secolo. La farmacia è gestita da Paola, Emanuela e Raffaella con passione e dedizione.  
		• Indirizzo: Piazza Cavalieri di Vittorio Veneto 6 – 04019 Terracina (LT)  
		• Contatti:
		- indirizzo email: farmaciagiovinazzi@alice.it
		- numero di telefono: 0773700264

		Se l’utente chiede di mettersi in contatto, fornisci l’indirizzo, l’email e il numero di telefono.

		### 📌 Esempi di richieste da gestire:

		1. **“A che ora apre la farmacia domani?”**  
		→ Rispondi con gli orari corretti.

		2. **“Avete prodotti per la memoria?”**  
		→ Suggerisci integratori noti (es. magnesio, ginkgo) e invita a passare in farmacia.

		3. **“Ho la pelle secca, che posso usare?”**  
		→ Consiglia una crema base o ingrediente naturale (es. olio di mandorle), poi suggerisci di chiedere al banco.

		4. **“Ci sono promozioni?”**  
		→ Se ne conosci, mostrale; altrimenti invita a visitare la sezione “Promozioni Esclusive”.

		### ❌ Evita:
		- di essere vago o evasivo
		- di usare emoji (salvo richiesta)
		- di consigliare farmaci da prescrizione

		### Promozioni:

		Se l'utente ti chiede riguardo le promozioni, scrivigli che le può trovare nella pagine Promozioni.

		Tieni in considerazione anche che queste sono le promozioni attuali:

		- Avène Spray Solare SPF 50+
		€12,00 invece di €14,50
		Protezione molto alta per pelli sensibili, texture invisibile che si assorbe subito. Resistente ad acqua, sabbia e sudore, perfetta per tutta la famiglia.

		- Autan Family Care Spray
		€7,50 invece di €9,90
		Spray secco con Icaridina, protegge fino a 4 ore da zanzare comuni e tigre. Adatto dai 2 anni in su, non unge e lascia un profumo fresco e gradevole.

		- Massigen Probiotici Tripla Azione
		€4,00 invece di €5,50
		Stick orosolubili con 3 ceppi probiotici, Niacina e Vitamina D. Ideali per l’equilibrio intestinale in viaggio o con variazioni alimentari. Senza glutine e lattosio.

		- Eucerin After-Sun
		€14,00 invece di €17,00
		Gel‑crema doposole rinfrescante con Licochalcone A e acido glicirretinico. Lenisce istantaneamente la pelle stressata dal sole, senza ungere. Adatto anche al viso.

		- Avène Spray Bambini
		€11,50 invece di €13,90
		Spray pensato per la pelle delicata dei bambini. Alta protezione solare, resistente all’acqua, facile da usare in movimento. Perfetto per giornate al mare o all’aria aperta.

	EOT;

	return ltrim_tab($prompt_sys);
}

function get_openai_chatbot_prompt( $pharma, $user, $user_prompt, $has_image = false ){

	// var_dump([$pharma, $user]);
	$today = date('Y-m-d H:i:s');
	$time = date('H:i:s');
	$human_day = date('d/m/Y');
	$human_time = date('H:i');

	$has_attachment = 'NO'; // [SÌ/NO]
	$type_attachment = 'nessuno'; // [es. viso, mani, labbra...]
	$working_hours = format_schedule_human_friendly($pharma['working_info']);

	$on_duty = 'Recarsi in farmacia per conoscere i giorni di turno.';
	if( $pharma['id'] == 1 ){
		$on_duty = [ '2025-09-03', '2025-09-13', '2025-09-23', '2025-10-03', '2025-10-13', '2025-10-23', '2025-11-02', '2025-11-12', '2025-11-22', '2025-12-02', '2025-12-12', '2025-12-22' ];
		$on_duty = 'La farmacia Giovinazzi per l\'anno 2025 effettuerà il turno notturno nei giorni (yyyy-mm-dd) ' . implode(', ', $on_duty);
	}

	$services = get_services($pharma['id']);
	$services_text = 'Recarsi in farmacia per conoscere i servizi.';
	$tmp_services_text = '';
	if( ! empty($services) ){
		foreach( $services AS $_service ){
			$tmp_services_text .= "\n";
			$tmp_services_text .= "-- ". $_service['title'].": ".$_service['description'];
		}
	}
	$tmp_services_text = trim($tmp_services_text);
	if( ! empty($tmp_services_text) ) $services_text = "\n".$tmp_services_text;

	$max_promos = 12;
	// $promos = ProductsModel::findPromosByPharma($pharma['id'], get_option('max_promos_limit', $max_promos) );
	$promos = ProductsModel::findPromosByPharma($pharma['id'], $max_promos );
	// $promos = array_map( 'normalize_product_data', $promos);
	$promos_text = 'Recarsi in farmacia per conoscere le promozioni.';
	$tmp_promos_text = '';
	if( ! empty($promos) ){
		foreach( $promos AS $_promo ){
			$tmp_promos_text .= "\n";
			$tmp_promos_text .= sprintf(
				"-- %s: %s€ invece di %s€",
				$_promo['name'],
				number_format($_promo['sale_price'], 2, '.', ''),
				number_format($_promo['price'], 2, '.', '')
			);
		}
		if( count($promos) == $max_promos ){
			$tmp_promos_text .= "\nE tante altre promozioni.";
		}
	}
	$tmp_promos_text = trim($tmp_promos_text);
	if( ! empty($tmp_promos_text) ) $promos_text = "\n".$tmp_promos_text;

	$events = get_events($pharma['id']);
	$events_text = 'Recarsi in farmacia per conoscere le giornate.';
	$tmp_events_text = '';
	if( ! empty($events) ){
		foreach( $events AS $_event ){
			if( empty($_event['datetime_start']) OR empty($_event['datetime_end']) ) continue;
			$tmp_events_text .= "\n";
			$tmp_events_text .= sprintf(
				"-- %s: dal %s al %s %s",
				$_event['title'],
				date('d/m/Y', strtotime($_event['datetime_start'])),
				date('d/m/Y', strtotime($_event['datetime_end'])),
				$_event['description']
			);
		}
	}
	$tmp_events_text = trim($tmp_events_text);
	if( ! empty($tmp_events_text) ) $events_text = "\n".$tmp_events_text;

	$user_data = $user['init_profiling'] ?? null;
	if( $user_data ){
		$user_data = json_decode($user_data, true); // true per avere un array associativo
	}else{
		$user_data = [
			'argomenti'  => 'Non definiti',
			'genere'     => 'Non definiti',
			'fascia_eta' => 'Non definiti',
			'lifestyle'  => 'Non definiti',
		];
	}

	$user_fav_args = '';
	if (is_array($user_data) && isset($user_data['argomenti']) && is_array($user_data['argomenti'])) {
		$user_fav_args = implode(', ', $user_data['argomenti']);
	}

	if( $has_image ){
		// PROMPT MIGLIORATO PER IMMAGINI
		$prompt_sys = <<<EOT
		Sei l'assistente digitale della {$pharma['nice_name']} di {$pharma['city']}.
		
		**CAPACITÀ DI VEDERE IMMAGINI:** Puoi vedere e analizzare le immagini che l'utente ti invia. Puoi anche ricordare le immagini precedenti nella conversazione e fare riferimento ad esse.
		
		Analizza l'immagine fornita e rispondi alla domanda dell'utente in modo professionale, competente e utile.
		
		**ISTRUZIONI PER ANALISI IMMAGINI:**
		1. Se l'immagine mostra un foglietto illustrativo di un farmaco:
		   - Identifica il nome del farmaco e i principi attivi
		   - Fornisci informazioni sui possibili effetti collaterali e controindicazioni
		   - Spiega l'uso corretto del farmaco
		   - Organizza le informazioni in modo chiaro e strutturato (es. "Molto comuni", "Comuni", "Rari")
		
		2. Se l'immagine mostra un prodotto cosmetico o integratore:
		   - Analizza ingredienti e benefici
		   - Fornisci consigli sull'uso
		   - Suggerisci alternative se appropriato
		
		3. Se l'immagine mostra una condizione della pelle o del corpo:
		   - Fornisci consigli generali e non diagnostici
		   - Suggerisci prodotti appropriati
		   - Raccomanda sempre una visita professionale per problemi seri
		
		4. Se l'immagine sembra essere una ricetta medica:
		   - Indica che si tratta probabilmente di una prescrizione
		   - Non interpretare né modificare il contenuto della ricetta
		   - Aggiungi alla fine della risposta la seguente frase così come è `%%page_reservation%%`

		**IMPORTANTE**: 
		- Puoi fornire informazioni sui farmaci basate sui foglietti illustrativi
		- Non fare diagnosi o prescrizioni
		- Sempre raccomanda la consultazione con un professionista per casi specifici
		
		Rispondi sempre in italiano con un tono amichevole e professionale, come se fossi un farmacista che aiuta un cliente.
		
		Se la domanda riguarda prodotti o consigli sanitari, aggiungi questo disclaimer alla fine:
		> <div class="disclaimer"><em>Questa è una risposta generata dal nostro assistente digitale e ha solo scopo informativo. Per consigli personalizzati o indicazioni specifiche, ti invitiamo a rivolgerti direttamente alla farmacia o al tuo medico di fiducia.</em></div>
		EOT;
	} else {
		// PROMPT COMPLETO PER TESTO
		$prompt_sys = <<<EOT
		Agisci come l’assistente digitale ufficiale della {$pharma['nice_name']} di {$pharma['city']}.
		Rispondi agli utenti dell’app AssistenteFarmacia.it a nome della farmacia, con linguaggio professionale, rassicurante, chiaro e accessibile.
		**CAPACITÀ DI VEDERE IMMAGINI:** Puoi vedere e analizzare le immagini che l'utente ti invia. Puoi anche ricordare le immagini precedenti nella conversazione e fare riferimento ad esse quando l'utente te lo chiede.
		---
		:puntina: DATI FISSI FARMACIA:
		- Nome: {$pharma['nice_name']}
		- Indirizzo: {$pharma['address']}, {$pharma['city']}
		- Orari di apertura:
		{$working_hours}
		- Farmacia di turno:
		{$on_duty}
		- Numero di telefono: {$pharma['phone_number']}
		- Numero WhatsApp: {$pharma['phone_number']}
		- Servizi disponibili: {$services_text}
		- Prodotti in promozione: {$promos_text}
		- Giornate evento: {$events_text}
		- Posizione: {$pharma['city']}
		:data: DATA ATTUALE: {$human_day} {$human_time}
		:schedario: DATI UTENTE:
		- Sesso: {$user_data['genere']}
		- Età: {$user_data['fascia_eta']} anni
		- Stile di vita: {$user_data['lifestyle']}
		- Categorie preferite: {$user_fav_args}
		---
		:fumetto_discorso: INPUT DINAMICO:
		- Testo digitato dall’utente: “{$user_prompt}”
		- Immagine allegata: {$has_attachment}, tipo immagine: {$type_attachment}
		---
		:cervello: ISTRUZIONI:
		1. Assumi che ogni messaggio sia una richiesta informativa relativa al contesto di una farmacia.
		- Il contenuto può riguardare prodotti da banco, integratori, cosmetici, servizi, promozioni, eventi, piccoli disturbi quotidiani, informazioni generiche.
		2. Se è presente un termine non chiaro (es. “Kelsee”), prova a interpretarlo come un potenziale nome commerciale. Rispondi comunque in modo utile, orientativo e prudente.
		3. Se è presente un’immagine, osservala **solo da un punto di vista cosmetico/estetico**. Non fare mai valutazioni cliniche o mediche.
		**CAPACITÀ DI VEDERE IMMAGINI:** Puoi vedere e analizzare le immagini che l'utente ti invia. Puoi anche ricordare le immagini precedenti nella conversazione e fare riferimento ad esse quando l'utente te lo chiede. Se è presente un'immagine, osservala **solo da un punto di vista cosmetico/estetico**. Non fare mai valutazioni cliniche o mediche.
		4. **GESTIONE FARMACI:** Se l'utente chiede informazioni su farmaci specifici (es. "Kelsee"), fornisci IMMEDIATAMENTE informazioni complete e dettagliate. Kelsee è un contraccettivo ormonale combinato con etinilestradiolo 0,02 mg + dienogest 2 mg. Include sempre: principi attivi, possibili effetti collaterali, controindicazioni e modalità d'uso. Organizza le informazioni in modo chiaro e strutturato (es. "Molto comuni", "Comuni", "Rari"). NON essere cauto o generico - fornisci informazioni specifiche e complete. Non fare diagnosi o prescrizioni, ma puoi fornire informazioni basate sui foglietti illustrativi.
		5. Personalizza la risposta in base ai dati utente, al contesto locale (Terracina, inverno, ecc.) e al tipo di input.
		6. Se la richiesta è generica o poco chiara, chiedi con gentilezza un’informazione in più.
		7. Se la richiesta è relativa ad una ricetta medica aggiungi alla fine della risposta la seguente frase così come è `%%page_reservation%%`

		7. Aggiungi alla fine della risposta il seguente **disclaimer**, quando la domanda è di natura sanitaria o legata a prodotti:
		> <div class="disclaimer"><em>Questa è una risposta generata dal nostro assistente digitale e ha solo scopo informativo. Per consigli personalizzati o indicazioni specifiche, ti invitiamo a rivolgerti direttamente alla farmacia o al tuo medico di fiducia.</em></div>
		---
		:posta_inviata: FORMATO DI RISPOSTA (JSON):
		{
		  "data": "[DATA ATTUALE]",
		  "farmacia": {
			"nome": "[NOME FARMACIA]",
			"indirizzo": "[INDIRIZZO FARMACIA]",
			"telefono": "[NUMERO TELEFONO FARMACIA]",
			"whatsapp": "[NUMERO WHATSAPP FARMACIA]",
			"orari": "[ORARI APERTURA FARMACIA]",
			"promozioni": "[PROMOZIONI ATTIVE]",
			"eventi": "[GIORNATE EVENTO]"
		  },
		  "utente": {
			"sesso": "[UOMO/DONNA]",
			"eta": "[X]",
			"stile_vita": "[...]"
		  },
		  "categoria_contesto": "[Categoria rilevante se applicabile]",
		  "tipo_input": "[testo / immagine / entrambi]",
		  "risposta_html": "Testo in HTML con paragrafi, eventuale <strong>grassetto</strong>. Tono rassicurante, professionale e umano. Scrivi sempre come Farmacia Giovinazzi. Includi il disclaimer finale quando necessario."
		}
		EOT;
	}

	// $prompt_sys .= print_r($pharma, 1);
	// $prompt_sys .= print_r($user, 1);
	// $prompt_sys .= print_r($user_data, 1);

	return ltrim_tab($prompt_sys);
}

function openai_daily_pill_prompt( string $date, string $category ){
	if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
	if( ! isset($category) OR empty($category) ) $category = get_random_profiling_category();

	$date = date('d/m/Y', strtotime($date));

	$prompt_sys = <<<EOT
		Agisci come un esperto di benessere e lifestyle che lavora per la FARMACIA GIOVINAZZI di TERRACINA.
		Oggi è il {$date} e devi creare una pillola del benessere giornaliera da mostrare agli utenti dell'app AssistenteFarmacia.it.
		
		### Categoria selezionata: {$category}
		
		### Obiettivo:
		Genera una sola pillola del benessere per la categoria indicata, seguendo queste regole precise:
		
		#### Contenuto:
		- Deve essere motivazionale, concreto e universale, adatta a TUTTI gli utenti
		- Deve considerare il contesto geografico (Terracina, inverno, clima freddo)
		- Deve essere coerente con la categoria selezionata
		- Deve essere adatta a un pubblico generalista di tutte le età e stili di vita
		- Il tono deve essere positivo, chiaro, ispirazionale, non tecnico né clinico
		- Non fornire mai consigli medici, terapeutici o legati a diagnosi o trattamento di patologie
		- Evita qualsiasi contenuto che possa essere interpretato come prescrizione, cura o automedicazione
		
		#### Struttura OBBLIGATORIA:
		- Includi un titolo con emoji, che sia chiaro e inviti alla lettura
		- Il testo dev'essere suddiviso in paragrafi usando tag HTML <p>
		- USA SEMPRE sottotitoli con tag HTML <h2> per migliorare la leggibilità
		- OGNI sottotitolo deve iniziare con un'emoji appropriata
		- Evidenzia concetti chiave usando HTML <strong> per il grassetto
		- La lunghezza DEVE essere SEMPRE compresa tra 800 e 1500 caratteri (obbligatorio)
		- Includi un riassunto del testo di lunghezza 200 caratteri, senza html e paragrafi, puoi usare emoji
		
		#### Formato HTML richiesto:
		- Usa SEMPRE HTML e NON markdown
		- Struttura: <p> per paragrafi, <h2> per sottotitoli, <strong> per grassetto
		- Esempio sottotitolo: <h2>🌟 Perché è importante</h2>
		
		#### Output richiesto:
		Restituisci tutto in formato JSON con questa struttura:
		{
		"day": "2025-08-01",
		"category": "{$category}",
		"title": "🚶‍♂️ Titolo motivazionale con emoji",
		"content": "<p>Primo paragrafo con <strong>elementi in grassetto</strong>.</p><h2>🌟 Primo sottotitolo con emoji</h2><p>Secondo paragrafo...</p><h2>💡 Secondo sottotitolo con emoji</h2><p>Terzo paragrafo...</p>",
		"excerpt": "Breve riassunto senza html e non in paragrafi."
		}
		EOT;

	return ltrim_tab($prompt_sys);
}

function openai_generate_daily_pill( string $date, string $category ){
	if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
	if( ! isset($category) OR empty($category) ) $category = get_random_profiling_category();

	$prompt_sys = openai_daily_pill_prompt( $date, $category );
	$prompt_user = 'Genera una pillola del benessere giornaliera';

	$args = [
		'model' => 'gpt-4o',
		'temperature' => 0.2,
		'max_tokens'  => 800,
	];

	// Chiamata API
	$response = openai_call_simple_result($prompt_user, $prompt_sys, $args);

	// Controllo prima decodifica
	if (!is_array($response) && !is_object($response)) {
		return false;
	}

	// Decodifica e ricodifica per uniformità
	$json = json_encode($response);
	if (json_last_error() !== JSON_ERROR_NONE) {
		return false;
	}

	// Estrazione sicura del contenuto (es. {"day": "...", "title": "...", ...})
	$pill_raw = safe_extract_openai_content($json);
	if (!$pill_raw) {
		return false;
	}

	// Se è già array (safe_extract_openai_content può farlo), usalo direttamente
	if (is_array($pill_raw)) {
		return $pill_raw;
	}

	// Altrimenti, prova a decodificarlo
	$decoded = json_decode($pill_raw, true);
	if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
		return $decoded;
	}

	return false;
}

function get_quiz_categories(){
	$current_month = (int) date('n');

	// Inverno: dicembre, gennaio, febbraio
	if ( in_array($current_month, [12,1,2]) ) {
		return [
			'Difese immunitarie e prevenzione influenze',
			'Pelle secca e screpolata (viso e mani)',
			'Vitamina D e sole che non esiste',
			'Idratazione e benessere invernale',
			'Alimentazione energetica per il freddo',
			'Recupero energie dopo i malanni stagionali',
			'Benessere delle vie respiratorie',
			'Riposo e qualità del sonno in inverno',
			'Gestione dello stress delle feste',
			'Cura delle articolazioni con il freddo',
		];
	}

	// Estate: giugno, luglio, agosto
	if ( in_array($current_month, [6,7,8]) ) {
		return [
			'Pelle e sole',
			'Stress estivo', 
			'Digestione in vacanza',
			'Idratazione e benessere',
			'Protezione solare avanzata',
			'Benessere in viaggio',
			'Energia e vitalità estiva',
			'Relax e riposo',
			'Immunità e difese naturali',
			'Benessere della pelle sensibile'
		];
	}

	// Autunno: settembre, ottobre, novembre
	if ( in_array($current_month, [9,10,11]) ) {
		return [
			'Pelle e cambi di stagione',
			'Rinforzo delle difese immunitarie',
			'Alimentazione e vitamine autunnali',
			'Gestione dello stress da rientro (scuola/lavoro)',
			'Benessere intestinale con il cambio di dieta',
			'Idratazione con clima fresco e secco',
			'Sonno e routine autunnale',
			'Allergie stagionali (pollini, muffe, umidità)',
			'Energia contro la stanchezza autunnale',
			'Cura dei capelli con il cambio di stagione',
		];
	}

	// Primavera: marzo, aprile, maggio
	return [
		'Depurazione e detox primaverile',
		'Stanchezza primaverile',
		'Allergie stagionali',
		'Benessere intestinale',
		'Pelle e rinnovamento cellulare',
		'Rinforzo delle difese immunitarie',
		'Attività fisica e ripresa del movimento',
		'Umore ed equilibrio emotivo',
		'Idratazione e leggerezza',
		'Preparazione della pelle al sole',
	];
}


function get_random_quiz_category(): string {
	$categories = get_quiz_categories();
	return $categories[array_rand($categories)];
}

function openai_daily_quiz_prompt( string $topic, string $date ){
	if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
	$date = date('d/m/Y', strtotime($date));
	if ( ! isset($topic) OR empty($topic) ) $topic = get_random_quiz_category();

	$prompt_sys = <<<EOT
		Agisci come esperto in educazione sanitaria e divulgazione scientifica accessibile. Il tuo compito è creare un "Quiz del giorno del benessere" destinato a un'app per farmacie. In questo caso dobbiamo creare un QUIZ per la FARMACIA GIOVINAZZI di Terracina oggi è {$date} quindi poni attenzione al territorio e alla data.
		Il tono deve essere semplice, rassicurante, positivo e comprensibile da chiunque, anche senza conoscenze mediche. L'obiettivo non è testare la cultura generale, ma **educare le persone aiutandole a capire meglio il proprio corpo e i propri bisogni**, in base al tema scelto del giorno.
		Tema del giorno: {$topic}
		Struttura richiesta del contenuto:
		1. **Titolo del quiz** → Breve, coinvolgente, legato al tema.
		2. **Introduzione al quiz** → Almeno 3 righe. Spiega cosa imparerà l'utente rispondendo alle 5 domande.
		3. **5 domande a scelta multipla** → Ogni domanda con 4 risposte (A, B, C, D). Le risposte devono aiutare a profilare la persona, non testare nozioni.
		4. **4 profili risultanti** → Uno per ciascuna lettera (A, B, C, D). Ogni profilo deve avere una descrizione di **almeno 5 righe**, precisa, utile, mai banale.
		5. **Mini guida con consigli del giorno** →
			- Introduzione di almeno 3 righe
			- 3 consigli pratici sotto forma di elenco
			- Conclusione di almeno 3 righe
		6. **Prodotti consigliati per ciascun profilo (massimo 3 per profilo)** → Nome generico del prodotto, uso comune (es. "Tisana drenante gambe leggere", "Integratore rilassante serale", "Gel doposole lenitivo"). Evita nomi di brand.
		Tono: divulgativo, empatico, autorevole ma accessibile.
		Evita linguaggio tecnico o termini che possono intimidire.
		Tutti i contenuti devono essere coerenti, concreti e originali.
		Genera un output pronto da usare in app mobile in formato JSON con questa struttura:
		{
			"header": {
				"title": "[TITOLO QUIZ]",
				"description": "[DESCRIZIONE QUIZ]",
				"steps": 5
			},
			"questions": [
				{
					"id": "q1",
					"text": "[TESTO DOMANDA 1]",
					"answers": {
						"A": "[TESTO RISPOSTA A]",
						"B": "[TESTO RISPOSTA B]",
						"C": "[TESTO RISPOSTA C]",
						"D": "[TESTO RISPOSTA D]"
					}
				},
				{
					"id": "q2",
					"text": "[TESTO DOMANDA 2]",
					"answers": {
						"A": "[TESTO RISPOSTA A]",
						"B": "[TESTO RISPOSTA B]",
						"C": "[TESTO RISPOSTA C]",
						"D": "[TESTO RISPOSTA D]"
					}
				},
				{
					"id": "q3",
					"text": "[TESTO DOMANDA 3]",
					"answers": {
						"A": "[TESTO RISPOSTA A]",
						"B": "[TESTO RISPOSTA B]",
						"C": "[TESTO RISPOSTA C]",
						"D": "[TESTO RISPOSTA D]"
					}
				},
				{
					"id": "q4",
					"text": "[TESTO DOMANDA 4]",
					"answers": {
						"A": "[TESTO RISPOSTA A]",
						"B": "[TESTO RISPOSTA B]",
						"C": "[TESTO RISPOSTA C]",
						"D": "[TESTO RISPOSTA D]"
					}
				},
				{
					"id": "q5",
					"text": "[TESTO DOMANDA 5]",
					"answers": {
						"A": "[TESTO RISPOSTA A]",
						"B": "[TESTO RISPOSTA B]",
						"C": "[TESTO RISPOSTA C]",
						"D": "[TESTO RISPOSTA D]"
					}
				}
			],
			"profiles": {
				"A": {
					"title": "[TITOLO PROFILO A]",
					"description": "[DESCRIZIONE PROFILO A]"
				},
				"B": {
					"title": "[TITOLO PROFILO B]",
					"description": "[DESCRIZIONE PROFILO B]"
				},
				"C": {
					"title": "[TITOLO PROFILO C]",
					"description": "[DESCRIZIONE PROFILO C]"
				},
				"D": {
					"title": "[TITOLO PROFILO D]",
					"description": "[DESCRIZIONE PROFILO D]"
				}
			},
			"mini_guide": {
				"introduction": "[MINI GUIDA TESTO INTRODUZIONE]",
				"advise": [
					"[MINI GUIDA TESTO CONSIGLIO PRATICO 1]",
					"[MINI GUIDA TESTO CONSIGLIO PRATICO 2]",
					"[MINI GUIDA TESTO CONSIGLIO PRATICO 3]."
				],
				"conclusion": "[MINI GUIDA TESTO CONCLUSIONE]"
			},
			"recommended_products": {
				"A": [
					"[PRODOTO CONSIGLIATO 1 PER PROFILO A]",
					"[PRODOTO CONSIGLIATO 1 PER PROFILO A]",
					"[PRODOTO CONSIGLIATO 1 PER PROFILO A]"
				],
				"B": [
					"[PRODOTO CONSIGLIATO 1 PER PROFILO B]",
					"[PRODOTO CONSIGLIATO 1 PER PROFILO B]",
					"[PRODOTO CONSIGLIATO 1 PER PROFILO B]"
				],
				"C": [
					"[PRODOTO CONSIGLIATO 1 PER PROFILO C]",
					"[PRODOTO CONSIGLIATO 1 PER PROFILO C]",
					"[PRODOTO CONSIGLIATO 1 PER PROFILO C]"
				],
				"D": [
					"[PRODOTO CONSIGLIATO 1 PER PROFILO D]",
					"[PRODOTO CONSIGLIATO 1 PER PROFILO D]",
					"[PRODOTO CONSIGLIATO 1 PER PROFILO D]"
				]
			}
		}
		EOT;

		// ```json
		// ```

	return ltrim_tab($prompt_sys);
}

function openai_weekly_challenge( string $date ){
	if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
	$dates_range = get_week_range($date);
	$start_date = date('d/m/Y', strtotime($dates_range[0]));
	$end_date = date('d/m/Y', strtotime($dates_range[1]));
	$points = get_option('point--challenge_daily', 1);

	// VERSIONE ORIGINALE
	// $prompt_sys = <<<EOT
	// 	Sei un esperto di benessere che crea sfide motivazionali settimanali sempre diverse per utenti di un’app di farmacia. La sfida che stai per generare è per i clienti della FARMACIA GIOVINAZZI di TERRACINA e sarà attiva dal {$start_date} al {$end_date} quindi tieni presente del contesto e della stagionalità. Ogni sfida dura 7 giorni ed è pensata per migliorare abitudini salutari quotidiane in modo semplice, pratico e accessibile. La sfida deve avere un linguaggio chiaro, positivo e ispirazionale, adatto a un pubblico generalista. I contenuti verranno visualizzati in un’app mobile e premiati con {$points} punto al giorno completato. Genera una nuova Sfida del Benessere per questa settimana. Rispondi in formato JSON con i seguenti campi: { “title”: “stringa breve con emoji”, “description”: “introduzione ispirazionale (3-5 righe)“, “instructions”: [“almeno 3 step pratici”], “reward”: “breve testo con spiegazione dei punti benessere”, “icon”: “nome icona FontAwesome (es. fa-seedling)” } La sfida deve essere adatta a chiunque (nessun attrezzo o spazio necessario), semplice da svolgere e utile per salute fisica o mentale. esempio di output: { "title": "Idratazione Consapevole :goccia:", "description": "A Terracina, sotto il sole di fine luglio, il caldo si fa sentire! Idratarsi ogni giorno è fondamentale per il benessere di tutto il corpo: migliora l’energia, la digestione, la pelle e la concentrazione. Questa settimana, impara a bere nel modo giusto con una semplice ma potente abitudine quotidiana.", "instructions": [ "Appena sveglio/a, bevi un bicchiere d’acqua a temperatura ambiente.", "Porta sempre con te una bottiglia e bevi piccoli sorsi durante la giornata, senza aspettare di avere sete.", "Prima di andare a dormire, chiediti: oggi ho bevuto almeno 1,5 litri d’acqua? Se sì, segna la giornata come completata!" ], "reward": "Ogni giornata completata ti fa guadagnare {$points} punto benessere. Portando a termine almeno 5 giorni otterrai 5 punti extra!", "icon": "fa-glass-water" }
	// EOT;

	// CORRETTO E SISTEMATO VIA AI 
	$prompt_sys = <<<PROMPT
		Sei un esperto di benessere e motivazione che crea sfide settimanali sempre diverse per gli utenti di un’app di farmacia.
		La sfida che stai per generare è dedicata ai clienti della FARMACIA GIOVINAZZI di TERRACINA e sarà attiva dal {$start_date} al {$end_date}: tieni quindi conto del contesto locale e della stagionalità.

		Ogni sfida dura 7 giorni e ha l’obiettivo di migliorare piccole abitudini quotidiane legate al benessere fisico o mentale, in modo semplice, pratico e accessibile a tutti (nessun attrezzo o spazio specifico richiesto).

		Usa un linguaggio chiaro, positivo e ispirazionale, adatto a un pubblico generalista.
		I contenuti verranno visualizzati in un’app mobile e premiati con {$points} punto benessere al giorno completato.

		### 🎯 Istruzioni per la generazione
		Genera una nuova Sfida del Benessere per questa settimana.
		Rispondi **solo** in formato JSON con i seguenti campi:

		{
		"title": "stringa breve con emoji",
		"description": "introduzione ispirazionale (3–5 righe)",
		"instructions": ["almeno 3 step pratici e quotidiani"],
		"reward": "breve testo con spiegazione dei punti benessere",
		"icon": "nome icona FontAwesome (es. fa-seedling)"
		}

		La sfida deve essere realistica, utile e coerente con la stagione (es. idratazione in estate, difese immunitarie in autunno, movimento leggero in inverno).

		### 🧾 Esempio di output
		(non copiarlo, serve solo come riferimento di formato)
		{
		"title": "Idratazione Consapevole 💧",
		"description": "A Terracina, sotto il sole di fine luglio, il caldo si fa sentire! Idratarsi ogni giorno è fondamentale per il benessere di tutto il corpo: migliora energia, digestione, pelle e concentrazione. Questa settimana, impara a bere nel modo giusto con una semplice ma potente abitudine quotidiana.",
		"instructions": [
			"Appena sveglio/a, bevi un bicchiere d’acqua a temperatura ambiente.",
			"Porta sempre con te una bottiglia e bevi piccoli sorsi durante la giornata.",
			"Prima di dormire, chiediti: oggi ho bevuto almeno 1,5 litri d’acqua?"
		],
		"reward": "Ogni giornata completata ti fa guadagnare {$points} punto benessere. Portando a termine almeno 5 giorni otterrai 5 punti extra!",
		"icon": "fa-glass-water"
		}
	PROMPT;

	return ltrim_tab($prompt_sys);
}

/**
 * Minifica un JSON rimuovendo spazi e a capo non necessari
 */
function minify_json($json_string) {
	// Rimuove commenti (se presenti)
	$json_string = preg_replace('/\/\*.*?\*\//s', '', $json_string);
	
	// Rimuove spazi extra e a capo, mantenendo la struttura
	$json_string = preg_replace('/\s+/', ' ', $json_string);
	
	// Rimuove spazi prima e dopo caratteri specifici
	$json_string = preg_replace('/\s*([{}[\],:])\s*/', '$1', $json_string);
	
	// Rimuove spazi extra all'inizio e alla fine
	$json_string = trim($json_string);
	
	return $json_string;
}

function openai_generate_daily_quiz( string $topic, string $date ){
	if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');
	if ( ! isset($topic) OR empty($topic) ) $topic = get_random_quiz_category();

	$prompt_sys = openai_daily_quiz_prompt($topic, $date);
	$prompt_user = 'Genera il "Quiz del giorno del benessere".';

	$args = [
		'model' => 'gpt-4o',
		'temperature' => 0.2,
		'max_tokens'  => 1500,
	];

	// Chiamata API
	$response = openai_call_simple_result($prompt_user, $prompt_sys, $args);

	// Controllo prima decodifica
	if (!is_array($response) && !is_object($response)) {
		write_log("openai_generate_daily_quiz: Risposta non è array/oggetto");
		return false;
	}

	// Decodifica e ricodifica per uniformità
	$json = json_encode($response);
	if (json_last_error() !== JSON_ERROR_NONE) {
		write_log("openai_generate_daily_quiz: Errore JSON encode: " . json_last_error_msg());
		return false;
	}

	// Estrazione sicura del contenuto (es. {"day": "...", "title": "...", ...})
	$quiz_raw = safe_extract_openai_content($json);
	if (!$quiz_raw) {
		write_log("openai_generate_daily_quiz: safe_extract_openai_content ha restituito false");
		return false;
	}

	// Se è già array (safe_extract_openai_content può farlo), usalo direttamente
	if (is_array($quiz_raw)) {
		write_log("openai_generate_daily_quiz: Contenuto estratto è già array");
		return $quiz_raw;
	}

	// Minifica il JSON per ridurre i token
	$quiz_raw = minify_json($quiz_raw);
	write_log("openai_generate_daily_quiz: JSON minificato, lunghezza ridotta da " . strlen($quiz_raw) . " caratteri");

	// Altrimenti, prova a decodificarlo
	$decoded = json_decode($quiz_raw, true);
	if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
		write_log("openai_generate_daily_quiz: Contenuto decodificato con successo");
		return $decoded;
	}

	write_log("openai_generate_daily_quiz: Errore decodifica finale: " . json_last_error_msg());
	write_log("openai_generate_daily_quiz: Contenuto grezzo: " . substr($quiz_raw, 0, 500));
	return false;
}

function openai_generate_weekly_challenge( string $date ){
	if( ! isset($date) OR empty($date) ) $date = date('Y-m-d');

	$prompt_sys = openai_weekly_challenge($date);
	$prompt_user = 'Genera la "sfida del benessere".';

	$args = [
		'model' => 'gpt-4o',
		'temperature' => 0.2,
		'max_tokens'  => 1500,
	];

	// Chiamata API
	$response = openai_call_simple_result($prompt_user, $prompt_sys, $args);

	// Controllo prima decodifica
	if (!is_array($response) && !is_object($response)) {
		write_log("openai_generate_weekly_challenge: Risposta non è array/oggetto");
		return false;
	}

	// Decodifica e ricodifica per uniformità
	$json = json_encode($response);
	if (json_last_error() !== JSON_ERROR_NONE) {
		write_log("openai_generate_weekly_challenge: Errore JSON encode: " . json_last_error_msg());
		return false;
	}

	// Estrazione sicura del contenuto (es. {"title": "...", "description": "...", ...})
	$challenge_raw = safe_extract_openai_content($json);
	if (!$challenge_raw) {
		write_log("openai_generate_weekly_challenge: safe_extract_openai_content ha restituito false");
		return false;
	}

	// Se è già array (safe_extract_openai_content può farlo), usalo direttamente
	if (is_array($challenge_raw)) {
		write_log("openai_generate_weekly_challenge: Contenuto estratto è già array");
		return $challenge_raw;
	}

	// Minifica il JSON per ridurre i token
	$challenge_raw = minify_json($challenge_raw);
	write_log("openai_generate_weekly_challenge: JSON minificato, lunghezza ridotta da " . strlen($challenge_raw) . " caratteri");

	// Altrimenti, prova a decodificarlo
	$decoded = json_decode($challenge_raw, true);
	if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
		write_log("openai_generate_weekly_challenge: Contenuto decodificato con successo");
		return $decoded;
	}

	write_log("openai_generate_weekly_challenge: Errore decodifica finale: " . json_last_error_msg());
	write_log("openai_generate_weekly_challenge: Contenuto grezzo: " . substr($challenge_raw, 0, 500));
	return false;
}
