<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Client
{
    private static function isUnknownColumn(\Throwable $e, string $col): bool
    {
        $m = strtolower($e->getMessage());
        return str_contains($m, 'unknown column') && str_contains($m, strtolower($col));
    }

    /** @return array<int, array<string,mixed>> */
    public static function allWithProjects(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $sql = "
                SELECT
                  c.*,
                  cg.name AS client_group_name,
                  COUNT(p.id) AS project_count,
                  GROUP_CONCAT(CONCAT(p.code, ' · ', p.name) ORDER BY p.created_at DESC SEPARATOR '||') AS project_list
                FROM clients c
                LEFT JOIN client_groups cg ON cg.id = c.client_group_id
                LEFT JOIN projects p
                  ON p.client_id = c.id
                 AND (p.deleted_at IS NULL OR p.deleted_at = '' OR p.deleted_at = '0000-00-00 00:00:00')
                GROUP BY c.id
                ORDER BY c.name ASC
            ";
            return $pdo->query($sql)->fetchAll();
        } catch (\Throwable $e) {
            // Compat: instalări vechi fără client_groups / fără deleted_at pe projects.
            if (!self::isUnknownColumn($e, 'client_group_id') && !self::isUnknownColumn($e, 'deleted_at')) {
                throw $e;
            }
            try {
                $sql2 = "
                    SELECT
                      c.*,
                      COUNT(p.id) AS project_count,
                      GROUP_CONCAT(CONCAT(p.code, ' · ', p.name) ORDER BY p.created_at DESC SEPARATOR '||') AS project_list
                    FROM clients c
                    LEFT JOIN projects p
                      ON p.client_id = c.id
                     AND (p.deleted_at IS NULL OR p.deleted_at = '' OR p.deleted_at = '0000-00-00 00:00:00')
                    GROUP BY c.id
                    ORDER BY c.name ASC
                ";
                return $pdo->query($sql2)->fetchAll();
            } catch (\Throwable $e2) {
                if (!self::isUnknownColumn($e2, 'deleted_at')) throw $e2;
                $sql3 = "
                    SELECT
                      c.*,
                      COUNT(p.id) AS project_count,
                      GROUP_CONCAT(CONCAT(p.code, ' · ', p.name) ORDER BY p.created_at DESC SEPARATOR '||') AS project_list
                    FROM clients c
                    LEFT JOIN projects p ON p.client_id = c.id
                    GROUP BY c.id
                    ORDER BY c.name ASC
                ";
                return $pdo->query($sql3)->fetchAll();
            }
        }
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $groupId = $data['client_group_id'] ?? null;
        try {
            $st = $pdo->prepare(
                'INSERT INTO clients (type, name, client_group_id, cui, contact_person, phone, email, address, notes)
                 VALUES (:type,:name,:client_group_id,:cui,:contact_person,:phone,:email,:address,:notes)'
            );
            $st->execute([
                ':type' => $data['type'],
                ':name' => $data['name'],
                ':client_group_id' => ($groupId !== null && (int)$groupId > 0) ? (int)$groupId : null,
                ':cui' => $data['cui'] ?: null,
                ':contact_person' => $data['contact_person'] ?: null,
                ':phone' => $data['phone'] ?: null,
                ':email' => $data['email'] ?: null,
                ':address' => $data['address'] ?: null,
                ':notes' => $data['notes'] ?: null,
            ]);
        } catch (\Throwable $e) {
            if (!self::isUnknownColumn($e, 'client_group_id')) throw $e;
            $st = $pdo->prepare(
                'INSERT INTO clients (type, name, cui, contact_person, phone, email, address, notes)
                 VALUES (:type,:name,:cui,:contact_person,:phone,:email,:address,:notes)'
            );
            $st->execute([
                ':type' => $data['type'],
                ':name' => $data['name'],
                ':cui' => $data['cui'] ?: null,
                ':contact_person' => $data['contact_person'] ?: null,
                ':phone' => $data['phone'] ?: null,
                ':email' => $data['email'] ?: null,
                ':address' => $data['address'] ?: null,
                ':notes' => $data['notes'] ?: null,
            ]);
        }
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $groupId = $data['client_group_id'] ?? null;
        try {
            $st = $pdo->prepare(
                'UPDATE clients
                 SET type=:type, name=:name, client_group_id=:client_group_id, cui=:cui, contact_person=:contact_person, phone=:phone, email=:email, address=:address, notes=:notes
                 WHERE id=:id'
            );
            $st->execute([
                ':id' => $id,
                ':type' => $data['type'],
                ':name' => $data['name'],
                ':client_group_id' => ($groupId !== null && (int)$groupId > 0) ? (int)$groupId : null,
                ':cui' => $data['cui'] ?: null,
                ':contact_person' => $data['contact_person'] ?: null,
                ':phone' => $data['phone'] ?: null,
                ':email' => $data['email'] ?: null,
                ':address' => $data['address'] ?: null,
                ':notes' => $data['notes'] ?: null,
            ]);
        } catch (\Throwable $e) {
            if (!self::isUnknownColumn($e, 'client_group_id')) throw $e;
            $st = $pdo->prepare(
                'UPDATE clients
                 SET type=:type, name=:name, cui=:cui, contact_person=:contact_person, phone=:phone, email=:email, address=:address, notes=:notes
                 WHERE id=:id'
            );
            $st->execute([
                ':id' => $id,
                ':type' => $data['type'],
                ':name' => $data['name'],
                ':cui' => $data['cui'] ?: null,
                ':contact_person' => $data['contact_person'] ?: null,
                ':phone' => $data['phone'] ?: null,
                ':email' => $data['email'] ?: null,
                ':address' => $data['address'] ?: null,
                ':notes' => $data['notes'] ?: null,
            ]);
        }
    }

    /** @return array<int, array{id:int,name:string,type:string}> */
    public static function othersInGroup(int $clientId, int $groupId): array
    {
        if ($groupId <= 0) return [];
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('SELECT id, name, type FROM clients WHERE client_group_id = ? AND id <> ? ORDER BY name ASC');
            $st->execute([$groupId, $clientId]);
            $rows = $st->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'type' => (string)$r['type']];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM clients WHERE id = ?');
        $st->execute([$id]);
    }
}

