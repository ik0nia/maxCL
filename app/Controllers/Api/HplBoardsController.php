<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Env;
use App\Core\DB;
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

    public static function offcuts(): void
    {
        $boardId = isset($_GET['board_id']) ? (int)$_GET['board_id'] : 0;
        if ($boardId <= 0) {
            Response::json(['ok' => true, 'board_id' => $boardId, 'count' => 0, 'items' => []]);
            return;
        }

        try {
            /** @var \PDO $pdo */
            $pdo = DB::pdo();
            $rows = [];
            try {
                $st = $pdo->prepare("
                    SELECT width_mm, height_mm, COALESCE(SUM(qty), 0) AS qty
                    FROM hpl_stock_pieces
                    WHERE board_id = ?
                      AND piece_type = 'OFFCUT'
                      AND status = 'AVAILABLE'
                      AND qty > 0
                      AND (is_accounting = 1 OR is_accounting IS NULL)
                    GROUP BY width_mm, height_mm
                    ORDER BY (width_mm * height_mm) DESC, width_mm DESC, height_mm DESC
                ");
                $st->execute([$boardId]);
                $rows = $st->fetchAll();
            } catch (\Throwable $e) {
                $st = $pdo->prepare("
                    SELECT width_mm, height_mm, COALESCE(SUM(qty), 0) AS qty
                    FROM hpl_stock_pieces
                    WHERE board_id = ?
                      AND piece_type = 'OFFCUT'
                      AND status = 'AVAILABLE'
                      AND qty > 0
                    GROUP BY width_mm, height_mm
                    ORDER BY (width_mm * height_mm) DESC, width_mm DESC, height_mm DESC
                ");
                $st->execute([$boardId]);
                $rows = $st->fetchAll();
            }

            $items = [];
            foreach ($rows as $r) {
                $w = (int)($r['width_mm'] ?? 0);
                $h = (int)($r['height_mm'] ?? 0);
                $qty = (int)($r['qty'] ?? 0);
                if ($w <= 0 || $h <= 0 || $qty <= 0) continue;
                $dim = $w . 'x' . $h;
                $label = $h . '×' . $w . ' mm · ' . $qty . ' buc';
                $items[] = [
                    'id' => $dim,
                    'dim' => $dim,
                    'width_mm' => $w,
                    'height_mm' => $h,
                    'qty' => $qty,
                    'text' => $label,
                ];
            }

            Response::json(['ok' => true, 'board_id' => $boardId, 'count' => count($items), 'items' => $items]);
        } catch (\Throwable $e) {
            $env = strtolower((string)Env::get('APP_ENV', 'prod'));
            $debug = Env::bool('APP_DEBUG', false) || ($env !== 'prod' && $env !== 'production');
            Response::json([
                'ok' => false,
                'error' => 'Nu pot încărca resturile disponibile. (API)',
                'debug' => $debug ? mb_substr($e->getMessage(), 0, 400) : null,
            ], 500);
        }
    }
}

