<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\View;
use App\Models\Offer;
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
        $readyProductsCount = null;
        $projectsInWorkCount = null;
        $latestOffers = [];
        $latestOffersError = null;
        $lowMagazieItems = [];
        $lowMagazieError = null;
        try {
            $byThickness = StockStats::availableByThickness();
            $topColors = self::buildTopColors(null, 6);
        } catch (\Throwable $e) {
            $stockError = $e->getMessage();
        }

        try {
            /** @var \PDO $pdo */
            $pdo = DB::pdo();
            $st = $pdo->prepare("
                SELECT COUNT(*) AS c
                FROM project_products pp
                INNER JOIN projects p ON p.id = pp.project_id
                WHERE pp.production_status IN ('GATA_DE_LIVRARE','GATA')
                  AND p.status NOT IN ('DRAFT','ANULAT','LIVRAT_COMPLET','FINALIZAT','ARHIVAT')
            ");
            $st->execute();
            $readyProductsCount = (int)($st->fetchColumn() ?? 0);
        } catch (\Throwable $e) {
            $readyProductsCount = null;
        }

        try {
            /** @var \PDO $pdo */
            $pdo = DB::pdo();
            $st = $pdo->prepare("
                SELECT COUNT(*) AS c
                FROM projects
                WHERE status NOT IN ('DRAFT','ANULAT','LIVRAT_COMPLET','FINALIZAT','ARHIVAT')
            ");
            $st->execute();
            $projectsInWorkCount = (int)($st->fetchColumn() ?? 0);
        } catch (\Throwable $e) {
            $projectsInWorkCount = null;
        }

        try {
            $latestOffers = Offer::all(null, null, 5);
        } catch (\Throwable $e) {
            $latestOffersError = $e->getMessage();
            $latestOffers = [];
        }

        try {
            /** @var \PDO $pdo */
            $pdo = DB::pdo();
            $st = $pdo->prepare('
                SELECT id, winmentor_code, name, unit, stock_qty
                FROM magazie_items
                ORDER BY stock_qty ASC, name ASC
                LIMIT 5
            ');
            $st->execute();
            $lowMagazieItems = $st->fetchAll();
        } catch (\Throwable $e) {
            $lowMagazieError = $e->getMessage();
            $lowMagazieItems = [];
        }

        echo View::render('dashboard/index', [
            'title' => 'Panou',
            'byThickness' => $byThickness,
            'topColors' => $topColors,
            'stockError' => $stockError,
            'readyProductsCount' => $readyProductsCount,
            'projectsInWorkCount' => $projectsInWorkCount,
            'latestOffers' => $latestOffers,
            'latestOffersError' => $latestOffersError,
            'lowMagazieItems' => $lowMagazieItems,
            'lowMagazieError' => $lowMagazieError,
        ]);
    }

    public static function apiTopColors(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : null;

        try {
            $qq = $q !== null ? trim($q) : '';
            // Pentru search afișăm mai multe rezultate (grid-ul poate avea mai multe rânduri),
            // dar când q e gol păstrăm aceeași logică ca pe dashboard (top 18).
            $limit = 6;
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

