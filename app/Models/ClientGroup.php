<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ClientGroup
{
    /** @return array<int, array{id:int,name:string}> */
    public static function forSelect(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $rows = $pdo->query('SELECT id, name FROM client_groups ORDER BY name ASC')->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        }
        return $out;
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('SELECT * FROM client_groups WHERE id = ?');
            $st->execute([$id]);
            $r = $st->fetch();
            return $r ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') return null;
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('SELECT * FROM client_groups WHERE name = ? LIMIT 1');
            $st->execute([$name]);
            $r = $st->fetch();
            return $r ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function create(string $name): int
    {
        $name = trim($name);
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('INSERT INTO client_groups (name) VALUES (?)');
        $st->execute([$name]);
        return (int)$pdo->lastInsertId();
    }

    public static function upsertByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') return 0;
        $found = self::findByName($name);
        if ($found) return (int)$found['id'];
        return self::create($name);
    }

    /** @return array<int, array{id:int,name:string,client_count:int,client_list:string}> */
    public static function allWithMembers(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $rows = $pdo->query("
                SELECT
                  g.id,
                  g.name,
                  COUNT(c.id) AS client_count,
                  GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '||') AS client_list
                FROM client_groups g
                LEFT JOIN clients c ON c.client_group_id = g.id
                GROUP BY g.id
                ORDER BY g.name ASC
            ")->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int)($r['id'] ?? 0),
                'name' => (string)($r['name'] ?? ''),
                'client_count' => (int)($r['client_count'] ?? 0),
                'client_list' => (string)($r['client_list'] ?? ''),
            ];
        }
        return $out;
    }
}

