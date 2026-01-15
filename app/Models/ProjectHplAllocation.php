<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectHplAllocation
{
    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sqlWithPm2 = '
            SELECT
              a.*,
              c.board_id,
              c.mode,
              b.code AS board_code,
              b.name AS board_name,
              b.std_width_mm AS board_std_width_mm,
              b.std_height_mm AS board_std_height_mm,
              b.sale_price AS board_sale_price,
              b.sale_price_per_m2 AS board_sale_price_per_m2,
              p.name AS product_name
            FROM project_hpl_allocations a
            INNER JOIN project_hpl_consumptions c ON c.id = a.consumption_id
            INNER JOIN hpl_boards b ON b.id = c.board_id
            INNER JOIN project_products pp ON pp.id = a.project_product_id
            INNER JOIN products p ON p.id = pp.product_id
            WHERE c.project_id = ?
            ORDER BY a.id DESC
        ';
        try {
            $st = $pdo->prepare($sqlWithPm2);
            $st->execute([(int)$projectId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            // Compat: dacă sale_price_per_m2 nu există (schema veche), calculăm din sale_price și dimensiuni.
            $st = $pdo->prepare('
                SELECT
                  a.*,
                  c.board_id,
                  c.mode,
                  b.code AS board_code,
                  b.name AS board_name,
                  b.std_width_mm AS board_std_width_mm,
                  b.std_height_mm AS board_std_height_mm,
                  b.sale_price AS board_sale_price,
                  NULL AS board_sale_price_per_m2,
                  p.name AS product_name
                FROM project_hpl_allocations a
                INNER JOIN project_hpl_consumptions c ON c.id = a.consumption_id
                INNER JOIN hpl_boards b ON b.id = c.board_id
                INNER JOIN project_products pp ON pp.id = a.project_product_id
                INNER JOIN products p ON p.id = pp.product_id
                WHERE c.project_id = ?
                ORDER BY a.id DESC
            ');
            $st->execute([(int)$projectId]);
            return $st->fetchAll();
        }
    }

    /**
     * Înlocuiește alocările pentru un consum (delete+insert).
     * @param array<int, array{project_product_id:int, qty_m2:float}> $rows
     */
    public static function replaceForConsumption(int $consumptionId, array $rows): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $pdo->prepare('DELETE FROM project_hpl_allocations WHERE consumption_id = ?')->execute([(int)$consumptionId]);
        if (!$rows) return;

        $st = $pdo->prepare('
            INSERT INTO project_hpl_allocations (consumption_id, project_product_id, qty_m2)
            VALUES (:cid, :ppid, :m2)
        ');
        foreach ($rows as $r) {
            $ppid = (int)($r['project_product_id'] ?? 0);
            $m2 = (float)($r['qty_m2'] ?? 0);
            if ($ppid <= 0 || $m2 <= 0) continue;
            $st->execute([':cid' => (int)$consumptionId, ':ppid' => $ppid, ':m2' => $m2]);
        }
    }
}

