<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Offer
{
    private static function isUnknownColumn(\Throwable $e, string $col): bool
    {
        $m = strtolower($e->getMessage());
        return str_contains($m, 'unknown column') && str_contains($m, strtolower($col));
    }

    public static function nextAutoCode(int $startAt = 10000, bool $forUpdate = false): string
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $startAt = max(1, (int)$startAt);

        $sql = "SELECT MAX(CAST(code AS UNSIGNED)) AS mx
                FROM offers
                WHERE code REGEXP '^[0-9]+$'";
        if ($forUpdate) $sql .= ' FOR UPDATE';

        $row = $pdo->query($sql)->fetch();
        $mx = isset($row['mx']) ? (int)$row['mx'] : 0;
        $next = max($startAt, $mx + 1);
        return (string)$next;
    }

    /** @return array<int, array<string,mixed>> */
    public static function all(?string $q = null, ?string $status = null, int $limit = 500): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        self::expireIfNeeded($pdo, null);
        $limit = max(1, min(2000, $limit));
        $q = $q !== null ? trim($q) : '';
        $status = $status !== null ? trim($status) : '';

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(o.code LIKE :q OR o.name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($status !== '') {
            $where[] = 'o.status = :st';
            $params[':st'] = $status;
        }
        $sql = "
            SELECT
              o.*,
              c.name AS client_name,
              cg.name AS client_group_name
            FROM offers o
            LEFT JOIN clients c ON c.id = o.client_id
            LEFT JOIN client_groups cg ON cg.id = o.client_group_id
        ";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY o.created_at DESC LIMIT ' . (int)$limit;

        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            if (!self::isUnknownColumn($e, 'client_group_id')) {
                throw $e;
            }
            $sql2 = "
                SELECT
                  o.*,
                  c.name AS client_name
                FROM offers o
                LEFT JOIN clients c ON c.id = o.client_id
            ";
            if ($where) $sql2 .= ' WHERE ' . implode(' AND ', array_filter($where, fn($w) => !str_contains($w, 'o.client_group_id')));
            $sql2 .= ' ORDER BY o.created_at DESC LIMIT ' . (int)$limit;
            $st = $pdo->prepare($sql2);
            $st->execute($params);
            return $st->fetchAll();
        }
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        self::expireIfNeeded($pdo, $id);
        $st = $pdo->prepare('SELECT * FROM offers WHERE id = ?');
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
            INSERT INTO offers
              (code, name, description, category, status, due_date, validity_days, notes, technical_notes, tags, client_id, client_group_id, created_by)
            VALUES
              (:code, :name, :description, :category, :status, :due_date, :validity_days, :notes, :technical_notes, :tags, :client_id, :client_group_id, :created_by)
        ');
        $st->execute([
            ':code' => trim((string)($data['code'] ?? '')),
            ':name' => trim((string)($data['name'] ?? '')),
            ':description' => $data['description'] ?? null,
            ':category' => $data['category'] ?? null,
            ':status' => (string)($data['status'] ?? 'DRAFT'),
            ':due_date' => $data['due_date'] ?? null,
            ':validity_days' => $data['validity_days'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':technical_notes' => $data['technical_notes'] ?? null,
            ':tags' => $data['tags'] ?? null,
            ':client_id' => $data['client_id'] ?? null,
            ':client_group_id' => $data['client_group_id'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            UPDATE offers SET
              code=:code,
              name=:name,
              description=:description,
              category=:category,
              status=:status,
              due_date=:due_date,
              validity_days=:validity_days,
              notes=:notes,
              technical_notes=:technical_notes,
              tags=:tags,
              client_id=:client_id,
              client_group_id=:client_group_id
            WHERE id=:id
        ');
        $st->execute([
            ':id' => $id,
            ':code' => trim((string)($data['code'] ?? '')),
            ':name' => trim((string)($data['name'] ?? '')),
            ':description' => $data['description'] ?? null,
            ':category' => $data['category'] ?? null,
            ':status' => (string)($data['status'] ?? 'DRAFT'),
            ':due_date' => $data['due_date'] ?? null,
            ':validity_days' => $data['validity_days'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':technical_notes' => $data['technical_notes'] ?? null,
            ':tags' => $data['tags'] ?? null,
            ':client_id' => $data['client_id'] ?? null,
            ':client_group_id' => $data['client_group_id'] ?? null,
        ]);
    }

    private static function expireIfNeeded(PDO $pdo, ?int $offerId): void
    {
        try {
            $sql = "
                UPDATE offers
                SET status = 'ANULATA'
                WHERE status IN ('DRAFT','TRIMISA')
                  AND validity_days IS NOT NULL
                  AND validity_days > 0
                  AND DATE_ADD(created_at, INTERVAL validity_days DAY) < NOW()
            ";
            $params = [];
            if ($offerId !== null) {
                $sql .= ' AND id = ?';
                $params[] = $offerId;
            }
            if ($params) {
                $st = $pdo->prepare($sql);
                $st->execute($params);
            } else {
                $pdo->exec($sql);
            }
        } catch (\Throwable $e) {
            // ignore (compat cu instalări vechi fără coloana validity_days)
        }
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM offers WHERE id = ?');
        $st->execute([$id]);
    }

    public static function markConverted(int $id, int $projectId): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            UPDATE offers
            SET converted_project_id = :pid,
                converted_at = NOW()
            WHERE id = :id
        ');
        $st->execute([
            ':id' => $id,
            ':pid' => $projectId,
        ]);
    }
}

