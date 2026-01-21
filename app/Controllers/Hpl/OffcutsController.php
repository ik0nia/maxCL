<?php
declare(strict_types=1);

namespace App\Controllers\Hpl;

use App\Core\DB;
use App\Core\Response;
use App\Core\Url;
use App\Core\View;
use App\Models\HplOffcuts;

final class OffcutsController
{
    private const BUCKETS = ['gt_half', 'half_to_quarter', 'lt_quarter'];

    public static function index(): void
    {
        $bucket = isset($_GET['bucket']) ? trim((string)$_GET['bucket']) : '';
        if ($bucket !== '' && !in_array($bucket, self::BUCKETS, true)) {
            Response::redirect('/hpl/bucati-rest');
        }

        $rows = HplOffcuts::nonStandardPieces(6000);

        // Calcul bucket în PHP (mai robust / compat)
        $items = [];
        $counts = ['all' => 0, 'gt_half' => 0, 'half_to_quarter' => 0, 'lt_quarter' => 0];

        foreach ($rows as $r) {
            $stdW = (int)($r['std_width_mm'] ?? 0);
            $stdH = (int)($r['std_height_mm'] ?? 0);
            $w = (int)($r['width_mm'] ?? 0);
            $h = (int)($r['height_mm'] ?? 0);
            $ratio = null;
            if ($stdW > 0 && $stdH > 0 && $w > 0 && $h > 0) {
                $ratio = ($w * $h) / ($stdW * $stdH);
            }
            $b = 'lt_quarter';
            if ($ratio !== null) {
                if ($ratio > 0.5) $b = 'gt_half';
                elseif ($ratio >= 0.25) $b = 'half_to_quarter';
                else $b = 'lt_quarter';
            }

            $counts['all']++;
            $counts[$b]++;

            $r['_area_ratio'] = $ratio;
            $r['_bucket'] = $b;
            if ($bucket === '' || $bucket === $b) {
                $items[] = $r;
            }
        }

        // Atașează ultima poză (dacă există) pentru fiecare piesă.
        $photoByPieceId = [];
        $pieceIds = [];
        foreach ($items as $it) {
            $pid = (int)($it['piece_id'] ?? 0);
            if ($pid > 0) $pieceIds[] = $pid;
        }
        $pieceIds = array_values(array_unique($pieceIds));
        if ($pieceIds) {
            try {
                /** @var \PDO $pdo */
                $pdo = DB::pdo();
                $chunks = array_chunk($pieceIds, 500);
                foreach ($chunks as $chunk) {
                    $ph = implode(',', array_fill(0, count($chunk), '?'));
                    $st = $pdo->prepare("
                        SELECT entity_id, stored_name, original_name, mime, category, created_at, id
                        FROM entity_files
                        WHERE entity_type = 'hpl_stock_pieces'
                          AND entity_id IN ($ph)
                          AND (category = 'internal_piece_photo' OR mime LIKE 'image/%')
                        ORDER BY created_at DESC, id DESC
                    ");
                    $st->execute($chunk);
                    $rowsFiles = $st->fetchAll();
                    foreach ($rowsFiles as $rf) {
                        $eid = (int)($rf['entity_id'] ?? 0);
                        if ($eid <= 0 || isset($photoByPieceId[$eid])) continue;
                        $stored = (string)($rf['stored_name'] ?? '');
                        if ($stored === '') continue;
                        $photoByPieceId[$eid] = [
                            'stored_name' => $stored,
                            'original_name' => (string)($rf['original_name'] ?? ''),
                            'mime' => (string)($rf['mime'] ?? ''),
                            'url' => Url::to('/uploads/files/' . $stored),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $photoByPieceId = [];
            }
        }
        if ($photoByPieceId) {
            foreach ($items as &$it) {
                $pid = (int)($it['piece_id'] ?? 0);
                if ($pid > 0 && isset($photoByPieceId[$pid])) {
                    $it['_photo'] = $photoByPieceId[$pid];
                }
            }
            unset($it);
        }

        echo View::render('hpl/offcuts/index', [
            'title' => 'Bucăți rest',
            'bucket' => $bucket,
            'counts' => $counts,
            'items' => $items,
        ]);
    }
}

