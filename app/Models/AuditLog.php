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
            a.before_json,
            hb.code AS board_code,
            hb.name AS board_name,
            hb.brand AS board_brand,
            hb.thickness_mm AS board_thickness_mm,
            hb.std_width_mm AS board_std_width_mm,
            hb.std_height_mm AS board_std_height_mm,
            u.name AS user_name, u.email AS user_email, u.role AS user_role
          FROM audit_log a
          LEFT JOIN users u ON u.id = a.actor_user_id
          LEFT JOIN hpl_boards hb
            ON hb.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(a.before_json, '$.board_id')) AS UNSIGNED)
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

    /**
     * Istoric pentru o placă: include evenimente pe placă + pe piese,
     * filtrat în PHP (fără funcții JSON SQL pentru compat cu hosting).
     *
     * @return array<int, array{
     *   id:int,
     *   created_at:string,
     *   action:string,
     *   entity_type:?string,
     *   entity_id:?string,
     *   user_name:?string,
     *   user_email:?string,
     *   message:?string
     * }>
     */
    public static function forBoard(int $boardId, int $limit = 120): array
    {
        $boardId = (int)$boardId;
        $limit = max(1, min(500, $limit));

        /** @var PDO $pdo */
        $pdo = DB::pdo();

        // Luăm un “window” mai mare și filtrăm local după board_id din meta/before/after.
        $st = $pdo->prepare("
          SELECT
            a.id, a.created_at, a.action, a.entity_type, a.entity_id,
            a.before_json, a.after_json, a.meta_json,
            u.name AS user_name, u.email AS user_email
          FROM audit_log a
          LEFT JOIN users u ON u.id = a.actor_user_id
          WHERE a.entity_type IN ('hpl_boards','hpl_stock_pieces')
          ORDER BY a.id DESC
          LIMIT 1200
        ");
        $st->execute();
        $rows = $st->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $etype = isset($r['entity_type']) ? (string)$r['entity_type'] : null;
            $eid = $r['entity_id'] ?? null;
            $eidInt = is_numeric($eid) ? (int)$eid : 0;

            $match = false;
            if ($etype === 'hpl_boards' && $eidInt === $boardId) {
                $match = true;
            } else {
                $before = is_string($r['before_json'] ?? null) ? json_decode((string)$r['before_json'], true) : null;
                $after = is_string($r['after_json'] ?? null) ? json_decode((string)$r['after_json'], true) : null;
                $meta = is_string($r['meta_json'] ?? null) ? json_decode((string)$r['meta_json'], true) : null;
                $bid = null;
                if (is_array($meta) && isset($meta['board_id'])) $bid = (int)$meta['board_id'];
                if ($bid === null && is_array($before) && isset($before['board_id'])) $bid = (int)$before['board_id'];
                if ($bid === null && is_array($after) && isset($after['board_id'])) $bid = (int)$after['board_id'];
                if ($bid !== null && $bid === $boardId) $match = true;
            }

            if (!$match) continue;

            $metaDecoded = is_string($r['meta_json'] ?? null) ? json_decode((string)$r['meta_json'], true) : null;
            $message = (is_array($metaDecoded) && isset($metaDecoded['message']) && is_string($metaDecoded['message']))
                ? (string)$metaDecoded['message']
                : null;

            $out[] = [
                'id' => (int)$r['id'],
                'created_at' => (string)$r['created_at'],
                'action' => (string)$r['action'],
                'entity_type' => $etype,
                'entity_id' => $eid !== null ? (string)$eid : null,
                'user_name' => isset($r['user_name']) ? (string)$r['user_name'] : null,
                'user_email' => isset($r['user_email']) ? (string)$r['user_email'] : null,
                'message' => $message,
            ];
            if (count($out) >= $limit) break;
        }

        return $out;
    }

    /**
     * Istoric pentru un proiect: include evenimente pe proiect + pe entități legate,
     * filtrat în PHP pentru compat hosting (fără JSON SQL).
     *
     * @return array<int, array{
     *   id:int,
     *   created_at:string,
     *   action:string,
     *   entity_type:?string,
     *   entity_id:?string,
     *   user_name:?string,
     *   user_email:?string,
     *   message:?string,
     *   note:?string
     * }>
     */
    public static function forProject(int $projectId, int $limit = 200): array
    {
        $projectId = (int)$projectId;
        $limit = max(1, min(800, $limit));

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare("
          SELECT
            a.id, a.created_at, a.action, a.entity_type, a.entity_id,
            a.before_json, a.after_json, a.meta_json,
            u.name AS user_name, u.email AS user_email
          FROM audit_log a
          LEFT JOIN users u ON u.id = a.actor_user_id
          WHERE a.entity_type IN (
            'projects',
            'project_products',
            'project_magazie_consumptions',
            'project_hpl_consumptions',
            'project_deliveries',
            'project_work_logs',
            'entity_files'
          )
          ORDER BY a.id DESC
          LIMIT 2000
        ");
        $st->execute();
        $rows = $st->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $etype = isset($r['entity_type']) ? (string)$r['entity_type'] : null;
            $eid = $r['entity_id'] ?? null;
            $eidInt = is_numeric($eid) ? (int)$eid : 0;

            $match = false;
            if ($etype === 'projects' && $eidInt === $projectId) {
                $match = true;
            } else {
                $before = is_string($r['before_json'] ?? null) ? json_decode((string)$r['before_json'], true) : null;
                $after = is_string($r['after_json'] ?? null) ? json_decode((string)$r['after_json'], true) : null;
                $meta = is_string($r['meta_json'] ?? null) ? json_decode((string)$r['meta_json'], true) : null;
                $pid = null;
                if (is_array($meta) && isset($meta['project_id'])) $pid = (int)$meta['project_id'];
                if ($pid === null && is_array($before) && isset($before['project_id'])) $pid = (int)$before['project_id'];
                if ($pid === null && is_array($after) && isset($after['project_id'])) $pid = (int)$after['project_id'];
                if ($pid !== null && $pid === $projectId) $match = true;
            }
            if (!$match) continue;

            $metaDecoded = is_string($r['meta_json'] ?? null) ? json_decode((string)$r['meta_json'], true) : null;
            $message = (is_array($metaDecoded) && isset($metaDecoded['message']) && is_string($metaDecoded['message']))
                ? (string)$metaDecoded['message']
                : null;
            $note = (is_array($metaDecoded) && isset($metaDecoded['note']) && is_string($metaDecoded['note']))
                ? (string)$metaDecoded['note']
                : null;

            $out[] = [
                'id' => (int)$r['id'],
                'created_at' => (string)$r['created_at'],
                'action' => (string)$r['action'],
                'entity_type' => $etype,
                'entity_id' => $eid !== null ? (string)$eid : null,
                'user_name' => isset($r['user_name']) ? (string)$r['user_name'] : null,
                'user_email' => isset($r['user_email']) ? (string)$r['user_email'] : null,
                'message' => $message,
                'note' => $note,
            ];
            if (count($out) >= $limit) break;
        }
        return $out;
    }
}

