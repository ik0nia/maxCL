<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Env;
use App\Core\Response;
use App\Models\Finish;

final class FinishesController
{
    public static function search(): void
    {
        // Acceptă atât q (custom) cât și term (compat select2)
        $q = isset($_GET['q']) ? (string)$_GET['q'] : (isset($_GET['term']) ? (string)$_GET['term'] : '');
        try {
            $items = Finish::searchForSelect($q, 25);
            Response::json(['ok' => true, 'q' => $q, 'count' => count($items), 'items' => $items]);
        } catch (\Throwable $e) {
            $env = strtolower((string)Env::get('APP_ENV', 'prod'));
            $debug = ($env !== 'prod' && $env !== 'production');
            Response::json([
                'ok' => false,
                'error' => 'Nu pot încărca sugestiile. (API)',
                'debug' => $debug ? mb_substr($e->getMessage(), 0, 400) : null,
            ], 500);
        }
    }
}

