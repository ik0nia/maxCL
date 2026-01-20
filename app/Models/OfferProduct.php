<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class OfferProduct
{
    /** @return array<int, array<string,mixed>> */
    public static function forOffer(int $offerId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              op.*,
              p.code AS product_code,
              p.name AS product_name,
              p.notes AS product_notes,
              p.sale_price AS product_sale_price
            FROM offer_products op
            INNER JOIN products p ON p.id = op.product_id
            WHERE op.offer_id = ?
            ORDER BY op.id DESC
        ');
        $st->execute([(int)$offerId]);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM offer_products WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function addToOffer(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO offer_products (offer_id, product_id, qty, unit, notes)
            VALUES (:offer_id, :product_id, :qty, :unit, :notes)
        ');
        $st->execute([
            ':offer_id' => (int)$data['offer_id'],
            ':product_id' => (int)$data['product_id'],
            ':qty' => (float)($data['qty'] ?? 1),
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function updateFields(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            UPDATE offer_products
            SET qty = :qty,
                unit = :unit,
                notes = :notes
            WHERE id = :id
        ');
        $st->execute([
            ':id' => $id,
            ':qty' => (float)($data['qty'] ?? 1),
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
        ]);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM offer_products WHERE id = ?');
        $st->execute([$id]);
    }
}

