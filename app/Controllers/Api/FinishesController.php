<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Response;
use App\Models\Finish;

final class FinishesController
{
    public static function search(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : '';
        try {
            $items = Finish::searchForSelect($q, 25);
            Response::json(['ok' => true, 'items' => $items]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => 'Nu pot încărca sugestiile.'], 500);
        }
    }
}

