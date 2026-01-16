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
                  b.id AS board_id, b.code AS board_code, b.name AS board_name, b.thickness_mm,
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
        } catch (\Throwable $e) {
            // fallback: lăsăm tabelele goale; toast-ul e afișat din layout dacă e setat
            $hplRows = [];
            $magRows = [];
        }

        echo View::render('system/material_consumptions', [
            'title' => 'Consumuri materiale',
            'tab' => $tab,
            'mode' => $mode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'hplRows' => $hplRows,
            'magRows' => $magRows,
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

