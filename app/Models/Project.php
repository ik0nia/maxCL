<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Project
{
    /** @return array<int, array<string,mixed>> */
    public static function forClient(int $clientId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC');
        $st->execute([$clientId]);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function findByCode(string $code): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM projects WHERE code = ? LIMIT 1');
        $st->execute([trim($code)]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Creează un proiect minimal (pentru consumuri de stoc).
     * @return int id
     */
    public static function createPlaceholder(string $code, ?string $name = null, ?int $createdBy = null): int
    {
        $code = trim($code);
        $name = trim((string)($name ?? ''));
        if ($name === '') {
            $name = 'Proiect ' . $code;
        }

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO projects (code, name, status, created_by)
            VALUES (:code, :name, :status, :created_by)
        ');
        $st->execute([
            ':code' => $code,
            ':name' => $name,
            ':status' => 'IN_LUCRU',
            ':created_by' => $createdBy,
        ]);
        return (int)$pdo->lastInsertId();
    }
}

