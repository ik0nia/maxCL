<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\StockStats;

final class DashboardController
{
    /**
     * @return array<int, array{
     *   face_color_id:int,
     *   color_name:string,
     *   color_code:string,
     *   thumb_path:string,
     *   image_path:?string,
     *   total_m2:float,
     *   total_qty:int,
     *   by_thickness:array<int, float>
     * }>
     */
    private static function buildTopColors(?string $q, int $limit): array
    {
        $rows = StockStats::availableByAnySideColorAndThickness($q);

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
        $topColors = array_slice($topColors, 0, max(0, $limit));

        // sort thickness keys asc
        foreach ($topColors as &$c) {
            ksort($c['by_thickness']);
        }

        return $topColors;
    }

    public static function index(): void
    {
        $byThickness = [];
        $topColors = [];
        $stockError = null;
        try {
            $byThickness = StockStats::availableByThickness();
            $topColors = self::buildTopColors(null, 18);
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

    public static function apiTopColors(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : null;

        try {
            $qq = $q !== null ? trim($q) : '';
            // Pentru search afișăm mai multe rezultate (grid-ul poate avea mai multe rânduri),
            // dar când q e gol păstrăm aceeași logică ca pe dashboard (top 18).
            $limit = ($qq === '') ? 18 : 36;
            $topColors = self::buildTopColors($q, $limit);
            $html = View::render('dashboard/_top_colors_grid', [
                'topColors' => $topColors,
            ]);
            \App\Core\Response::json([
                'ok' => true,
                'count' => count($topColors),
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            \App\Core\Response::json([
                'ok' => false,
                'error' => 'Nu am putut încărca rezultatele.',
            ], 500);
        }
    }
}

