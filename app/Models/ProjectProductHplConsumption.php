<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectProductHplConsumption
{
    /** @return array<int, array<string,mixed>> */
    public static function forProjectProduct(int $projectProductId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare("
            SELECT
              x.*,
              b.code AS board_code,
              b.name AS board_name,
              b.sale_price AS board_sale_price,
              b.thickness_mm AS board_thickness_mm,
              b.std_width_mm AS board_std_width_mm,
              b.std_height_mm AS board_std_height_mm,
              fc.code AS face_color_code,
              bc.code AS back_color_code,
              fc.thumb_path AS face_thumb,
              bc.thumb_path AS back_thumb,
              u.name AS user_name,
              sp.piece_type AS piece_type,
              sp.status AS piece_status,
              sp.width_mm AS piece_width_mm,
              sp.height_mm AS piece_height_mm,
              sp.qty AS piece_qty,
              sp.location AS piece_location,
              sp.notes AS piece_notes,
              sp.area_total_m2 AS piece_area_total_m2,
              sp.is_accounting AS piece_is_accounting,
              sp2.piece_type AS consumed_piece_type,
              sp2.status AS consumed_piece_status,
              sp2.width_mm AS consumed_piece_width_mm,
              sp2.height_mm AS consumed_piece_height_mm,
              sp2.qty AS consumed_piece_qty,
              sp2.location AS consumed_piece_location,
              sp2.notes AS consumed_piece_notes,
              sp2.area_total_m2 AS consumed_piece_area_total_m2
            FROM project_product_hpl_consumptions x
            INNER JOIN hpl_boards b ON b.id = x.board_id
            LEFT JOIN finishes fc ON fc.id = b.face_color_id
            LEFT JOIN finishes bc ON bc.id = b.back_color_id
            LEFT JOIN users u ON u.id = x.created_by
            LEFT JOIN hpl_stock_pieces sp ON sp.id = x.stock_piece_id
            LEFT JOIN hpl_stock_pieces sp2 ON sp2.id = x.consumed_piece_id
            WHERE x.project_product_id = ?
            ORDER BY x.created_at DESC, x.id DESC
        ");
        $st->execute([(int)$projectProductId]);
        $rows = $st->fetchAll();
        // De-dup pentru UI: păstrăm doar ultima înregistrare pentru aceeași piesă + status (evită dubluri istorice).
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $sid = (int)($r['stock_piece_id'] ?? 0);
            $stt = (string)($r['status'] ?? '');
            $key = $stt . ':' . $sid;
            if ($sid > 0 && isset($seen[$key])) continue;
            if ($sid > 0) $seen[$key] = true;
            $out[] = $r;
        }
        return $out;
    }

    /** @return array<int, array<string,mixed>> */
    public static function reservedForProjectProduct(int $projectId, int $projectProductId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare("
            SELECT *
            FROM project_product_hpl_consumptions
            WHERE project_id = ?
              AND project_product_id = ?
              AND status = 'RESERVED'
            ORDER BY created_at ASC, id ASC
        ");
        $st->execute([(int)$projectId, (int)$projectProductId]);
        return $st->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare("
            INSERT INTO project_product_hpl_consumptions
              (project_id, project_product_id, board_id, stock_piece_id, source, consume_mode, status, created_by)
            VALUES
              (:project_id, :ppid, :board_id, :piece_id, :source, :consume_mode, :status, :created_by)
        ");
        $st->execute([
            ':project_id' => (int)$data['project_id'],
            ':ppid' => (int)$data['project_product_id'],
            ':board_id' => (int)$data['board_id'],
            ':piece_id' => (int)($data['stock_piece_id'] ?? 0) ?: null,
            ':source' => (string)($data['source'] ?? 'PROJECT'),
            ':consume_mode' => (string)($data['consume_mode'] ?? 'FULL'),
            ':status' => (string)($data['status'] ?? 'RESERVED'),
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function markConsumed(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare("UPDATE project_product_hpl_consumptions SET status='CONSUMED', consumed_at=NOW() WHERE id = ?");
        $st->execute([(int)$id]);
    }

    public static function setConsumedPiece(int $id, int $pieceId): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare("UPDATE project_product_hpl_consumptions SET consumed_piece_id = ? WHERE id = ?");
            $st->execute([(int)$pieceId, (int)$id]);
        } catch (\Throwable $e) {
            // compat: coloana poate lipsi
        }
    }
}

