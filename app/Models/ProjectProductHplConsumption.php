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
              b.thickness_mm AS board_thickness_mm,
              b.std_width_mm AS board_std_width_mm,
              b.std_height_mm AS board_std_height_mm,
              sp.piece_type AS piece_type,
              sp.status AS piece_status,
              sp.width_mm AS piece_width_mm,
              sp.height_mm AS piece_height_mm,
              sp.qty AS piece_qty,
              sp.location AS piece_location,
              sp.is_accounting AS piece_is_accounting
            FROM project_product_hpl_consumptions x
            INNER JOIN hpl_boards b ON b.id = x.board_id
            LEFT JOIN hpl_stock_pieces sp ON sp.id = x.stock_piece_id
            WHERE x.project_product_id = ?
            ORDER BY x.created_at DESC, x.id DESC
        ");
        $st->execute([(int)$projectProductId]);
        return $st->fetchAll();
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
}

