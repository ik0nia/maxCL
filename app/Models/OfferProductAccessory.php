<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class OfferProductAccessory
{
    /** @return array<int, array<string,mixed>> */
    public static function forOfferProduct(int $offerProductId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              a.*,
              i.winmentor_code AS item_code,
              i.name AS item_name,
              i.unit AS item_unit,
              i.unit_price AS item_unit_price
            FROM offer_product_accessories a
            INNER JOIN magazie_items i ON i.id = a.item_id
            WHERE a.offer_product_id = ?
            ORDER BY a.created_at DESC, a.id DESC
        ');
        $st->execute([(int)$offerProductId]);
        return $st->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO offer_product_accessories
              (offer_id, offer_product_id, item_id, qty, unit, unit_price, include_in_deviz, created_by)
            VALUES
              (:offer_id, :offer_product_id, :item_id, :qty, :unit, :unit_price, :include_in_deviz, :created_by)
        ');
        $st->execute([
            ':offer_id' => (int)$data['offer_id'],
            ':offer_product_id' => (int)$data['offer_product_id'],
            ':item_id' => (int)$data['item_id'],
            ':qty' => (float)($data['qty'] ?? 1),
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':unit_price' => $data['unit_price'] ?? null,
            ':include_in_deviz' => (int)($data['include_in_deviz'] ?? 1),
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
            UPDATE offer_product_accessories
            SET qty = :qty,
                unit = :unit,
                unit_price = :unit_price,
                include_in_deviz = :include_in_deviz
            WHERE id = :id
        ');
        $st->execute([
            ':id' => $id,
            ':qty' => (float)($data['qty'] ?? 1),
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':unit_price' => $data['unit_price'] ?? null,
            ':include_in_deviz' => (int)($data['include_in_deviz'] ?? 1),
        ]);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM offer_product_accessories WHERE id = ?');
        $st->execute([$id]);
    }
}

