<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Finish
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
        try {
            $st = $pdo->prepare("SHOW COLUMNS FROM finishes LIKE ?");
            $st->execute([$name]);
            $ok = (bool)$st->fetch();
        } catch (\Throwable $e) {
            $ok = false;
        }
        self::$colCache[$name] = $ok;
        return $ok;
    }

    private static function isUnknownColumn(\Throwable $e, string $col): bool
    {
        $m = strtolower($e->getMessage());
        return str_contains($m, 'unknown column') && str_contains($m, strtolower($col));
    }

    /**
     * Căutare pentru Catalog (carduri) fără dependență de SHOW COLUMNS,
     * cu fallback dacă lipsesc coloane (compat instalări vechi / hosting restricționat).
     *
     * @return array<int, array{id:int, code:string, color_name:string, thumb_path:string, image_path:?string}>
     */
    public static function catalogSearch(?string $q, int $limit = 500): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $limit = max(1, min(2000, $limit));
        $q = $q !== null ? trim($q) : '';

        if ($q === '') {
            $sql = "SELECT id, code, color_name, thumb_path, image_path
                    FROM finishes
                    ORDER BY code ASC, color_name ASC
                    LIMIT $limit";
            return $pdo->query($sql)->fetchAll();
        }

        $qNoSpace = str_replace(["\xC2\xA0", ' '], '', $q);
        $like = '%' . $q . '%';
        $like2 = '%' . $qNoSpace . '%';
        $prefix2 = $qNoSpace . '%';

        // Preferăm query-ul complet (include color_code), dar facem fallback dacă nu există coloana.
        $sql1 = "
          SELECT id, code, color_name, thumb_path, image_path
          FROM finishes
          WHERE
            code LIKE ?
            OR REPLACE(code, ' ', '') LIKE ?
            OR color_name LIKE ?
            OR color_code LIKE ?
            OR REPLACE(COALESCE(color_code,''), ' ', '') LIKE ?
          ORDER BY
            CASE WHEN REPLACE(code, ' ', '') LIKE ? THEN 0 ELSE 1 END,
            code ASC,
            color_name ASC
          LIMIT $limit
        ";
        $sql2 = "
          SELECT id, code, color_name, thumb_path, image_path
          FROM finishes
          WHERE
            code LIKE ?
            OR REPLACE(code, ' ', '') LIKE ?
            OR color_name LIKE ?
          ORDER BY
            CASE WHEN REPLACE(code, ' ', '') LIKE ? THEN 0 ELSE 1 END,
            code ASC,
            color_name ASC
          LIMIT $limit
        ";

        try {
            $st = $pdo->prepare($sql1);
            $st->execute([$like, $like2, $like, $like, $like2, $prefix2]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            if (!self::isUnknownColumn($e, 'color_code')) {
                throw $e;
            }
            $st = $pdo->prepare($sql2);
            $st->execute([$like, $like2, $like, $prefix2]);
            return $st->fetchAll();
        }
    }

    /** @return array<int, array<string,mixed>> */
    public static function all(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $order = ['color_name ASC'];
        if (self::hasColumn('texture_name')) $order[] = 'texture_name ASC';
        if (self::hasColumn('code')) $order[] = 'code ASC';
        $sql = 'SELECT * FROM finishes ORDER BY ' . implode(', ', $order);
        return $pdo->query($sql)->fetchAll();
    }

    /** @return array<int, array<string,mixed>> */
    public static function search(?string $q): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $q = $q !== null ? trim($q) : '';
        if ($q === '') {
            return self::all();
        }

        // Căutare tolerantă (inclusiv fără spații în cod / cod culoare)
        $qNoSpace = str_replace(["\xC2\xA0", ' '], '', $q);
        $like = '%' . $q . '%';
        $like2 = '%' . $qNoSpace . '%';
        $prefix2 = $qNoSpace . '%';

        $conds = [];
        $params = [];
        $hasCode = self::hasColumn('code');
        $hasColorCode = self::hasColumn('color_code');
        $hasTextureName = self::hasColumn('texture_name');
        $hasTextureCode = self::hasColumn('texture_code');

        if ($hasCode) {
            $conds[] = 'code LIKE ?';
            $params[] = $like;
            $conds[] = "REPLACE(code, ' ', '') LIKE ?";
            $params[] = $like2;
        }
        $conds[] = 'color_name LIKE ?';
        $params[] = $like;
        if ($hasColorCode) {
            $conds[] = 'color_code LIKE ?';
            $params[] = $like;
            $conds[] = "REPLACE(COALESCE(color_code,''), ' ', '') LIKE ?";
            $params[] = $like2;
        }
        if ($hasTextureName) {
            $conds[] = 'texture_name LIKE ?';
            $params[] = $like;
        }
        if ($hasTextureCode) {
            $conds[] = 'texture_code LIKE ?';
            $params[] = $like;
        }

        $where = $conds ? ('WHERE ' . implode(' OR ', $conds)) : '';

        $order = [];
        if ($hasCode) {
            // aducem mai sus codurile care încep cu ce se tastează (ignorând spațiile)
            $order[] = "CASE WHEN REPLACE(code, ' ', '') LIKE ? THEN 0 ELSE 1 END";
            $params[] = $prefix2;
        }
        $order[] = 'color_name ASC';
        if ($hasCode) $order[] = 'code ASC';
        if ($hasTextureName) $order[] = 'texture_name ASC';

        $sql = "SELECT * FROM finishes $where ORDER BY " . implode(', ', $order);
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM finishes WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') return null;
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        if (self::hasColumn('color_code')) {
            $codeNoSpace = str_replace(' ', '', $code);
            $st = $pdo->prepare("
                SELECT * FROM finishes
                WHERE code = ?
                   OR color_code = ?
                   OR REPLACE(code, ' ', '') = ?
                   OR REPLACE(COALESCE(color_code,''), ' ', '') = ?
                LIMIT 1
            ");
            $st->execute([$code, $code, $codeNoSpace, $codeNoSpace]);
            $r = $st->fetch();
            return $r ?: null;
        }
        $st = $pdo->prepare('SELECT * FROM finishes WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @return array<int, array<string,mixed>> */
    public static function forSelect(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->query('SELECT id, code, color_name, texture_name, thumb_path FROM finishes ORDER BY color_name ASC, texture_name ASC');
        return $st->fetchAll();
    }

    /** @param array<int,int> $ids @return array<int, array<string,mixed>> */
    public static function forSelectByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (!$ids) return [];

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, code, color_name, thumb_path FROM finishes WHERE id IN ($in)");
        $st->execute($ids);
        return $st->fetchAll();
    }

    /** @return array<int, array{id:int, text:string, thumb:string, code:string, name:string}> */
    public static function searchForSelect(?string $q, int $limit = 20): array
    {
        $q = $q !== null ? trim($q) : '';
        if ($q === '') return [];
        $limit = max(1, min(50, $limit));

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $qNoSpace = str_replace(' ', '', $q);
        $like = '%' . $q . '%';
        $like2 = '%' . $qNoSpace . '%';
        $prefix2 = $qNoSpace . '%';
        // IMPORTANT: evităm placeholder-e nominale repetate (pot produce HY093 pe unele drivere PDO)
        $sql = "
          SELECT id, code, color_name, thumb_path
          FROM finishes
          WHERE
            code LIKE ?
            OR REPLACE(code, ' ', '') LIKE ?
            OR color_name LIKE ?
            OR color_code LIKE ?
            OR REPLACE(COALESCE(color_code,''), ' ', '') LIKE ?
          ORDER BY
            CASE WHEN REPLACE(code, ' ', '') LIKE ? THEN 0 ELSE 1 END,
            code ASC,
            color_name ASC
          LIMIT $limit
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$like, $like2, $like, $like, $like2, $prefix2]);
        $rows = $st->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $code = (string)($r['code'] ?? '');
            $name = (string)($r['color_name'] ?? '');
            $text = trim($code) !== '' ? trim($code) : '—';
            if (trim($name) !== '') {
                $text .= ' - ' . trim($name);
            }
            $out[] = [
                'id' => $id,
                'text' => $text,
                'thumb' => (string)($r['thumb_path'] ?? ''),
                'code' => trim($code),
                'name' => trim($name),
            ];
        }
        return $out;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare(
            'INSERT INTO finishes (code, color_name, color_code, texture_name, texture_code, thumb_path, image_path)
             VALUES (:code,:color_name,:color_code,:texture_name,:texture_code,:thumb_path,:image_path)'
        );
        $st->execute([
            ':code' => $data['code'],
            ':color_name' => $data['color_name'],
            ':color_code' => $data['color_code'] ?: null,
            ':texture_name' => $data['texture_name'],
            ':texture_code' => $data['texture_code'] ?: null,
            ':thumb_path' => $data['thumb_path'],
            ':image_path' => $data['image_path'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare(
            'UPDATE finishes
             SET code=:code, color_name=:color_name, color_code=:color_code, texture_name=:texture_name, texture_code=:texture_code,
                 thumb_path=:thumb_path, image_path=:image_path
             WHERE id=:id'
        );
        $st->execute([
            ':id' => $id,
            ':code' => $data['code'],
            ':color_name' => $data['color_name'],
            ':color_code' => $data['color_code'] ?: null,
            ':texture_name' => $data['texture_name'],
            ':texture_code' => $data['texture_code'] ?: null,
            ':thumb_path' => $data['thumb_path'],
            ':image_path' => $data['image_path'] ?: null,
        ]);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM finishes WHERE id = ?');
        $st->execute([$id]);
    }
}

