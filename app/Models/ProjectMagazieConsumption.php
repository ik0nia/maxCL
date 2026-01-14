<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectMagazieConsumption
{
    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              c.*,
              mi.winmentor_code,
              mi.name AS item_name,
              pp.product_id AS linked_product_id,
              p.code AS linked_product_code,
              p.name AS linked_product_name
            FROM project_magazie_consumptions c
            INNER JOIN magazie_items mi ON mi.id = c.item_id
            LEFT JOIN project_products pp ON pp.id = c.project_product_id
            LEFT JOIN products p ON p.id = pp.product_id
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
        $st = $pdo->prepare('SELECT * FROM project_magazie_consumptions WHERE id = ?');
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
            INSERT INTO project_magazie_consumptions
              (project_id, project_product_id, item_id, qty, unit, mode, note, created_by)
            VALUES
              (:project_id, :pp_id, :item_id, :qty, :unit, :mode, :note, :created_by)
        ');
        $st->execute([
            ':project_id' => (int)$data['project_id'],
            ':pp_id' => $data['project_product_id'] ?? null,
            ':item_id' => (int)$data['item_id'],
            ':qty' => (float)$data['qty'],
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':mode' => (string)($data['mode'] ?? 'CONSUMED'),
            ':note' => (isset($data['note']) && trim((string)$data['note']) !== '') ? trim((string)$data['note']) : null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            UPDATE project_magazie_consumptions
            SET project_product_id=:pp_id, qty=:qty, unit=:unit, mode=:mode, note=:note
            WHERE id=:id
        ');
        $st->execute([
            ':id' => $id,
            ':pp_id' => $data['project_product_id'] ?? null,
            ':qty' => (float)$data['qty'],
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':mode' => (string)($data['mode'] ?? 'CONSUMED'),
            ':note' => (isset($data['note']) && trim((string)$data['note']) !== '') ? trim((string)$data['note']) : null,
        ]);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM project_magazie_consumptions WHERE id = ?');
        $st->execute([$id]);
    }
}

