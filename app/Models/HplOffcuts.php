<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class HplOffcuts
{
    private static function isUnknownColumn(\Throwable $e, string $col): bool
    {
        $m = strtolower($e->getMessage());
        return str_contains($m, 'unknown column') && str_contains($m, strtolower($col));
    }

    private static function isMissingTable(\Throwable $e, string $table): bool
    {
        $m = strtolower($e->getMessage());
        // ex: "Base table or view not found" / "doesn't exist"
        return (str_contains($m, "doesn't exist") || str_contains($m, 'not found')) && str_contains($m, strtolower($table));
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
        // Preferăm încercare directă (mai robust decât detectări pe hosting).
        $selTextures1 = "
            ft.name AS face_texture_name,
            bt.name AS back_texture_name,
        ";
        $joinTextures1 = "
            LEFT JOIN textures ft ON ft.id = b.face_texture_id
            LEFT JOIN textures bt ON bt.id = b.back_texture_id
        ";
        $selAccounting1 = "sp.is_accounting AS is_accounting,";

        if ($limit < 1) $limit = 1;
        if ($limit > 20000) $limit = 20000;

        $baseSelect = "
            SELECT
              sp.id AS piece_id,
              sp.board_id AS board_id,
              %s
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

              %s

              CASE
                WHEN b.std_width_mm > 0 AND b.std_height_mm > 0 THEN ((sp.width_mm * sp.height_mm) / (b.std_width_mm * b.std_height_mm))
                ELSE NULL
              END AS area_ratio
            FROM hpl_stock_pieces sp
            JOIN hpl_boards b ON b.id = sp.board_id
            JOIN finishes fc ON fc.id = b.face_color_id
            LEFT JOIN finishes bc ON bc.id = b.back_color_id
            %s
            WHERE (sp.width_mm <> b.std_width_mm OR sp.height_mm <> b.std_height_mm)
            ORDER BY sp.created_at DESC
            LIMIT $limit
        ";

        $sql1 = sprintf($baseSelect, $selAccounting1, $selTextures1, $joinTextures1);
        try {
            return $pdo->query($sql1)->fetchAll();
        } catch (\Throwable $e) {
            // Fallback 1: fără textures
            if (self::isMissingTable($e, 'textures')) {
                $sqlNoTextures = sprintf($baseSelect, $selAccounting1, "NULL AS face_texture_name,\n              NULL AS back_texture_name,\n        ", "");
                try {
                    return $pdo->query($sqlNoTextures)->fetchAll();
                } catch (\Throwable $e2) {
                    // continue to other fallbacks
                    $e = $e2;
                }
            }
            // Fallback 2: fără is_accounting (coloană lipsă) — tratăm toate ca "contabil"
            if (self::isUnknownColumn($e, 'is_accounting')) {
                $sqlNoAcc = sprintf($baseSelect, "1 AS is_accounting,", $selTextures1, $joinTextures1);
                try {
                    return $pdo->query($sqlNoAcc)->fetchAll();
                } catch (\Throwable $e3) {
                    if (self::isMissingTable($e3, 'textures')) {
                        $sqlNoAccNoTex = sprintf($baseSelect, "1 AS is_accounting,", "NULL AS face_texture_name,\n              NULL AS back_texture_name,\n        ", "");
                        return $pdo->query($sqlNoAccNoTex)->fetchAll();
                    }
                    throw $e3;
                }
            }
            throw $e;
        }
    }
}

