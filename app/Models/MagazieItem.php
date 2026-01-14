<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class MagazieItem
{
    /** @return array<int, array<string,mixed>> */
    public static function all(?string $q = null, int $limit = 2000): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();

        $q = $q !== null ? trim($q) : '';
        if ($q === '') {
            $st = $pdo->prepare('SELECT * FROM magazie_items ORDER BY name ASC, winmentor_code ASC LIMIT ' . (int)$limit);
            $st->execute();
            return $st->fetchAll();
        }

        $like = '%' . $q . '%';
        $st = $pdo->prepare(
            'SELECT * FROM magazie_items
             WHERE winmentor_code LIKE :q OR name LIKE :q
             ORDER BY name ASC, winmentor_code ASC
             LIMIT ' . (int)$limit
        );
        $st->execute([':q' => $like]);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM magazie_items WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public static function findByWinmentorCode(string $code): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM magazie_items WHERE winmentor_code = ? LIMIT 1');
        $st->execute([trim($code)]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public static function findForUpdate(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM magazie_items WHERE id = ? LIMIT 1 FOR UPDATE');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public static function findByWinmentorForUpdate(string $code): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM magazie_items WHERE winmentor_code = ? LIMIT 1 FOR UPDATE');
        $st->execute([trim($code)]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * @param array{winmentor_code:string,name:string,unit_price:float|null,stock_qty:int} $data
     */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO magazie_items (winmentor_code, name, unit_price, stock_qty)
            VALUES (:c, :n, :p, :q)
        ');
        $st->execute([
            ':c' => trim((string)$data['winmentor_code']),
            ':n' => trim((string)$data['name']),
            ':p' => $data['unit_price'],
            ':q' => (int)$data['stock_qty'],
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * @param array{name?:string,unit_price?:float|null} $fields
     */
    public static function updateFields(int $id, array $fields): void
    {
        $sets = [];
        $params = [':id' => $id];

        if (array_key_exists('name', $fields)) {
            $sets[] = 'name = :name';
            $params[':name'] = trim((string)$fields['name']);
        }
        if (array_key_exists('unit_price', $fields)) {
            $sets[] = 'unit_price = :unit_price';
            $params[':unit_price'] = $fields['unit_price'];
        }
        if (!$sets) return;

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = 'UPDATE magazie_items SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute($params);
    }

    /** Ajustează stocul (delta poate fi negativ). Returnează false dacă ar duce sub 0. */
    public static function adjustStock(int $id, int $delta): bool
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        if ($delta >= 0) {
            $st = $pdo->prepare('UPDATE magazie_items SET stock_qty = stock_qty + :d WHERE id = :id');
            $st->execute([':d' => $delta, ':id' => $id]);
            return $st->rowCount() > 0;
        }

        $st = $pdo->prepare('
            UPDATE magazie_items
            SET stock_qty = stock_qty + :d
            WHERE id = :id
              AND (stock_qty + :d) >= 0
        ');
        $st->execute([':d' => $delta, ':id' => $id]);
        return $st->rowCount() > 0;
    }
}

