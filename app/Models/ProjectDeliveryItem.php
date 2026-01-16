<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectDeliveryItem
{
    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO project_delivery_items (delivery_id, project_product_id, qty)
            VALUES (:delivery_id, :pp_id, :qty)
        ');
        $st->execute([
            ':delivery_id' => (int)$data['delivery_id'],
            ':pp_id' => (int)$data['project_product_id'],
            ':qty' => (float)$data['qty'],
        ]);
        return (int)$pdo->lastInsertId();
    }
}

