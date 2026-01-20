<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Project
{
    private static function isUnknownColumn(\Throwable $e, string $col): bool
    {
        $m = strtolower($e->getMessage());
        return str_contains($m, 'unknown column') && str_contains($m, strtolower($col));
    }

    public static function nextAutoCode(int $startAt = 1000, bool $forUpdate = false): string
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $startAt = max(1, (int)$startAt);

        $sql = "SELECT MAX(CAST(code AS UNSIGNED)) AS mx
                FROM projects
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
        $limit = max(1, min(2000, $limit));
        $q = $q !== null ? trim($q) : '';
        $status = $status !== null ? trim($status) : '';

        $where = [];
        $params = [];
        // soft-delete: ascundem proiectele șterse
        $where[] = 'p.deleted_at IS NULL';
        if ($q !== '') {
            $where[] = '(p.code LIKE :q OR p.name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($status !== '') {
            $where[] = 'p.status = :st';
            $params[':st'] = $status;
        }
        $sql = "
            SELECT
              p.*,
              c.name AS client_name,
              cg.name AS client_group_name
            FROM projects p
            LEFT JOIN clients c ON c.id = p.client_id
            LEFT JOIN client_groups cg ON cg.id = p.client_group_id
        ";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY p.created_at DESC LIMIT ' . (int)$limit;

        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            // Compat: instalări vechi fără client_group_id/status extins etc.
            if (!self::isUnknownColumn($e, 'client_group_id') && !self::isUnknownColumn($e, 'deleted_at')) {
                throw $e;
            }
            $sql2 = "
                SELECT
                  p.*,
                  c.name AS client_name
                FROM projects p
                LEFT JOIN clients c ON c.id = p.client_id
            ";
            if ($where) {
                // scoatem filtrul pe status dacă enum-ul diferă? păstrăm simplu.
                $sql2 .= ' WHERE ' . implode(' AND ', array_filter($where, fn($w) => !str_contains($w, 'p.client_group_id') && !str_contains($w, 'p.deleted_at')));
            }
            $sql2 .= ' ORDER BY p.created_at DESC LIMIT ' . (int)$limit;
            $st = $pdo->prepare($sql2);
            $st->execute($params);
            return $st->fetchAll();
        }
    }

    /** @return array<int, array<string,mixed>> */
    public static function forClient(int $clientId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare("
                SELECT *
                FROM projects
                WHERE client_id = ?
                  AND (deleted_at IS NULL OR deleted_at = '' OR deleted_at = '0000-00-00 00:00:00')
                ORDER BY created_at DESC
            ");
            $st->execute([$clientId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            if (!self::isUnknownColumn($e, 'deleted_at')) throw $e;
            $st = $pdo->prepare('SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC');
            $st->execute([$clientId]);
            return $st->fetchAll();
        }
    }

    /** @return array<string,mixed>|null */
    public static function findByCode(string $code): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $code = trim($code);
        try {
            $st = $pdo->prepare('SELECT * FROM projects WHERE code = ? AND deleted_at IS NULL LIMIT 1');
            $st->execute([$code]);
            $row = $st->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            if (!self::isUnknownColumn($e, 'deleted_at')) throw $e;
            $st = $pdo->prepare('SELECT * FROM projects WHERE code = ? LIMIT 1');
            $st->execute([$code]);
            $row = $st->fetch();
            return $row ?: null;
        }
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

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO projects
              (code, name, description, category, status, priority, start_date, due_date, notes, technical_notes, tags, client_id, client_group_id, allocation_mode, allocations_locked, created_by)
            VALUES
              (:code, :name, :description, :category, :status, :priority, :start_date, :due_date, :notes, :technical_notes, :tags, :client_id, :client_group_id, :allocation_mode, :allocations_locked, :created_by)
        ');
        $st->execute([
            ':code' => trim((string)($data['code'] ?? '')),
            ':name' => trim((string)($data['name'] ?? '')),
            ':description' => $data['description'] ?? null,
            ':category' => $data['category'] ?? null,
            ':status' => (string)($data['status'] ?? 'DRAFT'),
            ':priority' => (int)($data['priority'] ?? 0),
            ':start_date' => $data['start_date'] ?? null,
            ':due_date' => $data['due_date'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':technical_notes' => $data['technical_notes'] ?? null,
            ':tags' => $data['tags'] ?? null,
            ':client_id' => $data['client_id'] ?? null,
            ':client_group_id' => $data['client_group_id'] ?? null,
            ':allocation_mode' => (string)($data['allocation_mode'] ?? 'by_area'),
            ':allocations_locked' => (int)($data['allocations_locked'] ?? 0),
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            UPDATE projects SET
              code=:code,
              name=:name,
              description=:description,
              category=:category,
              status=:status,
              priority=:priority,
              start_date=:start_date,
              due_date=:due_date,
              completed_at=:completed_at,
              cancelled_at=:cancelled_at,
              notes=:notes,
              technical_notes=:technical_notes,
              tags=:tags,
              client_id=:client_id,
              client_group_id=:client_group_id,
              allocation_mode=:allocation_mode,
              allocations_locked=:allocations_locked
            WHERE id=:id
        ');
        $st->execute([
            ':id' => $id,
            ':code' => trim((string)($data['code'] ?? '')),
            ':name' => trim((string)($data['name'] ?? '')),
            ':description' => $data['description'] ?? null,
            ':category' => $data['category'] ?? null,
            ':status' => (string)($data['status'] ?? 'DRAFT'),
            ':priority' => (int)($data['priority'] ?? 0),
            ':start_date' => $data['start_date'] ?? null,
            ':due_date' => $data['due_date'] ?? null,
            ':completed_at' => $data['completed_at'] ?? null,
            ':cancelled_at' => $data['cancelled_at'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':technical_notes' => $data['technical_notes'] ?? null,
            ':tags' => $data['tags'] ?? null,
            ':client_id' => $data['client_id'] ?? null,
            ':client_group_id' => $data['client_group_id'] ?? null,
            ':allocation_mode' => (string)($data['allocation_mode'] ?? 'by_area'),
            ':allocations_locked' => (int)($data['allocations_locked'] ?? 0),
        ]);
    }

    public static function updateSourceOffer(int $id, ?int $offerId): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $val = ($offerId !== null && $offerId > 0) ? $offerId : null;
        try {
            $st = $pdo->prepare('UPDATE projects SET source_offer_id = :oid WHERE id = :id');
            $st->execute([
                ':id' => $id,
                ':oid' => $val,
            ]);
        } catch (\Throwable $e) {
            // compat: coloana poate lipsi
        }
    }
}

