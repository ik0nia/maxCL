<?php
declare(strict_types=1);

namespace App\Controllers\Hpl;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Upload;
use App\Core\Url;
use App\Core\View;
use App\Models\EntityFile;
use App\Models\HplOffcuts;
use App\Models\HplStockPiece;

final class OffcutsController
{
    private const BUCKETS = ['gt_half', 'half_to_quarter', 'lt_quarter'];

    public static function index(): void
    {
        $bucket = isset($_GET['bucket']) ? trim((string)$_GET['bucket']) : '';
        $scrapOnly = isset($_GET['scrap']) && (string)$_GET['scrap'] === '1';
        $showAccounting = isset($_GET['accounting']) && (string)$_GET['accounting'] === '1';
        $colorId = isset($_GET['color_id']) ? (int)$_GET['color_id'] : 0;
        if ($bucket !== '' && !in_array($bucket, self::BUCKETS, true)) {
            Response::redirect('/hpl/bucati-rest');
        }
        if ($scrapOnly) $bucket = '';

        $rows = HplOffcuts::nonStandardPieces(6000);

        // Filtru: stoc contabil + listă culori disponibile.
        $colors = [];
        $filteredRows = [];
        foreach ($rows as $r) {
            $isAcc = (int)($r['is_accounting'] ?? 1);
            if (!$showAccounting && $isAcc === 1) {
                continue;
            }
            $faceId = (int)($r['face_color_id'] ?? 0);
            $backId = (int)($r['back_color_id'] ?? 0);
            if ($faceId > 0 && !isset($colors[$faceId])) {
                $colors[$faceId] = [
                    'id' => $faceId,
                    'code' => (string)($r['face_color_code'] ?? ''),
                    'name' => (string)($r['face_color_name'] ?? ''),
                    'thumb' => (string)($r['face_thumb_path'] ?? ''),
                    'image' => (string)($r['face_image_path'] ?? ''),
                ];
            }
            if ($backId > 0 && !isset($colors[$backId])) {
                $colors[$backId] = [
                    'id' => $backId,
                    'code' => (string)($r['back_color_code'] ?? ''),
                    'name' => (string)($r['back_color_name'] ?? ''),
                    'thumb' => (string)($r['back_thumb_path'] ?? ''),
                    'image' => (string)($r['back_image_path'] ?? ''),
                ];
            }
            if ($colorId > 0 && $faceId !== $colorId && $backId !== $colorId) {
                continue;
            }
            $filteredRows[] = $r;
        }

        if ($colors) {
            $colors = array_values($colors);
            usort($colors, function ($a, $b) {
                $ac = strtolower((string)($a['code'] ?? ''));
                $bc = strtolower((string)($b['code'] ?? ''));
                if ($ac === $bc) {
                    return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                }
                return strcmp($ac, $bc);
            });
        }

        // Calcul bucket în PHP (mai robust / compat)
        $items = [];
        $counts = ['all' => 0, 'gt_half' => 0, 'half_to_quarter' => 0, 'lt_quarter' => 0, 'scrap' => 0];

        foreach ($filteredRows as $r) {
            $stdW = (int)($r['std_width_mm'] ?? 0);
            $stdH = (int)($r['std_height_mm'] ?? 0);
            $w = (int)($r['width_mm'] ?? 0);
            $h = (int)($r['height_mm'] ?? 0);
            $status = (string)($r['status'] ?? '');
            $location = (string)($r['location'] ?? '');
            $isScrap = ($status === 'SCRAP' || $location === 'Depozit (Stricat)');
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

            if ($isScrap) {
                $counts['scrap']++;
                $r['_area_ratio'] = $ratio;
                $r['_bucket'] = $b;
                if ($scrapOnly) {
                    $items[] = $r;
                }
                continue;
            }

            $counts['all']++;
            $counts[$b]++;

            $r['_area_ratio'] = $ratio;
            $r['_bucket'] = $b;
            if (!$scrapOnly && ($bucket === '' || $bucket === $b)) {
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

        // Atașează info despre marcarea ca "stricat" (user + notă).
        $trashByPieceId = [];
        $scrapPieceIds = [];
        foreach ($items as $it) {
            $status = (string)($it['status'] ?? '');
            $location = (string)($it['location'] ?? '');
            if ($status === 'SCRAP' || $location === 'Depozit (Stricat)') {
                $pid = (int)($it['piece_id'] ?? 0);
                if ($pid > 0) $scrapPieceIds[] = $pid;
            }
        }
        $scrapPieceIds = array_values(array_unique($scrapPieceIds));
        if ($scrapPieceIds) {
            try {
                /** @var \PDO $pdo */
                $pdo = DB::pdo();
                $chunks = array_chunk($scrapPieceIds, 500);
                foreach ($chunks as $chunk) {
                    $ph = implode(',', array_fill(0, count($chunk), '?'));
                    $st = $pdo->prepare("
                        SELECT a.entity_id, a.meta_json, a.actor_user_id, a.created_at, u.name AS user_name
                        FROM audit_log a
                        LEFT JOIN users u ON u.id = a.actor_user_id
                        WHERE a.entity_type = 'hpl_stock_pieces'
                          AND a.action = 'HPL_STOCK_TRASH'
                          AND a.entity_id IN ($ph)
                        ORDER BY a.id DESC
                    ");
                    $st->execute($chunk);
                    $rowsAudit = $st->fetchAll();
                    foreach ($rowsAudit as $ar) {
                        $eid = (int)($ar['entity_id'] ?? 0);
                        if ($eid <= 0 || isset($trashByPieceId[$eid])) continue;
                        $meta = is_string($ar['meta_json'] ?? null) ? json_decode((string)$ar['meta_json'], true) : null;
                        $note = is_array($meta) ? trim((string)($meta['note'] ?? '')) : '';
                        $trashByPieceId[$eid] = [
                            'user_name' => (string)($ar['user_name'] ?? ''),
                            'user_id' => (int)($ar['actor_user_id'] ?? 0),
                            'note' => $note,
                            'created_at' => (string)($ar['created_at'] ?? ''),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $trashByPieceId = [];
            }
        }
        if ($trashByPieceId) {
            foreach ($items as &$it) {
                $pid = (int)($it['piece_id'] ?? 0);
                if ($pid > 0 && isset($trashByPieceId[$pid])) {
                    $it['_trash'] = $trashByPieceId[$pid];
                }
            }
            unset($it);
        }

        echo View::render('hpl/offcuts/index', [
            'title' => 'Bucăți rest',
            'bucket' => $bucket,
            'scrapOnly' => $scrapOnly,
            'showAccounting' => $showAccounting,
            'colorId' => $colorId,
            'colors' => $colors,
            'counts' => $counts,
            'items' => $items,
        ]);
    }

    public static function uploadPhoto(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $pieceId = (int)($params['pieceId'] ?? 0);
        $bucket = isset($_GET['bucket']) ? trim((string)$_GET['bucket']) : '';
        $return = '/hpl/bucati-rest' . ($bucket !== '' ? ('?bucket=' . rawurlencode($bucket)) : '');

        if ($pieceId <= 0) {
            Session::flash('toast_error', 'Piesă invalidă.');
            Response::redirect($return);
        }
        $piece = HplStockPiece::find($pieceId);
        if (!$piece) {
            Session::flash('toast_error', 'Piesă inexistentă.');
            Response::redirect($return);
        }
        if (empty($_FILES['photo']['name'] ?? '')) {
            Session::flash('toast_error', 'Alege o poză.');
            Response::redirect($return);
        }
        $photo = $_FILES['photo'];
        if (!isset($photo['error']) || (int)$photo['error'] !== UPLOAD_ERR_OK) {
            Session::flash('toast_error', 'Upload imagine eșuat.');
            Response::redirect($return);
        }
        $tmp = (string)($photo['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            Session::flash('toast_error', 'Fișier invalid.');
            Response::redirect($return);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
        ];
        if (!isset($allowed[$mime])) {
            Session::flash('toast_error', 'Format invalid. Acceptat: JPG/PNG/WEBP.');
            Response::redirect($return);
        }

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        $uploadedFs = null;
        $oldFiles = [];
        try {
            // ștergem pozele vechi (dacă există) pentru a înlocui
            $st = $pdo->prepare("
                SELECT id, stored_name
                FROM entity_files
                WHERE entity_type = 'hpl_stock_pieces'
                  AND entity_id = ?
                  AND (category = 'internal_piece_photo' OR mime LIKE 'image/%')
                ORDER BY id DESC
            ");
            $st->execute([$pieceId]);
            $oldFiles = $st->fetchAll();
            if ($oldFiles) {
                $ids = [];
                foreach ($oldFiles as $of) {
                    $fid = (int)($of['id'] ?? 0);
                    if ($fid > 0) $ids[] = $fid;
                }
                if ($ids) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare("DELETE FROM entity_files WHERE id IN ($ph)")->execute($ids);
                }
            }

            $up = Upload::saveEntityFile($photo);
            $uploadedFs = $up['fs_path'] ?? null;
            $fid = EntityFile::create([
                'entity_type' => 'hpl_stock_pieces',
                'entity_id' => $pieceId,
                'category' => 'internal_piece_photo',
                'original_name' => $up['original_name'],
                'stored_name' => $up['stored_name'],
                'mime' => $up['mime'],
                'size_bytes' => $up['size_bytes'],
                'uploaded_by' => Auth::id(),
            ]);
            Audit::log('FILE_UPLOAD', 'entity_files', $fid, null, null, [
                'message' => 'Upload foto piesă HPL: ' . $up['original_name'],
                'entity_type' => 'hpl_stock_pieces',
                'entity_id' => $pieceId,
                'stored_name' => $up['stored_name'],
            ]);
            $pdo->commit();

            // curățăm fișierele vechi de pe disc
            if ($oldFiles) {
                $dir = dirname(__DIR__, 2) . '/storage/uploads/files/';
                foreach ($oldFiles as $of) {
                    $stored = (string)($of['stored_name'] ?? '');
                    if ($stored !== '') {
                        $fs = $dir . $stored;
                        if (is_file($fs)) @unlink($fs);
                    }
                }
            }
            Session::flash('toast_success', 'Poză salvată.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            if (is_string($uploadedFs) && $uploadedFs !== '' && is_file($uploadedFs)) {
                @unlink($uploadedFs);
            }
            Session::flash('toast_error', 'Nu pot salva poza: ' . $e->getMessage());
        }
        Response::redirect($return);
    }

    public static function trashPiece(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $pieceId = (int)($params['pieceId'] ?? 0);
        $bucket = isset($_GET['bucket']) ? trim((string)$_GET['bucket']) : '';
        $return = '/hpl/bucati-rest' . ($bucket !== '' ? ('?bucket=' . rawurlencode($bucket)) : '');

        if ($pieceId <= 0) {
            Session::flash('toast_error', 'Piesă invalidă.');
            Response::redirect($return);
        }
        $note = trim((string)($_POST['note'] ?? ''));
        if ($note === '') {
            Session::flash('toast_error', 'Completează nota explicativă.');
            Response::redirect($return);
        }
        $piece = HplStockPiece::find($pieceId);
        if (!$piece) {
            Session::flash('toast_error', 'Piesă inexistentă.');
            Response::redirect($return);
        }

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $before = $piece;
            HplStockPiece::updateFields($pieceId, [
                'status' => 'SCRAP',
                'location' => 'Depozit (Stricat)',
            ]);
            HplStockPiece::appendNote($pieceId, $note);
            $pdo->commit();
            $after = HplStockPiece::find($pieceId) ?: $before;
            Audit::log('HPL_STOCK_TRASH', 'hpl_stock_pieces', $pieceId, $before, $after, [
                'message' => 'Piesă scoasă din stoc (Depozit Stricat).',
                'note' => $note,
            ]);
            Session::flash('toast_success', 'Piesa a fost scoasă din stoc.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot scoate piesa: ' . $e->getMessage());
        }
        Response::redirect($return);
    }
}

