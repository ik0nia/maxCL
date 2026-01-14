<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class HplBoard
{
    /** @var array<string,bool> */
    private static array $colCache = [];
    /** @var array<string,bool> */
    private static array $tableCache = [];

    private static function hasColumn(string $name): bool
    {
        if (array_key_exists($name, self::$colCache)) {
            return self::$colCache[$name];
        }
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        // Compat hosting: unele conturi nu au acces la information_schema.
        // Folosim SHOW COLUMNS (mai permisiv).
        try {
            $st = $pdo->prepare("SHOW COLUMNS FROM hpl_boards LIKE ?");
            $st->execute([$name]);
            $ok = (bool)$st->fetch();
        } catch (\Throwable $e) {
            $ok = false;
        }
        self::$colCache[$name] = $ok;
        return $ok;
    }

    private static function hasTable(string $name): bool
    {
        if (array_key_exists($name, self::$tableCache)) {
            return self::$tableCache[$name];
        }
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        // Compat hosting: SHOW TABLES nu depinde de information_schema.
        try {
            $st = $pdo->prepare("SHOW TABLES LIKE ?");
            $st->execute([$name]);
            $ok = (bool)$st->fetch();
        } catch (\Throwable $e) {
            $ok = false;
        }
        self::$tableCache[$name] = $ok;
        return $ok;
    }

    private static function isUnknownColumn(\Throwable $e, string $col): bool
    {
        $m = strtolower($e->getMessage());
        return str_contains($m, 'unknown column') && str_contains($m, strtolower($col));
    }

    /** @return array<int, array<string,mixed>> */
    public static function allWithTotals(?int $colorId = null, ?int $thicknessMm = null): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $whereParts = [];
        $params = [];
        if ($colorId !== null && $colorId > 0) {
            // IMPORTANT: evităm placeholder-e nominale repetate (pot produce HY093 pe unele drivere PDO)
            $whereParts[] = '(b.face_color_id = :cid1 OR b.back_color_id = :cid2)';
            $params[':cid1'] = $colorId;
            $params[':cid2'] = $colorId;
        }
        if ($thicknessMm !== null && $thicknessMm > 0) {
            $whereParts[] = 'b.thickness_mm = :th';
            $params[':th'] = $thicknessMm;
        }
        $where = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        $hasTextures = self::hasTable('textures');
        $joinTextures = $hasTextures
            ? "JOIN textures ft ON ft.id = b.face_texture_id
               LEFT JOIN textures bt ON bt.id = b.back_texture_id"
            : "";
        $selTextures = $hasTextures
            ? "ft.name AS face_texture_name,
               bt.name AS back_texture_name,"
            : "NULL AS face_texture_name,
               NULL AS back_texture_name,";

        // IMPORTANT: ignorăm piesele interne (nestocabile) din totaluri, dacă există coloana is_accounting.
        $sqlWithAccounting = "
            SELECT
              b.*,
              fc.color_name AS face_color_name,
              fc.code AS face_color_code,
              fc.thumb_path AS face_thumb_path,
              fc.image_path AS face_image_path,
              $selTextures
              bc.color_name AS back_color_name,
              bc.code AS back_color_code,
              bc.thumb_path AS back_thumb_path,
              bc.image_path AS back_image_path,
              COALESCE(SUM(CASE WHEN sp.is_accounting=1 AND sp.status='AVAILABLE' THEN sp.qty ELSE 0 END),0) AS stock_qty_available,
              COALESCE(SUM(CASE WHEN sp.is_accounting=1 AND sp.status='AVAILABLE' AND sp.piece_type='FULL' THEN sp.qty ELSE 0 END),0) AS stock_qty_full_available,
              COALESCE(SUM(CASE WHEN sp.is_accounting=1 AND sp.status='AVAILABLE' AND sp.piece_type='OFFCUT' THEN sp.qty ELSE 0 END),0) AS stock_qty_offcut_available,
              COALESCE(SUM(CASE WHEN sp.is_accounting=1 AND sp.status='AVAILABLE' THEN sp.area_total_m2 ELSE 0 END),0) AS stock_m2_available
            FROM hpl_boards b
            JOIN finishes fc ON fc.id = b.face_color_id
            LEFT JOIN finishes bc ON bc.id = b.back_color_id
            $joinTextures
            LEFT JOIN hpl_stock_pieces sp ON sp.board_id = b.id
            $where
            GROUP BY b.id
            ORDER BY b.brand ASC, b.name ASC
        ";

        $sqlNoAccounting = "
            SELECT
              b.*,
              fc.color_name AS face_color_name,
              fc.code AS face_color_code,
              fc.thumb_path AS face_thumb_path,
              fc.image_path AS face_image_path,
              $selTextures
              bc.color_name AS back_color_name,
              bc.code AS back_color_code,
              bc.thumb_path AS back_thumb_path,
              bc.image_path AS back_image_path,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.qty ELSE 0 END),0) AS stock_qty_available,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' AND sp.piece_type='FULL' THEN sp.qty ELSE 0 END),0) AS stock_qty_full_available,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' AND sp.piece_type='OFFCUT' THEN sp.qty ELSE 0 END),0) AS stock_qty_offcut_available,
              COALESCE(SUM(CASE WHEN sp.status='AVAILABLE' THEN sp.area_total_m2 ELSE 0 END),0) AS stock_m2_available
            FROM hpl_boards b
            JOIN finishes fc ON fc.id = b.face_color_id
            LEFT JOIN finishes bc ON bc.id = b.back_color_id
            $joinTextures
            LEFT JOIN hpl_stock_pieces sp ON sp.board_id = b.id
            $where
            GROUP BY b.id
            ORDER BY b.brand ASC, b.name ASC
        ";

        try {
            $st = $pdo->prepare($sqlWithAccounting);
            $st->execute($params);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            // Compat: dacă încă nu există coloana, nu blocăm.
            if (!self::isUnknownColumn($e, 'is_accounting')) {
                throw $e;
            }
            $st = $pdo->prepare($sqlNoAccounting);
            $st->execute($params);
            return $st->fetchAll();
        }
    }

    /** @return array<int, array{id:int,text:string,code:string,name:string,brand:string,thickness_mm:int,std_width_mm:int,std_height_mm:int}> */
    public static function searchForSelect(?string $q, int $limit = 25): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $q = $q !== null ? trim($q) : '';
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        $where = '';
        $params = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where = 'WHERE (code LIKE ? OR name LIKE ? OR brand LIKE ?)';
            $params = [$like, $like, $like];
        }

        $sql = "
            SELECT id, code, name, brand, thickness_mm, std_width_mm, std_height_mm
            FROM hpl_boards
            $where
            ORDER BY code ASC
            LIMIT $limit
        ";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $code = (string)($r['code'] ?? '');
            $name = (string)($r['name'] ?? '');
            $brand = (string)($r['brand'] ?? '');
            $th = (int)($r['thickness_mm'] ?? 0);
            $w = (int)($r['std_width_mm'] ?? 0);
            $h = (int)($r['std_height_mm'] ?? 0);
            $text = trim($code . ' · ' . $name . ' · ' . $brand . ' · ' . $th . 'mm · ' . $h . '×' . $w);
            $out[] = [
                'id' => $id,
                'text' => $text,
                'code' => $code,
                'name' => $name,
                'brand' => $brand,
                'thickness_mm' => $th,
                'std_width_mm' => $w,
                'std_height_mm' => $h,
            ];
        }
        return $out;
    }

    /** @return array<int,int> */
    public static function thicknessOptions(): array
    {
        try {
            /** @var PDO $pdo */
            $pdo = DB::pdo();
            $st = $pdo->query('SELECT DISTINCT thickness_mm FROM hpl_boards WHERE thickness_mm IS NOT NULL ORDER BY thickness_mm ASC');
            $rows = $st->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $v = (int)($r['thickness_mm'] ?? 0);
                if ($v > 0) $out[] = $v;
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
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
            // Dacă detectarea coloanei a eșuat pe hosting, încercăm totuși INSERT cu sale_price.
            // Dacă nu există coloana, facem fallback fără sale_price.
            try {
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
            } catch (\Throwable $e) {
                if (!self::isUnknownColumn($e, 'sale_price')) {
                    throw $e;
                }
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
            // Dacă detectarea coloanei a eșuat pe hosting, încercăm totuși UPDATE cu sale_price.
            // Dacă nu există coloana, facem fallback fără sale_price.
            try {
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
            } catch (\Throwable $e) {
                if (!self::isUnknownColumn($e, 'sale_price')) {
                    throw $e;
                }
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
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM hpl_boards WHERE id = ?');
        $st->execute([$id]);
    }
}

