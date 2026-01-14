<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class HplStockPiece
{
    /** @return array<int, array<string,mixed>> */
    public static function forBoard(int $boardId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM hpl_stock_pieces WHERE board_id = ? ORDER BY created_at DESC');
        $st->execute([$boardId]);
        return $st->fetchAll();
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

    public static function countForBoard(int $boardId): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT COUNT(*) AS c FROM hpl_stock_pieces WHERE board_id = ?');
        $st->execute([$boardId]);
        $r = $st->fetch();
        return (int)($r['c'] ?? 0);
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
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
        ?int $excludeId = null
    ): ?array {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = 'SELECT * FROM hpl_stock_pieces
                WHERE board_id = ?
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
     * @param array{status?:string|null, location?:string|null, notes?:string|null} $data
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
}

