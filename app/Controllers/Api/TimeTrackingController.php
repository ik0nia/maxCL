<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Env;
use App\Core\Response;
use App\Models\ProjectTimeLog;

final class TimeTrackingController
{
    public static function peopleSearch(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : (isset($_GET['term']) ? (string)$_GET['term'] : '');
        $q = trim($q);
        if ($q === '') {
            Response::json(['ok' => true, 'q' => $q, 'count' => 0, 'items' => []]);
        }

        try {
            $rows = ProjectTimeLog::searchPeople($q, 20);
            $items = [];
            foreach ($rows as $name) {
                $items[] = ['id' => $name, 'text' => $name];
            }
            Response::json(['ok' => true, 'q' => $q, 'count' => count($items), 'items' => $items]);
        } catch (\Throwable $e) {
            $env = strtolower((string)Env::get('APP_ENV', 'prod'));
            $debug = Env::bool('APP_DEBUG', false) || ($env !== 'prod' && $env !== 'production');
            Response::json([
                'ok' => false,
                'error' => 'Nu pot incarca persoanele. (API)',
                'debug' => $debug ? mb_substr($e->getMessage(), 0, 400) : null,
            ], 500);
        }
    }

    public static function descriptionSearch(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : (isset($_GET['term']) ? (string)$_GET['term'] : '');
        $q = trim($q);
        $category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
        if ($q === '') {
            Response::json(['ok' => true, 'q' => $q, 'count' => 0, 'items' => []]);
        }

        try {
            $rows = ProjectTimeLog::searchDescriptions($q, $category !== '' ? $category : null, 20);
            $items = [];
            foreach ($rows as $desc) {
                $items[] = ['id' => $desc, 'text' => $desc];
            }
            Response::json(['ok' => true, 'q' => $q, 'count' => count($items), 'items' => $items]);
        } catch (\Throwable $e) {
            $env = strtolower((string)Env::get('APP_ENV', 'prod'));
            $debug = Env::bool('APP_DEBUG', false) || ($env !== 'prod' && $env !== 'production');
            Response::json([
                'ok' => false,
                'error' => 'Nu pot incarca descrierile. (API)',
                'debug' => $debug ? mb_substr($e->getMessage(), 0, 400) : null,
            ], 500);
        }
    }
}

