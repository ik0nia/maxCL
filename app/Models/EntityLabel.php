<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class EntityLabel
{
    /** @return array<int, array{label_id:int,name:string,source:string}> */
    public static function labelsForEntity(string $entityType, int $entityId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT el.label_id, l.name, el.source
            FROM entity_labels el
            INNER JOIN labels l ON l.id = el.label_id
            WHERE el.entity_type = ? AND el.entity_id = ?
            ORDER BY l.name ASC
        ');
        $st->execute([$entityType, (int)$entityId]);
        $rows = $st->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['label_id' => (int)$r['label_id'], 'name' => (string)$r['name'], 'source' => (string)$r['source']];
        }
        return $out;
    }

    /** @return array<int,int> */
    public static function labelIdsForEntity(string $entityType, int $entityId, ?string $source = null): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = 'SELECT label_id FROM entity_labels WHERE entity_type = ? AND entity_id = ?';
        $params = [$entityType, (int)$entityId];
        if ($source !== null && $source !== '') {
            $sql .= ' AND source = ?';
            $params[] = $source;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        return array_map(fn($r) => (int)$r['label_id'], $rows);
    }

    public static function attach(string $entityType, int $entityId, int $labelId, string $source = 'DIRECT', ?int $createdBy = null): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO entity_labels (entity_type, entity_id, label_id, source, created_by)
            VALUES (:t, :id, :lid, :src, :by)
            ON DUPLICATE KEY UPDATE created_by = VALUES(created_by)
        ');
        $st->execute([
            ':t' => $entityType,
            ':id' => (int)$entityId,
            ':lid' => (int)$labelId,
            ':src' => $source,
            ':by' => $createdBy,
        ]);
    }

    public static function detach(string $entityType, int $entityId, int $labelId, ?string $source = null): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = 'DELETE FROM entity_labels WHERE entity_type = ? AND entity_id = ? AND label_id = ?';
        $params = [$entityType, (int)$entityId, (int)$labelId];
        if ($source !== null && $source !== '') {
            $sql .= ' AND source = ?';
            $params[] = $source;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
    }

    /**
     * Propagă etichetele proiectului către project_products ca INHERITED.
     * @param array<int,int> $projectProductIds
     * @param array<int,int> $labelIds
     */
    public static function propagateToProjectProducts(array $projectProductIds, array $labelIds, ?int $createdBy = null): void
    {
        $ppIds = array_values(array_unique(array_filter(array_map('intval', $projectProductIds), fn($v) => $v > 0)));
        $lblIds = array_values(array_unique(array_filter(array_map('intval', $labelIds), fn($v) => $v > 0)));
        if (!$ppIds || !$lblIds) return;

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO entity_labels (entity_type, entity_id, label_id, source, created_by)
            VALUES (\'project_products\', :eid, :lid, \'INHERITED\', :by)
            ON DUPLICATE KEY UPDATE created_by = VALUES(created_by)
        ');
        foreach ($ppIds as $ppid) {
            foreach ($lblIds as $lid) {
                $st->execute([':eid' => $ppid, ':lid' => $lid, ':by' => $createdBy]);
            }
        }
    }

    /**
     * Șterge eticheta INHERITED de pe toate produsele proiectului.
     * @param array<int,int> $projectProductIds
     */
    public static function removeInheritedFromProjectProducts(array $projectProductIds, int $labelId): void
    {
        $ppIds = array_values(array_unique(array_filter(array_map('intval', $projectProductIds), fn($v) => $v > 0)));
        if (!$ppIds) return;
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $in = implode(',', array_fill(0, count($ppIds), '?'));
        $params = array_merge([(int)$labelId], $ppIds);
        $sql = "DELETE FROM entity_labels
                WHERE entity_type = 'project_products'
                  AND source = 'INHERITED'
                  AND label_id = ?
                  AND entity_id IN ($in)";
        $st = $pdo->prepare($sql);
        $st->execute($params);
    }
}

