<?php

class RequestModel {

	public const PENDING     = 0; // In attesa
	public const PROCESSING  = 1; // In lavorazione
	public const COMPLETED   = 2; // Completata
	public const REJECTED    = 3; // Rifiutata
	public const CANCELED    = 4; // Annullata

    /**
     * Inserisce una nuova richiesta nella tabella jta_requests
     *
     * @param array $data Associativo con chiavi: request_type, user_id, pharma_id, message, metadata, status (opzionale)
     * @return int ID della richiesta inserita
     */
    public static function insert(array $data) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO jta_requests (
                    request_type, user_id, pharma_id, message,
                    metadata, status, created_at
                ) VALUES (
                    :request_type, :user_id, :pharma_id, :message,
                    :metadata, :status, :created_at
                )
            ");

            $stmt->execute([
                ':request_type' => $data['request_type'],
                ':user_id'      => $data['user_id'],
                ':pharma_id'    => $data['pharma_id'],
                ':message'      => $data['message'],
                ':metadata'     => json_encode( (isset($data['metadata']) ? $data['metadata'] : (object) [] ), JSON_UNESCAPED_UNICODE),
                ':status'       => isset($data['status']) ? $data['status'] : 0,
                ':created_at'   => date('Y-m-d H:i:s')
            ]);

            return (int) $pdo->lastInsertId();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function delete($request_id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("UPDATE jta_requests SET deleted_at = :deleted_at WHERE id = :id");
            return $stmt->execute([
                ':deleted_at' => date('Y-m-d H:i:s'),
                ':id' => $request_id
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function updateStatus($request_id, $status) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("UPDATE jta_requests SET status = :status, updated_at = NOW() WHERE id = :id");
            return $stmt->execute([
                ':status' => $status,
                ':id' => $request_id
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function update($request_id, array $params) {
        global $pdo;

        try {
            if (empty($params)) return false;

            $fields = [];
            $values = [];

            foreach ($params as $key => $value) {
                $fields[] = "$key = :$key";
                $values[":$key"] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            }

            // $fields[] = "updated_at = NOW()";
            $values[":id"] = $request_id;

            $sql = "UPDATE jta_requests SET " . implode(", ", $fields) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return array|false
     */
    public static function findById($request_id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT * FROM jta_requests WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $request_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result : false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return array
     */
    public static function findByUser($user_id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT * FROM jta_requests WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY created_at DESC");
            $stmt->execute([':user_id' => $user_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $results ? $results : [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @return array
     */
    public static function findActiveByPharma($pharma_id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT * FROM jta_requests WHERE pharma_id = :pharma_id AND deleted_at IS NULL ORDER BY created_at DESC");
            $stmt->execute([':pharma_id' => $pharma_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $results ? $results : [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @return array|false
     */
    public static function findLatestByUserAndType($user_id, $type) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT * FROM jta_requests WHERE user_id = :user_id AND request_type = :type AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([
                ':user_id' => $user_id,
                ':type' => $type
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result : false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Restituisce tutte le richieste per un utente e una farmacia, con filtri opzionali su type e status (singolo valore o array), con limit opzionale
     *
     * @param int $user_id
     * @param int $pharma_id
     * @param string|array|null $type Tipo o array di tipi di richiesta
     * @param int|array|null $status Stato o array di stati
     * @param int|null $limit Numero massimo di risultati
     * @return array Lista di richieste oppure array vuoto
     */
    public static function getByUserAndPharma(int $user_id, int $pharma_id, $type = null, $status = null, ?int $limit = null): array
    {
        global $pdo;

        try {
            $sql = "SELECT * FROM jta_requests WHERE pharma_id = :pharma_id AND user_id = :user_id AND deleted_at IS NULL";
            $params = [
                ':pharma_id' => $pharma_id,
                ':user_id'   => $user_id,
            ];

            // Gestione tipi multipli
            if ($type !== null) {
                if (is_array($type) && count($type) > 0) {
                    $placeholders = [];
                    foreach ($type as $index => $t) {
                        $ph = ":type_$index";
                        $placeholders[] = $ph;
                        $params[$ph] = $t;
                    }
                    $sql .= " AND request_type IN (" . implode(',', $placeholders) . ")";
                } elseif (is_string($type)) {
                    $sql .= " AND request_type = :type";
                    $params[':type'] = $type;
                }
            }

            // Gestione status multipli
            if ($status !== null) {
                if (is_array($status) && count($status) > 0) {
                    $placeholders = [];
                    foreach ($status as $index => $s) {
                        $ph = ":status_$index";
                        $placeholders[] = $ph;
                        $params[$ph] = $s;
                    }
                    $sql .= " AND status IN (" . implode(',', $placeholders) . ")";
                } elseif (is_int($status)) {
                    $sql .= " AND status = :status";
                    $params[':status'] = $status;
                }
            }

            $sql .= " ORDER BY created_at DESC";

            if ($limit !== null) {
                $sql .= " LIMIT :limit";
            }

            $stmt = $pdo->prepare($sql);

            foreach ($params as $key => $val) {
                if (strpos($key, 'status') === 0 || strpos($key, 'limit') === 0) {
                    $stmt->bindValue($key, (int)$val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }

            if ($limit !== null) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }


    /**
     * @return array
     */
    public static function findAllDeleted() {
        global $pdo;

        try {
            $stmt = $pdo->query("SELECT * FROM jta_requests WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $results ? $results : [];
        } catch (Exception $e) {
            return [];
        }
    }
}
