<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class User
{
    /** @return array<int, array<string,mixed>> */
    public static function all(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        return $pdo->query('SELECT id, email, name, role, is_active, last_login_at, created_at, updated_at FROM users ORDER BY created_at DESC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT id, email, name, role, is_active, last_login_at, created_at, updated_at FROM users WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function findAuthRow(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT id, email, name, role, is_active, password_hash, last_login_at, created_at, updated_at FROM users WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT id, email, name, role, is_active FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('INSERT INTO users (email, name, role, password_hash, is_active) VALUES (:email,:name,:role,:password_hash,:is_active)');
        $st->execute([
            ':email' => $data['email'],
            ':name' => $data['name'],
            ':role' => $data['role'],
            ':password_hash' => $data['password_hash'],
            ':is_active' => (int)$data['is_active'],
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('UPDATE users SET email=:email, name=:name, role=:role, is_active=:is_active WHERE id=:id');
        $st->execute([
            ':id' => $id,
            ':email' => $data['email'],
            ':name' => $data['name'],
            ':role' => $data['role'],
            ':is_active' => (int)$data['is_active'],
        ]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $st->execute([':id' => $id, ':h' => $passwordHash]);
    }
}

