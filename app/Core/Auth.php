<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_GESTIONAR = 'GESTIONAR';
    public const ROLE_OPERATOR = 'OPERATOR';
    public const ROLE_VIEW = 'VIZUALIZARE';

    public static function user(): ?array
    {
        Session::start();
        $u = $_SESSION['user'] ?? null;
        return is_array($u) ? $u : null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): callable
    {
        return function (): void {
            if (!self::check()) {
                $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
                $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
                $isApi = str_starts_with($path, '/api/') || str_contains($accept, 'application/json');
                if ($isApi) {
                    Response::json(['ok' => false, 'error' => 'Te rugăm să te autentifici.'], 401);
                }
                Session::flash('toast_error', 'Te rugăm să te autentifici.');
                Response::redirect('/login');
            }
        };
    }

    /**
     * @param array<int, string> $roles
     */
    public static function requireRole(array $roles): callable
    {
        return function () use ($roles): void {
            $u = self::user();
            if (!$u) {
                $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
                $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
                $isApi = str_starts_with($path, '/api/') || str_contains($accept, 'application/json');
                if ($isApi) {
                    Response::json(['ok' => false, 'error' => 'Te rugăm să te autentifici.'], 401);
                }
                Session::flash('toast_error', 'Te rugăm să te autentifici.');
                Response::redirect('/login');
            }
            if (!in_array((string)$u['role'], $roles, true)) {
                $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
                $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
                $isApi = str_starts_with($path, '/api/') || str_contains($accept, 'application/json');
                if ($isApi) {
                    Response::json(['ok' => false, 'error' => 'Acces interzis.'], 403);
                }
                http_response_code(403);
                echo View::render('errors/403', ['title' => 'Acces interzis']);
                exit;
            }
        };
    }

    public static function attempt(string $email, string $password): bool
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, email, name, role, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['is_active'] !== 1) {
            return false;
        }
        if (!password_verify($password, (string)$row['password_hash'])) {
            return false;
        }

        Session::start();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$row['id'],
            'email' => (string)$row['email'],
            'name' => (string)$row['name'],
            'role' => (string)$row['role'],
        ];

        try {
            $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$row['id']]);
        } catch (\Throwable $e) {
            // ignore
        }
        Audit::log('LOGIN', 'users', (int)$row['id'], null, null, ['email' => (string)$row['email']]);
        return true;
    }

    public static function logout(): void
    {
        Session::start();
        $u = self::user();
        unset($_SESSION['user']);
        session_regenerate_id(true);
        if ($u) {
            Audit::log('LOGOUT', 'users', (int)$u['id'], null, null, ['email' => (string)$u['email']], (int)$u['id']);
        }
    }
}

