<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class AppSetting
{
    public const KEY_COST_LABOR_PER_HOUR = 'cost_labor_per_hour';
    public const KEY_COST_CNC_PER_HOUR = 'cost_cnc_per_hour';

    public static function get(string $key): ?string
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT value FROM app_settings WHERE `key` = ? LIMIT 1');
        $st->execute([$key]);
        $r = $st->fetch();
        if (!$r) return null;
        $v = $r['value'];
        if ($v === null) return null;
        $v = trim((string)$v);
        return $v !== '' ? $v : null;
    }

    public static function getFloat(string $key): ?float
    {
        $v = self::get($key);
        if ($v === null) return null;
        if (!is_numeric($v)) return null;
        return (float)$v;
    }

    /** @param array<int,string> $keys */
    public static function getMany(array $keys): array
    {
        $keys = array_values(array_filter(array_map('strval', $keys), fn($k) => $k !== ''));
        if (!$keys) return [];

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $in = implode(',', array_fill(0, count($keys), '?'));
        $st = $pdo->prepare("SELECT `key`, value FROM app_settings WHERE `key` IN ($in)");
        $st->execute($keys);
        $rows = $st->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $k = (string)($r['key'] ?? '');
            if ($k === '') continue;
            $out[$k] = $r['value'] !== null ? (string)$r['value'] : null;
        }
        return $out;
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        $key = trim($key);
        if ($key === '') return;
        $v = $value !== null ? trim($value) : null;
        if ($v === '') $v = null;

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO app_settings (`key`, value, updated_by)
            VALUES (:k, :v, :by)
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP
        ');
        $st->execute([
            ':k' => $key,
            ':v' => $v,
            ':by' => $userId,
        ]);
    }
}

