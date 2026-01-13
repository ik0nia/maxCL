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
}

