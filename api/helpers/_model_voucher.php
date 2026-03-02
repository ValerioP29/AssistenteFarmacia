<?php
/**
 * VoucherModel.php
 * Stesso pattern di RequestModel. Mettere nella cartella dei Model/classes del progetto.
 */
class VoucherModel {

    protected static string $table = 'user_vouchers';

    public static function insert(array $data): int|false {
        $table        = static::$table;
        $columns      = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values       = array_values($data);
        $types        = '';
        foreach ($values as $v) {
            if (is_int($v))        $types .= 'i';
            elseif (is_float($v))  $types .= 'd';
            else                   $types .= 's';
        }
        $db   = self::getDB();
        $stmt = $db->prepare("INSERT INTO `$table` ($columns) VALUES ($placeholders)");
        if (!$stmt) { error_log("VoucherModel::insert prepare: " . $db->error); return false; }
        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) { error_log("VoucherModel::insert execute: " . $stmt->error); return false; }
        return (int) $db->insert_id;
    }

    public static function getActiveForCycle(int $user_id, int $cycle_floor, int $cycle_ceil): ?array {
        $db   = self::getDB();
        $stmt = $db->prepare(
            "SELECT id, code, value_eur, status, date_start, date_end
               FROM `user_vouchers`
              WHERE user_id = ? AND status = 0
                AND points_at_generation >= ? AND points_at_generation < ?
              LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('iii', $user_id, $cycle_floor, $cycle_ceil);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result ? $result->fetch_assoc() : null;
        return $row ?: null;
    }

    public static function getByUser(int $user_id, int $limit = 20): array {
        $db   = self::getDB();
        $stmt = $db->prepare(
            "SELECT id, code, status, value_eur, date_created, date_start, date_end
               FROM `user_vouchers`
              WHERE user_id = ? ORDER BY date_created DESC LIMIT ?"
        );
        if (!$stmt) return [];
        $stmt->bind_param('ii', $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) return [];
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id'           => (int)   $row['id'],
                'code'         =>         $row['code'],
                'status'       => (int)   $row['status'],
                'value_eur'    => (float) $row['value_eur'],
                'date_created' =>         $row['date_created'],
                'date_start'   =>         $row['date_start'],
                'date_end'     =>         $row['date_end'],
            ];
        }
        return $rows;
    }

    public static function deductPoints(int $user_id, int $amount): bool {
        $db   = self::getDB();
        $stmt = $db->prepare(
            "UPDATE `users` SET points_current_month = GREATEST(0, points_current_month - ?) WHERE id = ?"
        );
        if (!$stmt) return false;
        $stmt->bind_param('ii', $amount, $user_id);
        return $stmt->execute();
    }

    private static function getDB(): \mysqli {
        // Prova i nomi di variabile globale più comuni — adatta se diverso
        foreach (['conn', 'db', 'mysqli', 'link', 'database'] as $name) {
            if (isset($GLOBALS[$name]) && $GLOBALS[$name] instanceof \mysqli) {
                return $GLOBALS[$name];
        }
        }
        if (function_exists('get_db_connection')) return get_db_connection();
        throw new \RuntimeException(
            'VoucherModel: connessione mysqli non trovata. ' .
            'Controlla il nome della variabile in VoucherModel::getDB().'
        );
    }
}