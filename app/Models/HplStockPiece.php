<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class HplStockPiece
{
    /** @return array<int, array<string,mixed>> */
    public static function forBoard(int $boardId, ?bool $isAccounting = null): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        if ($isAccounting === null) {
            $st = $pdo->prepare('SELECT * FROM hpl_stock_pieces WHERE board_id = ? ORDER BY created_at DESC');
            $st->execute([$boardId]);
            return $st->fetchAll();
        }

        // Compat: dacă nu există coloana is_accounting încă, returnăm tot.
        try {
            if ($isAccounting) {
                $st = $pdo->prepare('SELECT * FROM hpl_stock_pieces WHERE board_id = ? AND (is_accounting = 1 OR is_accounting IS NULL) ORDER BY created_at DESC');
            } else {
                $st = $pdo->prepare('SELECT * FROM hpl_stock_pieces WHERE board_id = ? AND is_accounting = 0 ORDER BY created_at DESC');
            }
            $st->execute([$boardId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            $st = $pdo->prepare('SELECT * FROM hpl_stock_pieces WHERE board_id = ? ORDER BY created_at DESC');
            $st->execute([$boardId]);
            return $st->fetchAll();
        }
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM hpl_stock_pieces WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function countForBoard(int $boardId, ?bool $isAccounting = null): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        if ($isAccounting === null) {
            $st = $pdo->prepare('SELECT COUNT(*) AS c FROM hpl_stock_pieces WHERE board_id = ?');
            $st->execute([$boardId]);
            $r = $st->fetch();
            return (int)($r['c'] ?? 0);
        }
        try {
            if ($isAccounting) {
                $st = $pdo->prepare('SELECT COUNT(*) AS c FROM hpl_stock_pieces WHERE board_id = ? AND (is_accounting = 1 OR is_accounting IS NULL)');
            } else {
                $st = $pdo->prepare('SELECT COUNT(*) AS c FROM hpl_stock_pieces WHERE board_id = ? AND is_accounting = 0');
            }
            $st->execute([$boardId]);
            $r = $st->fetch();
            return (int)($r['c'] ?? 0);
        } catch (\Throwable $e) {
            $st = $pdo->prepare('SELECT COUNT(*) AS c FROM hpl_stock_pieces WHERE board_id = ?');
            $st->execute([$boardId]);
            $r = $st->fetch();
            return (int)($r['c'] ?? 0);
        }
    }

    private static function isUnknownColumn(\Throwable $e, string $col): bool
    {
        $m = strtolower($e->getMessage());
        return str_contains($m, 'unknown column') && str_contains($m, strtolower($col));
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $isAccounting = array_key_exists('is_accounting', $data) ? (int)$data['is_accounting'] : 1;
        try {
            $st = $pdo->prepare(
                'INSERT INTO hpl_stock_pieces (board_id, project_id, is_accounting, piece_type, status, width_mm, height_mm, qty, location, notes)
                 VALUES (:board_id,:project_id,:is_accounting,:piece_type,:status,:width_mm,:height_mm,:qty,:location,:notes)'
            );
            $st->execute([
                ':board_id' => (int)$data['board_id'],
                ':project_id' => $data['project_id'] ?? null,
                ':is_accounting' => $isAccounting,
                ':piece_type' => $data['piece_type'],
                ':status' => $data['status'],
                ':width_mm' => (int)$data['width_mm'],
                ':height_mm' => (int)$data['height_mm'],
                ':qty' => (int)$data['qty'],
                ':location' => $data['location'] ?? '',
                ':notes' => $data['notes'] ?: null,
            ]);
        } catch (\Throwable $e) {
            // Compat: schema veche fără project_id
            if (self::isUnknownColumn($e, 'project_id')) {
                $st = $pdo->prepare(
                    'INSERT INTO hpl_stock_pieces (board_id, is_accounting, piece_type, status, width_mm, height_mm, qty, location, notes)
                     VALUES (:board_id,:is_accounting,:piece_type,:status,:width_mm,:height_mm,:qty,:location,:notes)'
                );
                $st->execute([
                    ':board_id' => (int)$data['board_id'],
                    ':is_accounting' => $isAccounting,
                    ':piece_type' => $data['piece_type'],
                    ':status' => $data['status'],
                    ':width_mm' => (int)$data['width_mm'],
                    ':height_mm' => (int)$data['height_mm'],
                    ':qty' => (int)$data['qty'],
                    ':location' => $data['location'] ?? '',
                    ':notes' => $data['notes'] ?: null,
                ]);
                return (int)$pdo->lastInsertId();
            }
            if (!self::isUnknownColumn($e, 'is_accounting')) {
                throw $e;
            }
            // Compat: vechi schema fără is_accounting
            $st = $pdo->prepare(
                'INSERT INTO hpl_stock_pieces (board_id, piece_type, status, width_mm, height_mm, qty, location, notes)
                 VALUES (:board_id,:piece_type,:status,:width_mm,:height_mm,:qty,:location,:notes)'
            );
            $st->execute([
                ':board_id' => (int)$data['board_id'],
                ':piece_type' => $data['piece_type'],
                ':status' => $data['status'],
                ':width_mm' => (int)$data['width_mm'],
                ':height_mm' => (int)$data['height_mm'],
                ':qty' => (int)$data['qty'],
                ':location' => $data['location'] ?? '',
                ':notes' => $data['notes'] ?: null,
            ]);
        }
        return (int)$pdo->lastInsertId();
    }

    /**
     * Găsește o piesă identică (pentru cumularea qty):
     * aceeași placă, tip, status, dimensiuni și locație.
     */
    public static function findIdentical(
        int $boardId,
        string $pieceType,
        string $status,
        int $widthMm,
        int $heightMm,
        string $location,
        int $isAccounting = 1,
        ?int $projectId = null,
        ?int $excludeId = null
    ): ?array {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $accCond = ($isAccounting === 1)
            ? '(is_accounting = 1 OR is_accounting IS NULL)' // vechi schema => NULL tratat ca "în contabilitate"
            : 'is_accounting = 0';
        $sql = 'SELECT * FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND (project_id <=> ?)
                  AND ' . $accCond . '
                  AND piece_type = ?
                  AND status = ?
                  AND width_mm = ?
                  AND height_mm = ?
                  AND location = ?';
        $params = [$boardId, $projectId, $pieceType, $status, $widthMm, $heightMm, $location];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $r = $st->fetch();
            return $r ?: null;
        } catch (\Throwable $e) {
            // Compat: vechi schema fără project_id -> căutăm fără filtrare
            if (self::isUnknownColumn($e, 'project_id')) {
                $sql = 'SELECT * FROM hpl_stock_pieces
                        WHERE board_id = ?
                          AND ' . $accCond . '
                          AND piece_type = ?
                          AND status = ?
                          AND width_mm = ?
                          AND height_mm = ?
                          AND location = ?';
                $params = [$boardId, $pieceType, $status, $widthMm, $heightMm, $location];
                if ($excludeId !== null && $excludeId > 0) {
                    $sql .= ' AND id <> ?';
                    $params[] = $excludeId;
                }
                $sql .= ' LIMIT 1';
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $r = $st->fetch();
                return $r ?: null;
            }
            // Compat: vechi schema fără is_accounting -> căutăm fără filtrare
            if (!self::isUnknownColumn($e, 'is_accounting')) {
                throw $e;
            }
            $sql2 = 'SELECT * FROM hpl_stock_pieces
                     WHERE board_id = ?
                       AND piece_type = ?
                       AND status = ?
                       AND width_mm = ?
                       AND height_mm = ?
                       AND location = ?';
            $params2 = [$boardId, $pieceType, $status, $widthMm, $heightMm, $location];
            if ($excludeId !== null && $excludeId > 0) {
                $sql2 .= ' AND id <> ?';
                $params2[] = $excludeId;
            }
            $sql2 .= ' LIMIT 1';
            $st = $pdo->prepare($sql2);
            $st->execute($params2);
            $r = $st->fetch();
            return $r ?: null;
        }
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM hpl_stock_pieces WHERE id = ?');
        $st->execute([$id]);
    }

    public static function updateQty(int $id, int $qty): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('UPDATE hpl_stock_pieces SET qty = ? WHERE id = ?');
        $st->execute([$qty, $id]);
    }

    public static function incrementQty(int $id, int $delta): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('UPDATE hpl_stock_pieces SET qty = qty + ? WHERE id = ?');
        $st->execute([$delta, $id]);
    }

    public static function appendNote(int $id, string $note): void
    {
        $note = trim($note);
        if ($note === '') return;
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        // CONVERT to keep compatibility across MySQL/MariaDB.
        $st = $pdo->prepare("UPDATE hpl_stock_pieces SET notes = TRIM(CONCAT(COALESCE(notes,''), CASE WHEN COALESCE(notes,'') = '' THEN '' ELSE '\n' END, ?)) WHERE id = ?");
        $st->execute([$note, $id]);
    }

    /**
     * @param array{status?:string|null, location?:string|null, notes?:string|null, project_id?:int|null} $data
     */
    public static function updateFields(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $set = [];
        $params = [];
        if (array_key_exists('status', $data)) {
            $set[] = 'status = :status';
            $params[':status'] = $data['status'];
        }
        if (array_key_exists('project_id', $data)) {
            $set[] = 'project_id = :project_id';
            $params[':project_id'] = $data['project_id'];
        }
        if (array_key_exists('location', $data)) {
            $set[] = 'location = :location';
            $params[':location'] = $data['location'] ?? '';
        }
        if (array_key_exists('notes', $data)) {
            $set[] = 'notes = :notes';
            $params[':notes'] = $data['notes'] ?: null;
        }
        if (!$set) return;
        $params[':id'] = $id;
        $sql = 'UPDATE hpl_stock_pieces SET ' . implode(', ', $set) . ' WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute($params);
    }

    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        $projectId = (int)$projectId;
        if ($projectId <= 0) return [];
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('
                SELECT
                  sp.*,
                  b.code AS board_code,
                  b.name AS board_name,
                  b.std_width_mm AS board_std_width_mm,
                  b.std_height_mm AS board_std_height_mm
                FROM hpl_stock_pieces sp
                INNER JOIN hpl_boards b ON b.id = sp.board_id
                WHERE sp.project_id = ?
                   OR EXISTS (
                        SELECT 1
                        FROM project_hpl_consumptions c
                        WHERE c.project_id = ?
                          AND sp.notes LIKE CONCAT(\'%consum HPL #\', c.id, \'%\')
                   )
                ORDER BY sp.board_id ASC, sp.status DESC, sp.piece_type DESC, sp.created_at DESC, sp.id DESC
            ');
            $st->execute([$projectId, $projectId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            // Compat: fără project_id
            return [];
        }
    }
}

