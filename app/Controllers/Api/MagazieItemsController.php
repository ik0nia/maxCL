<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Env;
use App\Core\Response;
use App\Models\MagazieItem;

final class MagazieItemsController
{
    public static function search(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : (isset($_GET['term']) ? (string)$_GET['term'] : '');
        $q = trim($q);
        if ($q === '') {
            Response::json(['ok' => true, 'q' => $q, 'count' => 0, 'items' => []]);
        }

        try {
            $rows = MagazieItem::all($q, 30);
            $items = [];
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                $code = (string)($r['winmentor_code'] ?? '');
                $name = (string)($r['name'] ?? '');
                $unit = (string)($r['unit'] ?? 'buc');
                $qty = isset($r['stock_qty']) && $r['stock_qty'] !== null && $r['stock_qty'] !== '' ? (float)$r['stock_qty'] : 0.0;
                $text = trim($code . ' · ' . $name);
                $text .= ' · stoc: ' . number_format($qty, 3, '.', '') . ' ' . $unit;
                $items[] = ['id' => $id, 'text' => $text, 'unit' => $unit];
            }
            Response::json(['ok' => true, 'q' => $q, 'count' => count($items), 'items' => $items]);
        } catch (\Throwable $e) {
            $env = strtolower((string)Env::get('APP_ENV', 'prod'));
            $debug = Env::bool('APP_DEBUG', false) || ($env !== 'prod' && $env !== 'production');
            Response::json([
                'ok' => false,
                'error' => 'Nu pot încărca accesoriile. (API)',
                'debug' => $debug ? mb_substr($e->getMessage(), 0, 400) : null,
            ], 500);
        }
    }
}

