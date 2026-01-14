<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectHplConsumption
{
    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              c.*,
              b.code AS board_code,
              b.name AS board_name,
              b.brand AS board_brand,
              b.thickness_mm AS board_thickness_mm
            FROM project_hpl_consumptions c
            INNER JOIN hpl_boards b ON b.id = c.board_id
            WHERE c.project_id = ?
            ORDER BY c.created_at DESC, c.id DESC
        ');
        $st->execute([(int)$projectId]);
        return $st->fetchAll();
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

