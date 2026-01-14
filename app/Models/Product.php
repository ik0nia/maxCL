<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Product
{
    /** @return array<int, array<string,mixed>> */
    public static function all(?string $q = null, int $limit = 800): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $limit = max(1, min(2000, $limit));
        $q = $q !== null ? trim($q) : '';
        if ($q === '') {
            $st = $pdo->prepare('SELECT * FROM products ORDER BY created_at DESC LIMIT ' . (int)$limit);
            $st->execute();
            return $st->fetchAll();
        }
        $like = '%' . $q . '%';
        $st = $pdo->prepare('
            SELECT * FROM products
            WHERE name LIKE :q OR code LIKE :q
            ORDER BY created_at DESC
            LIMIT ' . (int)$limit
        );
        $st->execute([':q' => $like]);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO products (code, name, width_mm, height_mm, notes, cnc_settings_json)
            VALUES (:code, :name, :w, :h, :notes, :cnc)
        ');
        $st->execute([
            ':code' => (isset($data['code']) && trim((string)$data['code']) !== '') ? trim((string)$data['code']) : null,
            ':name' => trim((string)($data['name'] ?? '')),
            ':w' => isset($data['width_mm']) ? (int)$data['width_mm'] : null,
            ':h' => isset($data['height_mm']) ? (int)$data['height_mm'] : null,
            ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
            ':cnc' => $data['cnc_settings_json'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }
}

