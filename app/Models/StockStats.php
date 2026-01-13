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
     * $q filtrează după cod (f.code), culoare (f.color_name) sau cod culoare (f.color_code).
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
    public static function availableByColorAndThickness(?string $q = null): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $q = $q !== null ? trim($q) : null;
        $where = '';
        $params = [];
        if ($q !== null && $q !== '') {
            $where = 'WHERE (f.code LIKE :q OR f.color_name LIKE :q OR f.color_code LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

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
            $where
            GROUP BY b.face_color_id, b.thickness_mm
            ORDER BY m2 DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
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

    /**
     * Returnează stocul disponibil agregat pe Tip culoare (față + verso) și grosime.
     * Texturile sunt ignorate (se cumulează).
     *
     * Reguli:
     * - Culoarea față este întotdeauna inclusă.
     * - Culoarea verso este inclusă doar dacă este setată și diferă de culoarea față (evită dublarea).
     *
     * $q filtrează după cod (f.code), culoare (f.color_name) sau cod culoare (f.color_code).
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
    public static function availableByAnySideColorAndThickness(?string $q = null): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $q = $q !== null ? trim($q) : null;
        $where = '';
        $params = [];
        if ($q !== null && $q !== '') {
            $where = 'WHERE (f.code LIKE :q OR f.color_name LIKE :q OR f.color_code LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "
            SELECT
              x.color_id AS face_color_id,
              f.color_name AS color_name,
              f.code AS color_code,
              f.thumb_path AS thumb_path,
              f.image_path AS image_path,
              x.thickness_mm AS thickness_mm,
              COALESCE(SUM(x.qty),0) AS qty,
              COALESCE(SUM(x.m2),0) AS m2
            FROM (
              SELECT
                b.face_color_id AS color_id,
                b.thickness_mm AS thickness_mm,
                COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.qty ELSE 0 END),0) AS qty,
                COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.area_total_m2 ELSE 0 END),0) AS m2
              FROM hpl_boards b
              LEFT JOIN hpl_stock_pieces sp ON sp.board_id = b.id
              GROUP BY b.face_color_id, b.thickness_mm

              UNION ALL

              SELECT
                b.back_color_id AS color_id,
                b.thickness_mm AS thickness_mm,
                COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.qty ELSE 0 END),0) AS qty,
                COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.area_total_m2 ELSE 0 END),0) AS m2
              FROM hpl_boards b
              LEFT JOIN hpl_stock_pieces sp ON sp.board_id = b.id
              WHERE b.back_color_id IS NOT NULL AND b.back_color_id <> b.face_color_id
              GROUP BY b.back_color_id, b.thickness_mm
            ) x
            JOIN finishes f ON f.id = x.color_id
            $where
            GROUP BY x.color_id, x.thickness_mm
            ORDER BY m2 DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
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

