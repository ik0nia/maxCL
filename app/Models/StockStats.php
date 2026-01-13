<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class StockStats
{
    /** @return array<int, array{thickness_mm:int, qty:int, m2:float}> */
    public static function availableByThickness(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = "
            SELECT
              b.thickness_mm AS thickness_mm,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.qty ELSE 0 END),0) AS qty,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.area_total_m2 ELSE 0 END),0) AS m2
            FROM hpl_boards b
            LEFT JOIN hpl_stock_pieces sp ON sp.board_id = b.id
            GROUP BY b.thickness_mm
            ORDER BY b.thickness_mm ASC
        ";
        $rows = $pdo->query($sql)->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'thickness_mm' => (int)$r['thickness_mm'],
                'qty' => (int)$r['qty'],
                'm2' => (float)$r['m2'],
            ];
        }
        return $out;
    }

    /**
     * Top plăci după suprafața disponibilă (AVAILABLE).
     * @return array<int, array<string,mixed>>
     */
    public static function topBoardsByAvailableM2(int $limit = 6): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = "
            SELECT
              b.id,
              b.code,
              b.name,
              b.brand,
              b.thickness_mm,
              fc.thumb_path AS face_thumb_path,
              fc.image_path AS face_image_path,
              bc.thumb_path AS back_thumb_path,
              bc.image_path AS back_image_path,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.qty ELSE 0 END),0) AS qty_available,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.area_total_m2 ELSE 0 END),0) AS m2_available
            FROM hpl_boards b
            JOIN finishes fc ON fc.id = b.face_color_id
            LEFT JOIN finishes bc ON bc.id = b.back_color_id
            LEFT JOIN hpl_stock_pieces sp ON sp.board_id = b.id
            GROUP BY b.id
            ORDER BY m2_available DESC
            LIMIT " . (int)$limit . "
        ";
        return $pdo->query($sql)->fetchAll();
    }
}

