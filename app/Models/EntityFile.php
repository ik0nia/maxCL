<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class EntityFile
{
    /** @return array<int, array<string,mixed>> */
    public static function forEntity(string $type, int $id): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT * FROM entity_files
            WHERE entity_type = ? AND entity_id = ?
            ORDER BY created_at DESC, id DESC
        ');
        $st->execute([$type, (int)$id]);
        return $st->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM entity_files WHERE id = ?');
        $st->execute([(int)$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO entity_files
              (entity_type, entity_id, category, original_name, stored_name, mime, size_bytes, uploaded_by)
            VALUES
              (:t, :id, :cat, :orig, :stored, :mime, :size, :by)
        ');
        $st->execute([
            ':t' => (string)$data['entity_type'],
            ':id' => (int)$data['entity_id'],
            ':cat' => $data['category'] ?? null,
            ':orig' => (string)$data['original_name'],
            ':stored' => (string)$data['stored_name'],
            ':mime' => $data['mime'] ?? null,
            ':size' => $data['size_bytes'] ?? null,
            ':by' => $data['uploaded_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM entity_files WHERE id = ?');
        $st->execute([(int)$id]);
    }
}

