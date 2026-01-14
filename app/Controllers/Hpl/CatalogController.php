<?php
declare(strict_types=1);

namespace App\Controllers\Hpl;

use App\Core\Env;
use App\Core\Response;
use App\Core\View;
use App\Models\Finish;
use App\Models\StockStats;

final class CatalogController
{
    /**
     * @return array{finishes: array<int, array<string,mixed>>, stockByFinish: array<int, array<int, float>>, totalByFinish: array<int, float>}
     */
    private static function build(?string $q): array
    {
        $finishes = Finish::search($q);

        // Stoc agregat pe Tip culoare (față) și grosimi (ignoră texturile)
        $rows = StockStats::availableByColorAndThickness(null);

        $wanted = [];
        foreach ($finishes as $f) {
            $wanted[(int)$f['id']] = true;
        }

        /** @var array<int, array<int, float>> $by */
        $by = [];
        /** @var array<int, float> $tot */
        $tot = [];
        foreach ($rows as $r) {
            $fid = (int)$r['face_color_id'];
            if (!isset($wanted[$fid])) continue;
            $t = (int)$r['thickness_mm'];
            $m2 = (float)$r['m2'];
            $by[$fid] ??= [];
            $by[$fid][$t] = ($by[$fid][$t] ?? 0.0) + $m2;
            $tot[$fid] = ($tot[$fid] ?? 0.0) + $m2;
        }

        // sort thickness keys asc
        foreach ($by as &$m) {
            ksort($m);
        }

        return [
            'finishes' => $finishes,
            'stockByFinish' => $by,
            'totalByFinish' => $tot,
        ];
    }

    public static function index(): void
    {
        try {
            $data = self::build(null);
            echo View::render('hpl/catalog/index', [
                'title' => 'Catalog',
                'finishes' => $data['finishes'],
                'stockByFinish' => $data['stockByFinish'],
                'totalByFinish' => $data['totalByFinish'],
            ]);
        } catch (\Throwable $e) {
            echo View::render('system/placeholder', [
                'title' => 'Catalog',
                'message' => 'Catalog indisponibil momentan. Rulează Setup dacă lipsesc tabelele de stoc.',
            ]);
        }
    }

    public static function apiGrid(): void
    {
        $q = isset($_GET['q']) ? (string)$_GET['q'] : null;
        try {
            $data = self::build($q);
            $html = View::render('hpl/catalog/_grid', [
                'finishes' => $data['finishes'],
                'stockByFinish' => $data['stockByFinish'],
                'totalByFinish' => $data['totalByFinish'],
            ]);
            Response::json(['ok' => true, 'html' => $html, 'count' => count($data['finishes'])]);
        } catch (\Throwable $e) {
            $payload = ['ok' => false, 'error' => 'Nu am putut încărca catalogul.'];
            if (Env::bool('APP_DEBUG', false)) {
                $payload['debug'] = $e->getMessage();
            }
            Response::json($payload, 500);
        }
    }
}

