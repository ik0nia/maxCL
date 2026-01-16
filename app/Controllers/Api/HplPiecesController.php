<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Env;
use App\Core\Response;
use App\Core\DB;

/**
 * Select2 endpoint pentru selectarea pieselor HPL:
 * - source=project: piese RESERVED din proiect (hpl_stock_pieces.project_id)
 * - source=rest: piese AVAILABLE "REST" (is_accounting=0)
 */
final class HplPiecesController
{
    public static function search(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : (isset($_GET['term']) ? (string)$_GET['term'] : '');
        $q = trim($q);
        $source = strtoupper(trim((string)($_GET['source'] ?? 'PROJECT')));
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

        try {
            /** @var \PDO $pdo */
            $pdo = DB::pdo();

            $items = [];
            $limit = 50;

            if ($source === 'REST') {
                $sql = "
                    SELECT sp.*, b.code AS board_code, b.name AS board_name, b.thickness_mm, b.std_width_mm, b.std_height_mm
                    FROM hpl_stock_pieces sp
                    INNER JOIN hpl_boards b ON b.id = sp.board_id
                    WHERE sp.qty > 0
                      AND sp.status = 'AVAILABLE'
                      AND sp.piece_type = 'FULL'
                      AND sp.is_accounting = 0
                ";
                $params = [];
                if ($q !== '') {
                    $sql .= " AND (b.code LIKE ? OR b.name LIKE ? OR sp.notes LIKE ?)";
                    $qq = '%' . $q . '%';
                    $params[] = $qq; $params[] = $qq; $params[] = $qq;
                }
                $sql .= " ORDER BY sp.created_at DESC, sp.id DESC LIMIT " . (int)$limit;
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll();
            } else {
                // PROJECT
                if ($projectId <= 0) {
                    Response::json(['ok' => true, 'q' => $q, 'count' => 0, 'items' => []]);
                }
                $sql = "
                    SELECT sp.*, b.code AS board_code, b.name AS board_name, b.thickness_mm, b.std_width_mm, b.std_height_mm
                    FROM hpl_stock_pieces sp
                    INNER JOIN hpl_boards b ON b.id = sp.board_id
                    WHERE sp.qty > 0
                      AND sp.status = 'RESERVED'
                      AND (sp.project_id = ?)
                ";
                $params = [(int)$projectId];
                if ($q !== '') {
                    $sql .= " AND (b.code LIKE ? OR b.name LIKE ? OR sp.notes LIKE ?)";
                    $qq = '%' . $q . '%';
                    $params[] = $qq; $params[] = $qq; $params[] = $qq;
                }
                $sql .= " ORDER BY sp.created_at DESC, sp.id DESC LIMIT " . (int)$limit;
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll();
            }

            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                $bid = (int)($r['board_id'] ?? 0);
                $bcode = (string)($r['board_code'] ?? '');
                $bname = (string)($r['board_name'] ?? '');
                $t = (int)($r['thickness_mm'] ?? 0);
                $w = (int)($r['width_mm'] ?? 0);
                $h = (int)($r['height_mm'] ?? 0);
                $qty = (int)($r['qty'] ?? 0);
                $loc = (string)($r['location'] ?? '');
                $pt = (string)($r['piece_type'] ?? '');

                $text = trim($bcode . ' · ' . $bname);
                $dims = ($t > 0 ? ($t . 'mm') : '') . ' · ' . ($h > 0 && $w > 0 ? ($h . '×' . $w . 'mm') : '');
                $text = trim($text . ' · ' . $dims);
                $text .= ' · ' . ($pt !== '' ? $pt : 'PIESĂ');
                if ($loc !== '') $text .= ' · ' . $loc;
                $text .= ' · buc: ' . $qty;

                $items[] = [
                    'id' => $id,
                    'text' => $text,
                    'board_id' => $bid,
                    'piece_type' => $pt,
                    'qty' => $qty,
                    'location' => $loc,
                    'source' => $source === 'REST' ? 'REST' : 'PROJECT',
                ];
            }

            Response::json(['ok' => true, 'q' => $q, 'count' => count($items), 'items' => $items]);
        } catch (\Throwable $e) {
            $env = strtolower((string)Env::get('APP_ENV', 'prod'));
            $debug = Env::bool('APP_DEBUG', false) || ($env !== 'prod' && $env !== 'production');
            Response::json([
                'ok' => false,
                'error' => 'Nu pot încărca piesele HPL. (API)',
                'debug' => $debug ? mb_substr($e->getMessage(), 0, 400) : null,
            ], 500);
        }
    }
}

