<?php
declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\DB;
use App\Core\View;

final class MaterialConsumptionsController
{
    public static function index(): void
    {
        $tab = isset($_GET['tab']) ? trim((string)($_GET['tab'] ?? '')) : '';
        if ($tab === '') $tab = 'hpl';
        if (!in_array($tab, ['hpl', 'accesorii'], true)) $tab = 'hpl';

        $mode = isset($_GET['mode']) ? strtoupper(trim((string)($_GET['mode'] ?? ''))) : '';
        if ($mode === '') $mode = 'CONSUMED';
        if (!in_array($mode, ['CONSUMED', 'RESERVED', 'ALL'], true)) $mode = 'CONSUMED';

        $df = isset($_GET['date_from']) ? trim((string)($_GET['date_from'] ?? '')) : '';
        $dt = isset($_GET['date_to']) ? trim((string)($_GET['date_to'] ?? '')) : '';
        $dateFrom = self::normalizeDate($df);
        $dateTo = self::normalizeDate($dt);

        $hplRows = [];
        $magRows = [];
        $hplAgg = [];
        $magAgg = [];

        try {
            /** @var \PDO $pdo */
            $pdo = DB::pdo();

            // HPL consumptions
            $where = [];
            $params = [];
            if ($mode !== 'ALL') {
                $where[] = 'c.mode = :mode';
                $params[':mode'] = $mode;
            }
            if ($dateFrom !== null) {
                $where[] = 'c.created_at >= :df';
                $params[':df'] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo !== null) {
                $where[] = 'c.created_at <= :dt';
                $params[':dt'] = $dateTo . ' 23:59:59';
            }
            $sql = "
                SELECT
                  c.id, c.created_at, c.mode, c.qty_boards, c.qty_m2, c.note,
                  b.id AS board_id, b.code AS board_code, b.name AS board_name, b.thickness_mm, b.std_width_mm, b.std_height_mm,
                  pr.id AS project_id, pr.code AS project_code, pr.name AS project_name,
                  u.name AS user_name, u.email AS user_email
                FROM project_hpl_consumptions c
                INNER JOIN projects pr ON pr.id = c.project_id
                INNER JOIN hpl_boards b ON b.id = c.board_id
                LEFT JOIN users u ON u.id = c.created_by
            ";
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ORDER BY c.created_at DESC, c.id DESC LIMIT 5000';
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $hplRows = $st->fetchAll();

            // Magazie consumptions
            $where = [];
            $params = [];
            if ($mode !== 'ALL') {
                $where[] = 'c.mode = :mode';
                $params[':mode'] = $mode;
            }
            if ($dateFrom !== null) {
                $where[] = 'c.created_at >= :df';
                $params[':df'] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo !== null) {
                $where[] = 'c.created_at <= :dt';
                $params[':dt'] = $dateTo . ' 23:59:59';
            }
            $sql = "
                SELECT
                  c.id, c.created_at, c.mode, c.qty, c.unit, c.note,
                  mi.id AS item_id, mi.winmentor_code, mi.name AS item_name, mi.unit_price,
                  pr.id AS project_id, pr.code AS project_code, pr.name AS project_name,
                  pp.id AS project_product_id,
                  p.name AS product_name,
                  u.name AS user_name, u.email AS user_email
                FROM project_magazie_consumptions c
                INNER JOIN projects pr ON pr.id = c.project_id
                INNER JOIN magazie_items mi ON mi.id = c.item_id
                LEFT JOIN project_products pp ON pp.id = c.project_product_id
                LEFT JOIN products p ON p.id = pp.product_id
                LEFT JOIN users u ON u.id = c.created_by
            ";
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ORDER BY c.created_at DESC, c.id DESC LIMIT 8000';
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $magRows = $st->fetchAll();

            // Aggregations (best-effort, in PHP)
            foreach ($hplRows as $r) {
                $bid = (int)($r['board_id'] ?? 0);
                $m = (string)($r['mode'] ?? '');
                $key = $bid . '|' . $m;
                if (!isset($hplAgg[$key])) {
                    $hplAgg[$key] = [
                        'board_id' => $bid,
                        'mode' => $m,
                        'board_code' => (string)($r['board_code'] ?? ''),
                        'board_name' => (string)($r['board_name'] ?? ''),
                        'thickness_mm' => (int)($r['thickness_mm'] ?? 0),
                        'std_width_mm' => (int)($r['std_width_mm'] ?? 0),
                        'std_height_mm' => (int)($r['std_height_mm'] ?? 0),
                        'sum_qty_boards' => 0.0,
                        'sum_qty_m2' => 0.0,
                        'rows' => 0,
                    ];
                }
                $qb = isset($r['qty_boards']) ? (float)($r['qty_boards'] ?? 0) : 0.0;
                $qm2 = isset($r['qty_m2']) ? (float)($r['qty_m2'] ?? 0) : 0.0;
                $hplAgg[$key]['sum_qty_boards'] += $qb;
                $hplAgg[$key]['sum_qty_m2'] += $qm2;
                $hplAgg[$key]['rows'] += 1;
            }
            foreach ($magRows as $r) {
                $iid = (int)($r['item_id'] ?? 0);
                $m = (string)($r['mode'] ?? '');
                $key = $iid . '|' . $m;
                if (!isset($magAgg[$key])) {
                    $magAgg[$key] = [
                        'item_id' => $iid,
                        'mode' => $m,
                        'winmentor_code' => (string)($r['winmentor_code'] ?? ''),
                        'item_name' => (string)($r['item_name'] ?? ''),
                        'unit' => (string)($r['unit'] ?? ''),
                        'unit_price' => (isset($r['unit_price']) && $r['unit_price'] !== null && $r['unit_price'] !== '' && is_numeric($r['unit_price'])) ? (float)$r['unit_price'] : null,
                        'sum_qty' => 0.0,
                        'sum_value' => 0.0,
                        'rows' => 0,
                    ];
                }
                $q = isset($r['qty']) ? (float)($r['qty'] ?? 0) : 0.0;
                $magAgg[$key]['sum_qty'] += $q;
                $up = $magAgg[$key]['unit_price'];
                if ($up !== null) $magAgg[$key]['sum_value'] += ($up * $q);
                $magAgg[$key]['rows'] += 1;
            }
            $hplAgg = array_values($hplAgg);
            $magAgg = array_values($magAgg);
        } catch (\Throwable $e) {
            // fallback: lăsăm tabelele goale; toast-ul e afișat din layout dacă e setat
            $hplRows = [];
            $magRows = [];
            $hplAgg = [];
            $magAgg = [];
        }

        echo View::render('system/material_consumptions', [
            'title' => 'Consumuri materiale',
            'tab' => $tab,
            'mode' => $mode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'hplRows' => $hplRows,
            'magRows' => $magRows,
            'hplAgg' => $hplAgg,
            'magAgg' => $magAgg,
        ]);
    }

    private static function normalizeDate(string $d): ?string
    {
        $d = trim($d);
        if ($d === '') return null;
        // accept strict YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
        return $d;
    }
}

