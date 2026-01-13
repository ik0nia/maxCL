<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Client
{
    /** @return array<int, array<string,mixed>> */
    public static function allWithProjects(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = "
            SELECT
              c.*,
              COUNT(p.id) AS project_count,
              GROUP_CONCAT(CONCAT(p.code, ' · ', p.name) ORDER BY p.created_at DESC SEPARATOR '||') AS project_list
            FROM clients c
            LEFT JOIN projects p ON p.client_id = c.id
            GROUP BY c.id
            ORDER BY c.name ASC
        ";
        return $pdo->query($sql)->fetchAll();
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
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
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

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM clients WHERE id = ?');
        $st->execute([$id]);
    }
}

