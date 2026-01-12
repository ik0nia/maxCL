<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Material
{
    /** @return array<int, array<string,mixed>> */
    public static function all(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        return $pdo->query('SELECT * FROM materials ORDER BY brand ASC, name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM materials WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @return array<int, array<string,mixed>> */
    public static function forSelect(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        return $pdo->query('SELECT id, code, name, brand, thickness_mm FROM materials ORDER BY brand ASC, name ASC')->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare(
            'INSERT INTO materials (code, name, brand, thickness_mm, notes, track_stock)
             VALUES (:code,:name,:brand,:thickness_mm,:notes,:track_stock)'
        );
        $st->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':brand' => $data['brand'],
            ':thickness_mm' => (int)$data['thickness_mm'],
            ':notes' => $data['notes'] ?: null,
            ':track_stock' => (int)$data['track_stock'],
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare(
            'UPDATE materials
             SET code=:code, name=:name, brand=:brand, thickness_mm=:thickness_mm, notes=:notes, track_stock=:track_stock
             WHERE id=:id'
        );
        $st->execute([
            ':id' => $id,
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':brand' => $data['brand'],
            ':thickness_mm' => (int)$data['thickness_mm'],
            ':notes' => $data['notes'] ?: null,
            ':track_stock' => (int)$data['track_stock'],
        ]);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM materials WHERE id = ?');
        $st->execute([$id]);
    }
}

