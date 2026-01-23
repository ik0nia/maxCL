<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DbMigrations;
use App\Core\DB;
use PDO;

final class MagazieMovement
{
    private static bool $autoMigratedOnce = false;

    private static function maybeAutoMigrateAndRetry(\Throwable $e): bool
    {
        if (self::$autoMigratedOnce) return false;
        $msg = mb_strtolower((string)$e->getMessage());
        if (!str_contains($msg, 'doesn\'t exist') && !str_contains($msg, 'unknown table') && !str_contains($msg, 'magazie_')) {
            return false;
        }
        self::$autoMigratedOnce = true;
        try {
            DbMigrations::runAuto();
        } catch (\Throwable $e2) {
            return false;
        }
        return true;
    }

    /**
     * @param array{item_id:int,direction:string,qty:float,unit_price:float|null,project_id:int|null,project_code:string|null,note:string|null,created_by:int|null} $data
     */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO magazie_movements
              (item_id, direction, qty, unit_price, project_id, project_code, note, created_by)
            VALUES
              (:item_id, :direction, :qty, :unit_price, :project_id, :project_code, :note, :created_by)
        ');
        $st->execute([
            ':item_id' => (int)$data['item_id'],
            ':direction' => (string)$data['direction'],
            ':qty' => (float)$data['qty'],
            ':unit_price' => $data['unit_price'],
            ':project_id' => $data['project_id'],
            ':project_code' => $data['project_code'] !== null ? trim((string)$data['project_code']) : null,
            ':note' => $data['note'] !== null ? trim((string)$data['note']) : null,
            ':created_by' => $data['created_by'],
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @return array<int, array<string,mixed>> */
    public static function recent(int $limit = 120): array
    {
        try {
            /** @var PDO $pdo */
            $pdo = DB::pdo();
            $st = $pdo->prepare('
                SELECT
                  m.*,
                  i.winmentor_code,
                  i.name AS item_name,
                  i.unit AS item_unit,
                  COALESCE(NULLIF(m.project_code, \'\'), p.code) AS project_code_display,
                  p.name AS project_name,
                  CAST(JSON_UNQUOTE(JSON_EXTRACT(a.meta_json, \'$.project_product_id\')) AS UNSIGNED) AS project_product_id,
                  prod.code AS project_product_code,
                  prod.name AS project_product_name
                FROM magazie_movements m
                INNER JOIN magazie_items i ON i.id = m.item_id
                LEFT JOIN projects p ON p.id = m.project_id
                LEFT JOIN audit_log a
                  ON a.action = \'MAGAZIE_OUT\'
                 AND CAST(JSON_UNQUOTE(JSON_EXTRACT(a.meta_json, \'$.movement_id\')) AS UNSIGNED) = m.id
                LEFT JOIN project_products pp
                  ON pp.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(a.meta_json, \'$.project_product_id\')) AS UNSIGNED)
                LEFT JOIN products prod ON prod.id = pp.product_id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT ' . (int)$limit
            );
            $st->execute();
            return $st->fetchAll();
        } catch (\Throwable $e) {
            if (self::maybeAutoMigrateAndRetry($e)) {
                return self::recent($limit);
            }
            throw $e;
        }
    }

    /** @return array<int, array<string,mixed>> */
    public static function forItem(int $itemId, int $limit = 300): array
    {
        try {
            /** @var PDO $pdo */
            $pdo = DB::pdo();
            $st = $pdo->prepare('
                SELECT
                  m.*,
                  i.winmentor_code,
                  i.name AS item_name,
                  i.unit AS item_unit,
                  COALESCE(NULLIF(m.project_code, \'\'), p.code) AS project_code_display,
                  p.name AS project_name,
                  u.name AS user_name,
                  u.email AS user_email
                FROM magazie_movements m
                INNER JOIN magazie_items i ON i.id = m.item_id
                LEFT JOIN projects p ON p.id = m.project_id
                LEFT JOIN users u ON u.id = m.created_by
                WHERE m.item_id = ?
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT ' . (int)$limit
            );
            $st->execute([(int)$itemId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            if (self::maybeAutoMigrateAndRetry($e)) {
                return self::forItem($itemId, $limit);
            }
            throw $e;
        }
    }
}

