<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectWorkLog
{
    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              w.*,
              p.name AS product_name
            FROM project_work_logs w
            LEFT JOIN project_products pp ON pp.id = w.project_product_id
            LEFT JOIN products p ON p.id = pp.product_id
            WHERE w.project_id = ?
            ORDER BY w.created_at DESC, w.id DESC
        ');
        $st->execute([(int)$projectId]);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM project_work_logs WHERE id = ?');
        $st->execute([(int)$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO project_work_logs
              (project_id, project_product_id, work_type, hours_estimated, hours_actual, cost_per_hour, note, created_by)
            VALUES
              (:project_id, :pp_id, :type, :he, :ha, :cph, :note, :by)
        ');
        $st->execute([
            ':project_id' => (int)$data['project_id'],
            ':pp_id' => $data['project_product_id'] ?? null,
            ':type' => (string)$data['work_type'],
            ':he' => $data['hours_estimated'] ?? null,
            ':ha' => $data['hours_actual'] ?? null,
            ':cph' => $data['cost_per_hour'] ?? null,
            ':note' => (isset($data['note']) && trim((string)$data['note']) !== '') ? trim((string)$data['note']) : null,
            ':by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $pdo->prepare('DELETE FROM project_work_logs WHERE id = ?')->execute([(int)$id]);
    }
}

