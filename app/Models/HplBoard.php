<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class HplBoard
{
    /** @var array<string,bool> */
    private static array $colCache = [];

    private static function hasColumn(string $name): bool
    {
        if (array_key_exists($name, self::$colCache)) {
            return self::$colCache[$name];
        }
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare("
            SELECT COUNT(*) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'hpl_boards'
              AND COLUMN_NAME = ?
        ");
        $st->execute([$name]);
        $row = $st->fetch();
        $ok = ((int)($row['c'] ?? 0)) > 0;
        self::$colCache[$name] = $ok;
        return $ok;
    }

    /** @return array<int, array<string,mixed>> */
    public static function allWithTotals(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = "
            SELECT
              b.*,
              fc.color_name AS face_color_name,
              fc.code AS face_color_code,
              fc.thumb_path AS face_thumb_path,
              fc.image_path AS face_image_path,
              ft.name AS face_texture_name,
              bc.color_name AS back_color_name,
              bc.code AS back_color_code,
              bc.thumb_path AS back_thumb_path,
              bc.image_path AS back_image_path,
              bt.name AS back_texture_name,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.qty ELSE 0 END),0) AS stock_qty_available,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.area_total_m2 ELSE 0 END),0) AS stock_m2_available
            FROM hpl_boards b
            JOIN finishes fc ON fc.id = b.face_color_id
            JOIN textures ft ON ft.id = b.face_texture_id
            LEFT JOIN finishes bc ON bc.id = b.back_color_id
            LEFT JOIN textures bt ON bt.id = b.back_texture_id
            LEFT JOIN hpl_stock_pieces sp ON sp.board_id = b.id
            GROUP BY b.id
            ORDER BY b.brand ASC, b.name ASC
        ";
        return $pdo->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM hpl_boards WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $hasSale = self::hasColumn('sale_price');
        if ($hasSale) {
            $st = $pdo->prepare(
                'INSERT INTO hpl_boards
                  (code,name,brand,thickness_mm,std_width_mm,std_height_mm,sale_price,face_color_id,face_texture_id,back_color_id,back_texture_id,notes)
                 VALUES
                  (:code,:name,:brand,:thickness_mm,:std_width_mm,:std_height_mm,:sale_price,:face_color_id,:face_texture_id,:back_color_id,:back_texture_id,:notes)'
            );
            $st->execute([
                ':code' => $data['code'],
                ':name' => $data['name'],
                ':brand' => $data['brand'],
                ':thickness_mm' => (int)$data['thickness_mm'],
                ':std_width_mm' => (int)$data['std_width_mm'],
                ':std_height_mm' => (int)$data['std_height_mm'],
                ':sale_price' => $data['sale_price'] !== null ? (float)$data['sale_price'] : null,
                ':face_color_id' => (int)$data['face_color_id'],
                ':face_texture_id' => (int)$data['face_texture_id'],
                ':back_color_id' => $data['back_color_id'] !== null ? (int)$data['back_color_id'] : null,
                ':back_texture_id' => $data['back_texture_id'] !== null ? (int)$data['back_texture_id'] : null,
                ':notes' => $data['notes'] ?: null,
            ]);
        } else {
            $st = $pdo->prepare(
                'INSERT INTO hpl_boards
                  (code,name,brand,thickness_mm,std_width_mm,std_height_mm,face_color_id,face_texture_id,back_color_id,back_texture_id,notes)
                 VALUES
                  (:code,:name,:brand,:thickness_mm,:std_width_mm,:std_height_mm,:face_color_id,:face_texture_id,:back_color_id,:back_texture_id,:notes)'
            );
            $st->execute([
                ':code' => $data['code'],
                ':name' => $data['name'],
                ':brand' => $data['brand'],
                ':thickness_mm' => (int)$data['thickness_mm'],
                ':std_width_mm' => (int)$data['std_width_mm'],
                ':std_height_mm' => (int)$data['std_height_mm'],
                ':face_color_id' => (int)$data['face_color_id'],
                ':face_texture_id' => (int)$data['face_texture_id'],
                ':back_color_id' => $data['back_color_id'] !== null ? (int)$data['back_color_id'] : null,
                ':back_texture_id' => $data['back_texture_id'] !== null ? (int)$data['back_texture_id'] : null,
                ':notes' => $data['notes'] ?: null,
            ]);
        }
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $hasSale = self::hasColumn('sale_price');
        if ($hasSale) {
            $st = $pdo->prepare(
                'UPDATE hpl_boards
                 SET code=:code,name=:name,brand=:brand,thickness_mm=:thickness_mm,std_width_mm=:std_width_mm,std_height_mm=:std_height_mm,
                     sale_price=:sale_price,face_color_id=:face_color_id,face_texture_id=:face_texture_id,back_color_id=:back_color_id,back_texture_id=:back_texture_id,notes=:notes
                 WHERE id=:id'
            );
            $st->execute([
                ':id' => $id,
                ':code' => $data['code'],
                ':name' => $data['name'],
                ':brand' => $data['brand'],
                ':thickness_mm' => (int)$data['thickness_mm'],
                ':std_width_mm' => (int)$data['std_width_mm'],
                ':std_height_mm' => (int)$data['std_height_mm'],
                ':sale_price' => $data['sale_price'] !== null ? (float)$data['sale_price'] : null,
                ':face_color_id' => (int)$data['face_color_id'],
                ':face_texture_id' => (int)$data['face_texture_id'],
                ':back_color_id' => $data['back_color_id'] !== null ? (int)$data['back_color_id'] : null,
                ':back_texture_id' => $data['back_texture_id'] !== null ? (int)$data['back_texture_id'] : null,
                ':notes' => $data['notes'] ?: null,
            ]);
        } else {
            $st = $pdo->prepare(
                'UPDATE hpl_boards
                 SET code=:code,name=:name,brand=:brand,thickness_mm=:thickness_mm,std_width_mm=:std_width_mm,std_height_mm=:std_height_mm,
                     face_color_id=:face_color_id,face_texture_id=:face_texture_id,back_color_id=:back_color_id,back_texture_id=:back_texture_id,notes=:notes
                 WHERE id=:id'
            );
            $st->execute([
                ':id' => $id,
                ':code' => $data['code'],
                ':name' => $data['name'],
                ':brand' => $data['brand'],
                ':thickness_mm' => (int)$data['thickness_mm'],
                ':std_width_mm' => (int)$data['std_width_mm'],
                ':std_height_mm' => (int)$data['std_height_mm'],
                ':face_color_id' => (int)$data['face_color_id'],
                ':face_texture_id' => (int)$data['face_texture_id'],
                ':back_color_id' => $data['back_color_id'] !== null ? (int)$data['back_color_id'] : null,
                ':back_texture_id' => $data['back_texture_id'] !== null ? (int)$data['back_texture_id'] : null,
                ':notes' => $data['notes'] ?: null,
            ]);
        }
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM hpl_boards WHERE id = ?');
        $st->execute([$id]);
    }
}

