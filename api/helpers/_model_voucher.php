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

        $db   = self::getDB();
        $stmt = $db->prepare("INSERT INTO `$table` ($columns) VALUES ($placeholders)");
        if (!$stmt) { error_log("VoucherModel::insert prepare failed"); return false; }

        try {
            if (!$stmt->execute($values)) {
                $error = $stmt->errorInfo();
                error_log('VoucherModel::insert execute: ' . ($error[2] ?? 'unknown error'));
                return false;
            }
            return (int) $db->lastInsertId();
        } catch (\Throwable $e) {
            error_log('VoucherModel::insert execute: ' . $e->getMessage());
            return false;
        }
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

        try {
            $stmt->execute([$user_id, $cycle_floor, $cycle_ceil]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getByUser(int $user_id, int $limit = 20): array {
        $db   = self::getDB();
        $stmt = $db->prepare(
            "SELECT id, code, status, value_eur, date_created, date_start, date_end
               FROM `user_vouchers`
              WHERE user_id = ? ORDER BY date_created DESC LIMIT ?"
        );
        if (!$stmt) return [];

        try {
            $stmt->execute([$user_id, $limit]);
            $rows = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
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
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function deductPoints(int $user_id, int $amount): bool {
        $db   = self::getDB();
        $stmt = $db->prepare(
            "UPDATE `users` SET points_current_month = GREATEST(0, points_current_month - ?) WHERE id = ?"
        );
        if (!$stmt) return false;

        try {
            return $stmt->execute([$amount, $user_id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function getDB(): \PDO {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
            return $GLOBALS['pdo'];
        }

        throw new \RuntimeException(
            'VoucherModel: PDO connection not found. Ensure db_connect.php initializes global $pdo.'
        );
    }
}
