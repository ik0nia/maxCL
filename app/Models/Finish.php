<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Finish
{
    /** @return array<int, array<string,mixed>> */
    public static function all(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        return $pdo->query('SELECT * FROM finishes ORDER BY color_name ASC, texture_name ASC')->fetchAll();
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

        $st = $pdo->prepare(
            "SELECT *
             FROM finishes
             WHERE code LIKE :q OR color_name LIKE :q OR color_code LIKE :q
             ORDER BY color_name ASC, code ASC"
        );
        $st->execute([':q' => '%' . $q . '%']);
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

    /** @return array<int, array<string,mixed>> */
    public static function forSelect(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->query('SELECT id, code, color_name, texture_name, thumb_path FROM finishes ORDER BY color_name ASC, texture_name ASC');
        return $st->fetchAll();
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

