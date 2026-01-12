<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class Audit
{
    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     * @param array<string,mixed>|null $meta
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
        ?int $actorUserId = null
    ): void {
        try {
            /** @var PDO $pdo */
            $pdo = DB::pdo();

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $actorUserId = $actorUserId ?? Auth::id();

            $stmt = $pdo->prepare(
                'INSERT INTO audit_log (actor_user_id, entity_type, entity_id, action, before_json, after_json, meta_json, ip, user_agent)
                 VALUES (:actor_user_id, :entity_type, :entity_id, :action, :before_json, :after_json, :meta_json, :ip, :user_agent)'
            );

            $stmt->execute([
                ':actor_user_id' => $actorUserId,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':action' => $action,
                ':before_json' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                ':after_json' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
                ':meta_json' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                ':ip' => $ip,
                ':user_agent' => $ua ? mb_substr((string)$ua, 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            // Audit nu trebuie să oprească aplicația (ex: înainte de setup).
        }
    }
}

