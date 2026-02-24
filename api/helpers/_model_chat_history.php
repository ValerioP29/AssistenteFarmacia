<?php

class ChatHistoryModel {
    
    /**
     * Inserisce un nuovo messaggio nello storico
     */
    public static function insert(array $data): int {
        global $pdo;
        
        $sql = "INSERT INTO jta_chat_history (
            user_id, pharma_id, session_id, role, content, 
            content_type, image_data, tokens_used, model_used, created_at
        ) VALUES (
            :user_id, :pharma_id, :session_id, :role, :content,
            :content_type, :image_data, :tokens_used, :model_used, :created_at
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':pharma_id' => $data['pharma_id'],
            ':session_id' => $data['session_id'],
            ':role' => $data['role'],
            ':content' => $data['content'],
            ':content_type' => $data['content_type'] ?? 'text',
            ':image_data' => $data['image_data'] ?? null,
            ':tokens_used' => $data['tokens_used'] ?? 0,
            ':model_used' => $data['model_used'] ?? 'gpt-4o',
            ':created_at' => date('Y-m-d H:i:s')
        ]);
        
        return (int) $pdo->lastInsertId();
    }
    
    /**
     * Recupera lo storico di una sessione specifica
     */
    public static function getSessionHistory(int $user_id, string $session_id, int $limit = 50): array {
        global $pdo;
        
        $sql = "SELECT * FROM jta_chat_history 
                WHERE user_id = :user_id AND session_id = :session_id 
                ORDER BY created_at ASC 
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Recupera lo storico recente di un utente (ultime N conversazioni)
     */
    public static function getRecentHistory(int $user_id, int $limit = 20): array {
        global $pdo;
        
        $sql = "SELECT * FROM jta_chat_history 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Elimina lo storico di una sessione specifica
     */
    public static function deleteSession(int $user_id, string $session_id): bool {
        global $pdo;
        
        $sql = "DELETE FROM jta_chat_history 
                WHERE user_id = :user_id AND session_id = :session_id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user_id,
            ':session_id' => $session_id
        ]);
    }
    
    /**
     * Elimina tutto lo storico di un utente
     */
    public static function deleteUserHistory(int $user_id): bool {
        global $pdo;
        
        $sql = "DELETE FROM jta_chat_history WHERE user_id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':user_id' => $user_id]);
    }
    
    /**
     * Conta i messaggi di una sessione
     */
    public static function countSessionMessages(int $user_id, string $session_id): int {
        global $pdo;
        
        $sql = "SELECT COUNT(*) FROM jta_chat_history 
                WHERE user_id = :user_id AND session_id = :session_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':session_id' => $session_id
        ]);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Genera un ID di sessione unico
     */
    public static function generateSessionId(): string {
        return uniqid('chat_', true);
    }
    
    /**
     * Aggiorna un messaggio esistente
     */
    public static function update(int $id, array $data): bool {
        global $pdo;
        
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        
        $sql = "UPDATE jta_chat_history SET " . implode(", ", $fields) . " WHERE id = :id";
        $data['id'] = $id;
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Converte lo storico del database nel formato richiesto da OpenAI
     */
    public static function formatForOpenAI(array $history): array {
        $messages = [];
        
        foreach ($history as $entry) {
            $message = [
                'role' => $entry['role'],
                'content' => $entry['content']
            ];
            
            // Se c'è un'immagine, caricala dal file e formatta il contenuto
            if ($entry['content_type'] === 'image' || $entry['content_type'] === 'mixed') {
                $image_data = json_decode($entry['image_data'], true);
                if ($image_data && isset($image_data['filepath'])) {
                    // Carica l'immagine dal file
                    $base64_data = load_chat_image($image_data['filepath']);
                    if ($base64_data) {
                        $data_url = convert_base64_to_data_url($base64_data);
                        $message['content'] = [
                            ['type' => 'text', 'text' => $entry['content']],
                            ['type' => 'image_url', 'image_url' => ['url' => $data_url]]
                        ];
                    }
                } elseif ($image_data && isset($image_data['url'])) {
                    // Formato legacy (URL diretto)
                    $message['content'] = [
                        ['type' => 'text', 'text' => $entry['content']],
                        ['type' => 'image_url', 'image_url' => ['url' => $image_data['url']]]
                    ];
                }
            }
            
            $messages[] = $message;
        }
        
        return $messages;
    }
}

/**
 * Funzioni helper per la gestione dello storico
 */

function save_chat_message(int $user_id, int $pharma_id, string $session_id, string $role, string $content, string $content_type = 'text', $image_data = null, int $tokens_used = 0, string $model_used = 'gpt-4o'): int {
    // Se c'è un'immagine, salvala in un file separato
    $image_filepath = null;
    if ($image_data && ($content_type === 'image' || $content_type === 'mixed')) {
        $base64_data = validate_and_clean_image_data($image_data);
        if ($base64_data) {
            // Prima inserisci il messaggio per ottenere l'ID
            $message_id = ChatHistoryModel::insert([
                'user_id' => $user_id,
                'pharma_id' => $pharma_id,
                'session_id' => $session_id,
                'role' => $role,
                'content' => $content,
                'content_type' => $content_type,
                'image_data' => null, // Temporaneamente null
                'tokens_used' => $tokens_used,
                'model_used' => $model_used
            ]);
            
            // Salva l'immagine e ottieni il percorso
            $image_filepath = save_chat_image($base64_data, $session_id, $message_id);
            
            // Aggiorna il messaggio con il percorso dell'immagine
            if ($image_filepath) {
                ChatHistoryModel::update($message_id, [
                    'image_data' => json_encode(['filepath' => $image_filepath])
                ]);
            }
            
            return $message_id;
        }
    }
    
    // Per messaggi senza immagini, inserisci normalmente
    return ChatHistoryModel::insert([
        'user_id' => $user_id,
        'pharma_id' => $pharma_id,
        'session_id' => $session_id,
        'role' => $role,
        'content' => $content,
        'content_type' => $content_type,
        'image_data' => $image_data ? json_encode($image_data) : null,
        'tokens_used' => $tokens_used,
        'model_used' => $model_used
    ]);
}

function get_chat_history(int $user_id, string $session_id, int $limit = 50): array {
    return ChatHistoryModel::getSessionHistory($user_id, $session_id, $limit);
}

function get_chat_history_for_openai(int $user_id, string $session_id, int $limit = 50): array {
    $history = ChatHistoryModel::getSessionHistory($user_id, $session_id, $limit);
    return ChatHistoryModel::formatForOpenAI($history);
}

function delete_chat_session(int $user_id, string $session_id): bool {
    // Prima elimina le immagini della sessione
    delete_chat_session_images($session_id);
    
    // Poi elimina i messaggi dal database
    return ChatHistoryModel::deleteSession($user_id, $session_id);
}

function generate_chat_session_id(): string {
    return ChatHistoryModel::generateSessionId();
} 