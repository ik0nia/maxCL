<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class EntityComment
{
    /** @return array<int, array<string,mixed>> */
    public static function forEntity(string $entityType, int $entityId, int $limit = 500): array
    {
        $entityType = trim($entityType);
        $entityId = (int)$entityId;
        $limit = max(1, min(2000, $limit));
        if ($entityType === '' || $entityId <= 0) return [];

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT
              c.*,
              u.name AS user_name,
              u.email AS user_email
            FROM entity_comments c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.entity_type = ?
              AND c.entity_id = ?
            ORDER BY c.created_at ASC, c.id ASC
            LIMIT ' . (int)$limit . '
        ');
        $st->execute([$entityType, $entityId]);
        return $st->fetchAll();
    }

    public static function create(string $entityType, int $entityId, string $comment, ?int $userId): int
    {
        $entityType = trim($entityType);
        $entityId = (int)$entityId;
        $comment = trim($comment);
        if ($entityType === '' || $entityId <= 0 || $comment === '') return 0;

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO entity_comments (entity_type, entity_id, comment, user_id)
            VALUES (:t, :id, :c, :u)
        ');
        $st->execute([
            ':t' => $entityType,
            ':id' => $entityId,
            ':c' => $comment,
            ':u' => $userId,
        ]);
        return (int)$pdo->lastInsertId();
    }
}

