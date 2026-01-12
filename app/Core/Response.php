<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

