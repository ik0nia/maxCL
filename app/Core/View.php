<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): string
    {
        $base = dirname(__DIR__) . '/Views/';
        $file = $base . $view . '.php';
        if (!is_file($file)) {
            return '<h1>View lipsă</h1><p>Nu există: ' . htmlspecialchars($view) . '</p>';
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}

