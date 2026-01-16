<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Env;
use App\Core\Response;
use App\Models\HplBoard;

final class HplBoardsController
{
    public static function search(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : (isset($_GET['term']) ? (string)$_GET['term'] : '');
        try {
            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
            $reservedOnly = isset($_GET['reserved_only']) && (string)$_GET['reserved_only'] !== '' && (string)$_GET['reserved_only'] !== '0';
            if ($reservedOnly && $projectId > 0) {
                $items = HplBoard::searchReservedForProjectForSelect($projectId, $q, 50);
            } else {
                $items = HplBoard::searchForSelect($q, 25);
            }
            Response::json(['ok' => true, 'q' => $q, 'count' => count($items), 'items' => $items]);
        } catch (\Throwable $e) {
            $env = strtolower((string)Env::get('APP_ENV', 'prod'));
            $debug = Env::bool('APP_DEBUG', false) || ($env !== 'prod' && $env !== 'production');
            Response::json([
                'ok' => false,
                'error' => 'Nu pot încărca tipurile de plăci. (API)',
                'debug' => $debug ? mb_substr($e->getMessage(), 0, 400) : null,
            ], 500);
        }
    }
}

