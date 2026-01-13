<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\StockStats;

final class DashboardController
{
    public static function index(): void
    {
        $byThickness = [];
        $topColors = [];
        $stockError = null;
        try {
            $byThickness = StockStats::availableByThickness();
            $rows = StockStats::availableByColorAndThickness();

            // Aggregate per color_id: totals + per-thickness mp
            $tmp = [];
            foreach ($rows as $r) {
                $cid = (int)$r['face_color_id'];
                if (!isset($tmp[$cid])) {
                    $tmp[$cid] = [
                        'face_color_id' => $cid,
                        'color_name' => (string)$r['color_name'],
                        'color_code' => (string)($r['color_code'] ?? ''),
                        'thumb_path' => (string)$r['thumb_path'],
                        'image_path' => $r['image_path'] ?? null,
                        'total_m2' => 0.0,
                        'total_qty' => 0,
                        'by_thickness' => [], // thickness_mm => m2
                    ];
                }
                $tmp[$cid]['total_m2'] += (float)$r['m2'];
                $tmp[$cid]['total_qty'] += (int)$r['qty'];
                $t = (int)$r['thickness_mm'];
                $tmp[$cid]['by_thickness'][$t] = ($tmp[$cid]['by_thickness'][$t] ?? 0.0) + (float)$r['m2'];
            }

            $topColors = array_values($tmp);
            usort($topColors, fn($a, $b) => ($b['total_m2'] <=> $a['total_m2']));
            $topColors = array_slice($topColors, 0, 6);

            // sort thickness keys asc
            foreach ($topColors as &$c) {
                ksort($c['by_thickness']);
            }
        } catch (\Throwable $e) {
            $stockError = $e->getMessage();
        }

        echo View::render('dashboard/index', [
            'title' => 'Panou',
            'byThickness' => $byThickness,
            'topColors' => $topColors,
            'stockError' => $stockError,
        ]);
    }
}

