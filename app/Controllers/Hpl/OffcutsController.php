<?php
declare(strict_types=1);

namespace App\Controllers\Hpl;

use App\Core\Response;
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

        echo View::render('hpl/offcuts/index', [
            'title' => 'Bucăți rest',
            'bucket' => $bucket,
            'counts' => $counts,
            'items' => $items,
        ]);
    }
}

