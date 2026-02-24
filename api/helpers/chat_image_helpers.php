<?php
/**
 * Helper per la gestione delle immagini del chat
 * Salva le immagini in base64 in file separati per riutilizzarle nello storico
 */

/**
 * Salva un'immagine base64 in un file e restituisce il percorso
 */
function save_chat_image($base64_data, $session_id, $message_id) {
    try {
        // Crea la directory per la sessione se non esiste
        $session_dir = "uploads/chat_images/" . $session_id;
        if (!is_dir($session_dir)) {
            mkdir($session_dir, 0755, true);
        }

        // Genera un nome file unico
        $filename = "msg_{$message_id}_" . uniqid() . ".json";
        $filepath = $session_dir . "/" . $filename;

        // Prepara i dati da salvare
        $image_data = [
            'base64_data' => $base64_data,
            'created_at' => date('Y-m-d H:i:s'),
            'message_id' => $message_id,
            'session_id' => $session_id
        ];

        // Salva il file JSON
        $result = file_put_contents($filepath, json_encode($image_data, JSON_UNESCAPED_UNICODE));
        
        if ($result === false) {
            throw new Exception("Impossibile salvare l'immagine");
        }

        return $filepath;
    } catch (Exception $e) {
        error_log("Errore nel salvataggio immagine chat: " . $e->getMessage());
        return false;
    }
}

/**
 * Recupera un'immagine salvata dal percorso
 */
function load_chat_image($filepath) {
    try {
        if (!file_exists($filepath)) {
            return false;
        }

        $image_data = json_decode(file_get_contents($filepath), true);
        
        if (!$image_data || !isset($image_data['base64_data'])) {
            return false;
        }

        return $image_data['base64_data'];
    } catch (Exception $e) {
        error_log("Errore nel caricamento immagine chat: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina un'immagine salvata
 */
function delete_chat_image($filepath) {
    try {
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return true;
    } catch (Exception $e) {
        error_log("Errore nell'eliminazione immagine chat: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina tutte le immagini di una sessione
 */
function delete_chat_session_images($session_id) {
    try {
        $session_dir = "uploads/chat_images/" . $session_id;
        
        if (!is_dir($session_dir)) {
            return true;
        }

        $files = glob($session_dir . "/*.json");
        foreach ($files as $file) {
            unlink($file);
        }

        // Rimuovi la directory se vuota
        if (is_dir($session_dir) && count(glob($session_dir . "/*")) === 0) {
            rmdir($session_dir);
        }

        return true;
    } catch (Exception $e) {
        error_log("Errore nell'eliminazione immagini sessione: " . $e->getMessage());
        return false;
    }
}

/**
 * Converte un'immagine base64 in formato data URL per OpenAI
 */
function convert_base64_to_data_url($base64_data, $format = 'jpeg') {
    // Se è già un data URL, restituiscilo
    if (strpos($base64_data, 'data:') === 0) {
        return $base64_data;
    }

    // Altrimenti, crea un data URL
    $mime_type = 'image/' . strtolower($format);
    return 'data:' . $mime_type . ';base64,' . $base64_data;
}

/**
 * Valida e pulisce i dati immagine base64
 */
function validate_and_clean_image_data($image_data) {
    // Se è un data URL, estrai solo la parte base64
    if (strpos($image_data, 'data:') === 0) {
        $parts = explode(',', $image_data, 2);
        if (count($parts) === 2) {
            return $parts[1];
        }
    }

    // Se è già base64 puro, restituiscilo
    return $image_data;
}

/**
 * Ottiene statistiche sulle immagini salvate
 */
function get_chat_images_stats() {
    try {
        $base_dir = "uploads/chat_images";
        $total_files = 0;
        $total_size = 0;
        $sessions = [];

        if (!is_dir($base_dir)) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'sessions' => 0
            ];
        }

        $session_dirs = glob($base_dir . "/*", GLOB_ONLYDIR);
        
        foreach ($session_dirs as $session_dir) {
            $session_id = basename($session_dir);
            $files = glob($session_dir . "/*.json");
            $session_size = 0;
            
            foreach ($files as $file) {
                $session_size += filesize($file);
            }
            
            $sessions[$session_id] = [
                'files' => count($files),
                'size' => $session_size
            ];
            
            $total_files += count($files);
            $total_size += $session_size;
        }

        return [
            'total_files' => $total_files,
            'total_size' => $total_size,
            'sessions' => count($sessions),
            'session_details' => $sessions
        ];
    } catch (Exception $e) {
        error_log("Errore nel calcolo statistiche immagini: " . $e->getMessage());
        return false;
    }
} 