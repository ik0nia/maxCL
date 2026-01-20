<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class OfferProductHpl
{
    /** @return array<int, array<string,mixed>> */
    public static function forOfferProduct(int $offerProductId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              h.*,
              b.code AS board_code,
              b.name AS board_name,
              b.sale_price AS board_sale_price,
              b.std_width_mm AS board_std_width_mm,
              b.std_height_mm AS board_std_height_mm
            FROM offer_product_hpl h
            INNER JOIN hpl_boards b ON b.id = h.board_id
            WHERE h.offer_product_id = ?
            ORDER BY h.created_at DESC, h.id DESC
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
            INSERT INTO offer_product_hpl
              (offer_id, offer_product_id, board_id, consume_mode, qty, width_mm, height_mm, created_by)
            VALUES
              (:offer_id, :offer_product_id, :board_id, :consume_mode, :qty, :width_mm, :height_mm, :created_by)
        ');
        $st->execute([
            ':offer_id' => (int)$data['offer_id'],
            ':offer_product_id' => (int)$data['offer_product_id'],
            ':board_id' => (int)$data['board_id'],
            ':consume_mode' => (string)($data['consume_mode'] ?? 'FULL'),
            ':qty' => (float)($data['qty'] ?? 1),
            ':width_mm' => $data['width_mm'] ?? null,
            ':height_mm' => $data['height_mm'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM offer_product_hpl WHERE id = ?');
        $st->execute([$id]);
    }
}

