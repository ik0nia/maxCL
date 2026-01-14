<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ClientAddress
{
    /** @return array<int, array<string,mixed>> */
    public static function forClient(int $clientId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('SELECT * FROM client_addresses WHERE client_id = ? ORDER BY is_default DESC, id DESC');
            $st->execute([$clientId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('SELECT * FROM client_addresses WHERE id = ?');
            $st->execute([$id]);
            $r = $st->fetch();
            return $r ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param array{label?:string|null,address:string,notes?:string|null,is_default?:int|null} $data */
    public static function create(int $clientId, array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $isDefault = (int)($data['is_default'] ?? 0);
            if ($isDefault === 1) {
                $st0 = $pdo->prepare('UPDATE client_addresses SET is_default = 0 WHERE client_id = ?');
                $st0->execute([$clientId]);
            }
            $st = $pdo->prepare(
                'INSERT INTO client_addresses (client_id, label, address, notes, is_default)
                 VALUES (:client_id,:label,:address,:notes,:is_default)'
            );
            $st->execute([
                ':client_id' => $clientId,
                ':label' => (isset($data['label']) && trim((string)$data['label']) !== '') ? trim((string)$data['label']) : null,
                ':address' => trim((string)$data['address']),
                ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
                ':is_default' => $isDefault === 1 ? 1 : 0,
            ]);
            $id = (int)$pdo->lastInsertId();

            // Dacă e prima adresă, o setăm implicit default.
            if ($isDefault !== 1) {
                $stC = $pdo->prepare('SELECT COUNT(*) AS c FROM client_addresses WHERE client_id = ?');
                $stC->execute([$clientId]);
                $c = (int)(($stC->fetch())['c'] ?? 0);
                if ($c === 1) {
                    $pdo->prepare('UPDATE client_addresses SET is_default = 1 WHERE id = ?')->execute([$id]);
                }
            }

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            throw $e;
        }
    }

    /** @param array{label?:string|null,address:string,notes?:string|null,is_default?:int|null} $data */
    public static function update(int $id, int $clientId, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $isDefault = (int)($data['is_default'] ?? 0);
            if ($isDefault === 1) {
                $pdo->prepare('UPDATE client_addresses SET is_default = 0 WHERE client_id = ?')->execute([$clientId]);
            }
            $st = $pdo->prepare(
                'UPDATE client_addresses
                 SET label=:label, address=:address, notes=:notes, is_default=:is_default
                 WHERE id=:id AND client_id=:client_id'
            );
            $st->execute([
                ':id' => $id,
                ':client_id' => $clientId,
                ':label' => (isset($data['label']) && trim((string)$data['label']) !== '') ? trim((string)$data['label']) : null,
                ':address' => trim((string)$data['address']),
                ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
                ':is_default' => $isDefault === 1 ? 1 : 0,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            throw $e;
        }
    }

    public static function delete(int $id, int $clientId): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            // dacă ștergem default, promovăm altă adresă (dacă există)
            $stF = $pdo->prepare('SELECT is_default FROM client_addresses WHERE id = ? AND client_id = ?');
            $stF->execute([$id, $clientId]);
            $row = $stF->fetch();
            $wasDefault = $row ? ((int)($row['is_default'] ?? 0) === 1) : false;

            $pdo->prepare('DELETE FROM client_addresses WHERE id = ? AND client_id = ?')->execute([$id, $clientId]);

            if ($wasDefault) {
                $st = $pdo->prepare('SELECT id FROM client_addresses WHERE client_id = ? ORDER BY id DESC LIMIT 1');
                $st->execute([$clientId]);
                $r = $st->fetch();
                if ($r && isset($r['id'])) {
                    $pdo->prepare('UPDATE client_addresses SET is_default = 1 WHERE id = ?')->execute([(int)$r['id']]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            throw $e;
        }
    }

    /**
     * Best-effort: sincronizează câmpul legacy `clients.address` într-o adresă default.
     * Dacă nu există nicio adresă încă, o creează (default).
     */
    public static function ensureDefaultFromLegacy(int $clientId, ?string $address): void
    {
        $address = $address !== null ? trim($address) : '';
        if ($address === '') return;
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('SELECT id FROM client_addresses WHERE client_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
            $st->execute([$clientId]);
            $r = $st->fetch();
            if ($r && isset($r['id'])) return;
            self::create($clientId, [
                'label' => 'Adresă principală',
                'address' => $address,
                'notes' => null,
                'is_default' => 1,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

