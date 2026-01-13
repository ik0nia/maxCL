<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class AuditLog
{
    /**
     * @param array{user_id?:int|null, action?:string|null, date_from?:string|null, date_to?:string|null} $filters
     * @return array<int, array<string,mixed>>
     */
    public static function list(array $filters, int $limit = 500): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();

        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'a.actor_user_id = :uid';
            $params[':uid'] = (int)$filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'a.action = :action';
            $params[':action'] = (string)$filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= :df';
            $params[':df'] = (string)$filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= :dt';
            $params[':dt'] = (string)$filters['date_to'] . ' 23:59:59';
        }

        $sql = "
          SELECT
            a.id, a.created_at, a.action, a.entity_type, a.entity_id, a.ip, a.meta_json,
            u.name AS user_name, u.email AS user_email, u.role AS user_role
          FROM audit_log a
          LEFT JOIN users u ON u.id = a.actor_user_id
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.id DESC LIMIT ' . (int)$limit;

        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<int, array{ id:int, created_at:string, action:string, entity_type:?string, entity_id:?string, before_json:?string, after_json:?string, meta_json:?string, ip:?string, user_agent:?string, actor_user_id:?int }> */
    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM audit_log WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @return array<int, array{id:int, label:string}> */
    public static function usersForFilter(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT id, CONCAT(name, " (", email, ")") AS label FROM users ORDER BY name ASC')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r['id'], 'label' => (string)$r['label']];
        }
        return $out;
    }

    /** @return array<int, string> */
    public static function actionsForFilter(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT DISTINCT action FROM audit_log ORDER BY action ASC')->fetchAll();
        return array_map(fn($r) => (string)$r['action'], $rows);
    }
}

