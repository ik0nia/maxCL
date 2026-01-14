<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class HplOffcuts
{
    private static function tableExists(PDO $pdo, string $name): bool
    {
        try {
            $st = $pdo->prepare('SHOW TABLES LIKE ?');
            $st->execute([$name]);
            return (bool)$st->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $col): bool
    {
        try {
            $tbl = str_replace('`', '``', $table);
            $st = $pdo->prepare("SHOW COLUMNS FROM `{$tbl}` LIKE ?");
            $st->execute([$col]);
            return (bool)$st->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Returnează toate piesele care nu sunt la dimensiunea standard a plăcii.
     * Include atât stocabile cât și interne (nestocabile).
     *
     * @return array<int, array<string,mixed>>
     */
    public static function nonStandardPieces(int $limit = 4000): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $hasTextures = self::tableExists($pdo, 'textures');
        $hasAccounting = self::columnExists($pdo, 'hpl_stock_pieces', 'is_accounting');

        $selTextures = $hasTextures ? "
            ft.name AS face_texture_name,
            bt.name AS back_texture_name,
        " : "
            NULL AS face_texture_name,
            NULL AS back_texture_name,
        ";
        $joinTextures = $hasTextures ? "
            LEFT JOIN textures ft ON ft.id = b.face_texture_id
            LEFT JOIN textures bt ON bt.id = b.back_texture_id
        " : "";

        $selAccounting = $hasAccounting ? "sp.is_accounting AS is_accounting," : "1 AS is_accounting,";

        if ($limit < 1) $limit = 1;
        if ($limit > 20000) $limit = 20000;

        $sql = "
            SELECT
              sp.id AS piece_id,
              sp.board_id AS board_id,
              $selAccounting
              sp.piece_type AS piece_type,
              sp.status AS status,
              sp.width_mm AS width_mm,
              sp.height_mm AS height_mm,
              sp.qty AS qty,
              sp.location AS location,
              sp.notes AS notes,
              sp.area_per_piece_m2 AS area_per_piece_m2,
              sp.area_total_m2 AS area_total_m2,
              sp.created_at AS piece_created_at,

              b.code AS board_code,
              b.name AS board_name,
              b.brand AS board_brand,
              b.thickness_mm AS thickness_mm,
              b.std_width_mm AS std_width_mm,
              b.std_height_mm AS std_height_mm,

              fc.id AS face_color_id,
              fc.code AS face_color_code,
              fc.color_name AS face_color_name,
              fc.thumb_path AS face_thumb_path,
              fc.image_path AS face_image_path,

              bc.id AS back_color_id,
              bc.code AS back_color_code,
              bc.color_name AS back_color_name,
              bc.thumb_path AS back_thumb_path,
              bc.image_path AS back_image_path,

              $selTextures

              CASE
                WHEN b.std_width_mm > 0 AND b.std_height_mm > 0 THEN ((sp.width_mm * sp.height_mm) / (b.std_width_mm * b.std_height_mm))
                ELSE NULL
              END AS area_ratio
            FROM hpl_stock_pieces sp
            JOIN hpl_boards b ON b.id = sp.board_id
            JOIN finishes fc ON fc.id = b.face_color_id
            LEFT JOIN finishes bc ON bc.id = b.back_color_id
            $joinTextures
            WHERE (sp.width_mm <> b.std_width_mm OR sp.height_mm <> b.std_height_mm)
            ORDER BY sp.created_at DESC
            LIMIT $limit
        ";

        $st = $pdo->query($sql);
        return $st->fetchAll();
    }
}

