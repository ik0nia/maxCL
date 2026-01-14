<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;

final class AuditController
{
    public static function index(): void
    {
        $userId = null;
        if (isset($_GET['user_id']) && (string)$_GET['user_id'] !== '') {
            $userId = Validator::int((string)$_GET['user_id'], 1);
        }

        $action = null;
        if (isset($_GET['action']) && (string)$_GET['action'] !== '') {
            $action = trim((string)$_GET['action']);
        }

        $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : null;
        $dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : null;

        $filters = [
            'user_id' => $userId,
            'action' => $action ?: null,
            'date_from' => $dateFrom ?: null,
            'date_to' => $dateTo ?: null,
        ];

        $rows = AuditLog::list($filters, 800);
        echo View::render('audit/index', [
            'title' => 'Jurnal activitate',
            'rows' => $rows,
            'filters' => $filters,
            'users' => AuditLog::usersForFilter(),
            'actions' => AuditLog::actionsForFilter(),
        ]);
    }

    public static function apiShow(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $row = AuditLog::find($id);
        if (!$row) {
            Response::json(['ok' => false, 'error' => 'Înregistrare inexistentă.'], 404);
        }

        $before = $row['before_json'];
        $after = $row['after_json'];
        $meta = $row['meta_json'];

        // Decode JSON columns to arrays when possible (for nicer UI)
        $beforeDecoded = is_string($before) ? json_decode($before, true) : null;
        $afterDecoded = is_string($after) ? json_decode($after, true) : null;
        $metaDecoded = is_string($meta) ? json_decode($meta, true) : null;
        $message = (is_array($metaDecoded) && isset($metaDecoded['message']) && is_string($metaDecoded['message'])) ? $metaDecoded['message'] : null;

        // Fallback pentru log-uri vechi (ex: STOCK_PIECE_DELETE fără Placă:)
        if (!$message && (string)$row['action'] === 'STOCK_PIECE_DELETE' && is_array($beforeDecoded)) {
            $boardId = isset($beforeDecoded['board_id']) ? (int)$beforeDecoded['board_id'] : 0;
            if ($boardId > 0) {
                try {
                    $pdo = \App\Core\DB::pdo();
                    $st = $pdo->prepare('SELECT code,name,brand,thickness_mm,std_width_mm,std_height_mm FROM hpl_boards WHERE id = ?');
                    $st->execute([$boardId]);
                    $b = $st->fetch();
                    if ($b) {
                        $message = 'A șters piesă '
                            . (string)($beforeDecoded['piece_type'] ?? '')
                            . ' ' . (int)($beforeDecoded['height_mm'] ?? 0) . '×' . (int)($beforeDecoded['width_mm'] ?? 0) . ' mm, '
                            . (int)($beforeDecoded['qty'] ?? 0) . ' buc'
                            . ((isset($beforeDecoded['location']) && $beforeDecoded['location'] !== '') ? (', locație ' . (string)$beforeDecoded['location']) : '')
                            . ' · Placă: ' . (string)$b['code'] . ' · ' . (string)$b['name'] . ' · ' . (string)$b['brand']
                            . ' · ' . (int)$b['thickness_mm'] . 'mm · ' . (int)$b['std_height_mm'] . '×' . (int)$b['std_width_mm'];
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        Response::json([
            'ok' => true,
            'data' => [
                'id' => (int)$row['id'],
                'created_at' => (string)$row['created_at'],
                'actor_user_id' => $row['actor_user_id'],
                'action' => (string)$row['action'],
                'entity_type' => $row['entity_type'],
                'entity_id' => $row['entity_id'],
                'ip' => $row['ip'],
                'user_agent' => $row['user_agent'],
                'before_json' => $beforeDecoded ?? $before,
                'after_json' => $afterDecoded ?? $after,
                'meta_json' => $metaDecoded ?? $meta,
                'message' => $message,
            ],
        ]);
    }
}

