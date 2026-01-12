<?php
declare(strict_types=1);

namespace App\Core;

final class Url
{
    private static string $basePath = '';

    public static function setBasePath(string $basePath): void
    {
        $basePath = str_replace('\\', '/', $basePath);
        $basePath = rtrim($basePath, '/');
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        }
        self::$basePath = $basePath;
    }

    public static function basePath(): string
    {
        if (self::$basePath !== '') {
            return self::$basePath;
        }

        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $dir = str_replace('\\', '/', dirname($script));
        $dir = rtrim($dir, '/');
        if ($dir === '.' || $dir === '/') {
            return '';
        }
        return $dir;
    }

    public static function to(string $path): string
    {
        if ($path === '') return self::basePath() . '/';
        if (preg_match('#^https?://#i', $path)) return $path;
        if ($path[0] !== '/') $path = '/' . $path;
        return self::basePath() . $path;
    }

    public static function asset(string $path): string
    {
        return self::to($path);
    }

    /**
     * Returnează calea curentă fără basePath (ex: "/login").
     */
    public static function currentPath(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
            if ($path === '') $path = '/';
        }
        return $path;
    }
}

