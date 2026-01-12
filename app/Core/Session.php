<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Reasonable defaults
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_start();
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        self::start();
        if ($value === null) {
            $val = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return is_string($val) ? $val : null;
        }
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
}

