<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectHplConsumption
{
    private static function isUnknownColumn(\Throwable $e, string $col): bool
    {
        $m = strtolower($e->getMessage());
        return str_contains($m, 'unknown column') && str_contains($m, strtolower($col));
    }

    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sqlWithPm2 = '
            SELECT
              c.*,
              b.code AS board_code,
              b.name AS board_name,
              b.brand AS board_brand,
              b.thickness_mm AS board_thickness_mm,
              b.std_width_mm AS board_std_width_mm,
              b.std_height_mm AS board_std_height_mm,
              b.sale_price AS board_sale_price,
              b.sale_price_per_m2 AS board_sale_price_per_m2
            FROM project_hpl_consumptions c
            INNER JOIN hpl_boards b ON b.id = c.board_id
            WHERE c.project_id = ?
            ORDER BY c.created_at DESC, c.id DESC
        ';
        try {
            $st = $pdo->prepare($sqlWithPm2);
            $st->execute([(int)$projectId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            $st = $pdo->prepare('
                SELECT
                  c.*,
                  b.code AS board_code,
                  b.name AS board_name,
                  b.brand AS board_brand,
                  b.thickness_mm AS board_thickness_mm,
                  b.std_width_mm AS board_std_width_mm,
                  b.std_height_mm AS board_std_height_mm,
                  b.sale_price AS board_sale_price,
                  NULL AS board_sale_price_per_m2
                FROM project_hpl_consumptions c
                INNER JOIN hpl_boards b ON b.id = c.board_id
                WHERE c.project_id = ?
                ORDER BY c.created_at DESC, c.id DESC
            ');
            $st->execute([(int)$projectId]);
            return $st->fetchAll();
        }
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM project_hpl_consumptions WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('
                INSERT INTO project_hpl_consumptions
                  (project_id, board_id, qty_boards, qty_m2, mode, note, created_by)
                VALUES
                  (:project_id, :board_id, :qty_boards, :qty_m2, :mode, :note, :created_by)
            ');
            $st->execute([
                ':project_id' => (int)$data['project_id'],
                ':board_id' => (int)$data['board_id'],
                ':qty_boards' => (int)($data['qty_boards'] ?? 0),
                ':qty_m2' => (float)$data['qty_m2'],
                ':mode' => (string)($data['mode'] ?? 'RESERVED'),
                ':note' => (isset($data['note']) && trim((string)$data['note']) !== '') ? trim((string)$data['note']) : null,
                ':created_by' => $data['created_by'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Compat: dacă DB nu are încă coloana qty_boards, inserăm fără ea.
            if (!self::isUnknownColumn($e, 'qty_boards')) {
                throw $e;
            }
            $st = $pdo->prepare('
                INSERT INTO project_hpl_consumptions
                  (project_id, board_id, qty_m2, mode, note, created_by)
                VALUES
                  (:project_id, :board_id, :qty_m2, :mode, :note, :created_by)
            ');
            $st->execute([
                ':project_id' => (int)$data['project_id'],
                ':board_id' => (int)$data['board_id'],
                ':qty_m2' => (float)$data['qty_m2'],
                ':mode' => (string)($data['mode'] ?? 'RESERVED'),
                ':note' => (isset($data['note']) && trim((string)$data['note']) !== '') ? trim((string)$data['note']) : null,
                ':created_by' => $data['created_by'] ?? null,
            ]);
        }
        return (int)$pdo->lastInsertId();
    }

    public static function reservedTotalForBoard(int $boardId, ?int $excludeProjectId = null): float
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = 'SELECT COALESCE(SUM(qty_m2),0) AS m2 FROM project_hpl_consumptions WHERE board_id = ? AND mode = \'RESERVED\'';
        $params = [(int)$boardId];
        if ($excludeProjectId !== null && $excludeProjectId > 0) {
            $sql .= ' AND project_id <> ?';
            $params[] = (int)$excludeProjectId;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        return (float)($r['m2'] ?? 0);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM project_hpl_consumptions WHERE id = ?');
        $st->execute([$id]);
    }
}

