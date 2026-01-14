<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Label
{
    /** @return array<int, array{id:int,name:string,color:?string}> */
    public static function all(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT id, name, color FROM labels ORDER BY name ASC')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'color' => $r['color'] !== null ? (string)$r['color'] : null];
        }
        return $out;
    }

    public static function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') return null;
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM labels WHERE name = ? LIMIT 1');
        $st->execute([$name]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function upsert(string $name, ?string $color = null): int
    {
        $name = trim($name);
        if ($name === '') return 0;
        $color = $color !== null ? trim($color) : null;
        if ($color === '') $color = null;

        $found = self::findByName($name);
        if ($found) return (int)$found['id'];

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('INSERT INTO labels (name, color) VALUES (?, ?)');
        $st->execute([$name, $color]);
        return (int)$pdo->lastInsertId();
    }
}

