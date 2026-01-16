<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectDelivery
{
    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT * FROM project_deliveries
            WHERE project_id = ?
            ORDER BY delivery_date DESC, id DESC
        ');
        $st->execute([(int)$projectId]);
        return $st->fetchAll();
    }

    /** @return array<int, array<string,mixed>> */
    public static function itemsForDelivery(int $deliveryId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              di.*,
              pp.product_id,
              p.name AS product_name,
              p.code AS product_code
            FROM project_delivery_items di
            INNER JOIN project_products pp ON pp.id = di.project_product_id
            INNER JOIN products p ON p.id = pp.product_id
            WHERE di.delivery_id = ?
            ORDER BY di.id ASC
        ');
        $st->execute([(int)$deliveryId]);
        return $st->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO project_deliveries (project_id, delivery_date, note, created_by)
            VALUES (:project_id, :delivery_date, :note, :created_by)
        ');
        $st->execute([
            ':project_id' => (int)$data['project_id'],
            ':delivery_date' => (string)$data['delivery_date'],
            ':note' => (isset($data['note']) && trim((string)$data['note']) !== '') ? trim((string)$data['note']) : null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }
}

