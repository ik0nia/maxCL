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
     * Returnează stocul disponibil agregat pe Tip culoare (față) și grosime.
     * Texturile sunt ignorate (se cumulează).
     *
     * @return array<int, array{
     *   face_color_id:int,
     *   color_name:string,
     *   color_code:?string,
     *   thumb_path:string,
     *   image_path:?string,
     *   thickness_mm:int,
     *   qty:int,
     *   m2:float
     * }>
     */
    public static function availableByColorAndThickness(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = "
            SELECT
              b.face_color_id AS face_color_id,
              f.color_name AS color_name,
              f.code AS color_code,
              f.thumb_path AS thumb_path,
              f.image_path AS image_path,
              b.thickness_mm AS thickness_mm,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.qty ELSE 0 END),0) AS qty,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.area_total_m2 ELSE 0 END),0) AS m2
            FROM hpl_boards b
            JOIN finishes f ON f.id = b.face_color_id
            LEFT JOIN hpl_stock_pieces sp ON sp.board_id = b.id
            GROUP BY b.face_color_id, b.thickness_mm
            ORDER BY m2 DESC
        ";
        $rows = $pdo->query($sql)->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'face_color_id' => (int)$r['face_color_id'],
                'color_name' => (string)$r['color_name'],
                'color_code' => (string)($r['color_code'] ?? ''),
                'thumb_path' => (string)$r['thumb_path'],
                'image_path' => $r['image_path'] ? (string)$r['image_path'] : null,
                'thickness_mm' => (int)$r['thickness_mm'],
                'qty' => (int)$r['qty'],
                'm2' => (float)$r['m2'],
            ];
        }
        return $out;
    }
}

