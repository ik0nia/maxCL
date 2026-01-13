<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Texture
{
    /** @return array<int, array<string,mixed>> */
    public static function all(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        return $pdo->query('SELECT * FROM textures ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM textures WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @return array<int, array<string,mixed>> */
    public static function forSelect(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        return $pdo->query('SELECT id, code, name FROM textures ORDER BY name ASC')->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('INSERT INTO textures (code, name) VALUES (:code, :name)');
        $st->execute([
            ':code' => $data['code'] ?: null,
            ':name' => $data['name'],
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('UPDATE textures SET code=:code, name=:name WHERE id=:id');
        $st->execute([
            ':id' => $id,
            ':code' => $data['code'] ?: null,
            ':name' => $data['name'],
        ]);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM textures WHERE id = ?');
        $st->execute([$id]);
    }
}

