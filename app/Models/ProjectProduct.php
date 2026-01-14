<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectProduct
{
    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              pp.*,
              p.code AS product_code,
              p.name AS product_name,
              p.width_mm AS product_width_mm,
              p.height_mm AS product_height_mm
            FROM project_products pp
            INNER JOIN products p ON p.id = pp.product_id
            WHERE pp.project_id = ?
            ORDER BY pp.id DESC
        ');
        $st->execute([(int)$projectId]);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM project_products WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function addToProject(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO project_products (project_id, product_id, qty, unit, production_status, delivered_qty, notes, cnc_override_json)
            VALUES (:project_id, :product_id, :qty, :unit, :st, :del, :notes, :cnc)
        ');
        $st->execute([
            ':project_id' => (int)$data['project_id'],
            ':product_id' => (int)$data['product_id'],
            ':qty' => (float)($data['qty'] ?? 1),
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':st' => (string)($data['production_status'] ?? 'DE_PREGATIT'),
            ':del' => (float)($data['delivered_qty'] ?? 0),
            ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
            ':cnc' => $data['cnc_override_json'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function updateFields(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            UPDATE project_products
            SET qty=:qty, unit=:unit, production_status=:st, delivered_qty=:del, notes=:notes
            WHERE id=:id
        ');
        $st->execute([
            ':id' => $id,
            ':qty' => (float)($data['qty'] ?? 1),
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':st' => (string)($data['production_status'] ?? 'DE_PREGATIT'),
            ':del' => (float)($data['delivered_qty'] ?? 0),
            ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
        ]);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM project_products WHERE id = ?');
        $st->execute([$id]);
    }
}

