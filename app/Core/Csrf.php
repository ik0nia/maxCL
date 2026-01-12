<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    public static function verify(?string $token): void
    {
        Session::start();
        $expected = $_SESSION['_csrf'] ?? '';
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            http_response_code(419);
            echo 'Token CSRF invalid.';
            exit;
        }
        if (!hash_equals($expected, $token)) {
            http_response_code(419);
            echo 'Token CSRF invalid.';
            exit;
        }
    }
}

