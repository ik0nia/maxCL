<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class OfferWorkLog
{
    /** @return array<int, array<string,mixed>> */
    public static function forOffer(int $offerId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              w.*,
              p.name AS product_name
            FROM offer_work_logs w
            LEFT JOIN offer_products op ON op.id = w.offer_product_id
            LEFT JOIN products p ON p.id = op.product_id
            WHERE w.offer_id = ?
            ORDER BY w.created_at DESC, w.id DESC
        ');
        $st->execute([(int)$offerId]);
        return $st->fetchAll();
    }

    /** @return array<int, array<string,mixed>> */
    public static function forOfferProduct(int $offerProductId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT *
            FROM offer_work_logs
            WHERE offer_product_id = ?
            ORDER BY created_at DESC, id DESC
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
            INSERT INTO offer_work_logs
              (offer_id, offer_product_id, work_type, hours_estimated, cost_per_hour, note, created_by)
            VALUES
              (:offer_id, :offer_product_id, :work_type, :hours_estimated, :cost_per_hour, :note, :created_by)
        ');
        $st->execute([
            ':offer_id' => (int)$data['offer_id'],
            ':offer_product_id' => $data['offer_product_id'] ?? null,
            ':work_type' => (string)$data['work_type'],
            ':hours_estimated' => $data['hours_estimated'] ?? null,
            ':cost_per_hour' => $data['cost_per_hour'] ?? null,
            ':note' => (isset($data['note']) && trim((string)$data['note']) !== '') ? trim((string)$data['note']) : null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM offer_work_logs WHERE id = ?');
        $st->execute([$id]);
    }
}

