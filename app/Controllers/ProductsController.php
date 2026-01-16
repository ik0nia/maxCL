<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class ProductsController
{
    public static function index(): void
    {
        $q = isset($_GET['q']) ? trim((string)($_GET['q'] ?? '')) : '';
        $label = isset($_GET['label']) ? trim((string)($_GET['label'] ?? '')) : '';
        try {
            /** @var \PDO $pdo */
            $pdo = \App\Core\DB::pdo();
            $where = [];
            $params = [];
            // Cerință: aici afișăm doar piesele care au minim "GATA_DE_LIVRARE".
            $where[] = "pp.production_status IN ('GATA_DE_LIVRARE','AVIZAT','LIVRAT')";
            if ($q !== '') {
                $where[] = '(p.name LIKE :q OR p.code LIKE :q OR pr.code LIKE :q OR pr.name LIKE :q)';
                $params[':q'] = '%' . $q . '%';
            }
            if ($label !== '') {
                $where[] = 'l.name = :lname';
                $params[':lname'] = $label;
            }
            $sql = '
                SELECT
                  pp.id AS project_product_id,
                  pp.qty, pp.unit, pp.production_status, pp.delivered_qty,
                  p.id AS product_id, p.code AS product_code, p.name AS product_name,
                  pr.id AS project_id, pr.code AS project_code, pr.name AS project_name, pr.status AS project_status,
                  GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ", ") AS labels
                FROM project_products pp
                INNER JOIN products p ON p.id = pp.product_id
                INNER JOIN projects pr ON pr.id = pp.project_id
                LEFT JOIN entity_labels el ON el.entity_type = "project_products" AND el.entity_id = pp.id
                LEFT JOIN labels l ON l.id = el.label_id
            ';
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' GROUP BY pp.id ORDER BY pp.id DESC LIMIT 1500';
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();

            echo View::render('products/index', [
                'title' => 'Produse',
                'rows' => $rows,
                'q' => $q,
                'label' => $label,
            ]);
        } catch (\Throwable $e) {
            echo View::render('system/placeholder', [
                'title' => 'Produse',
                'message' => 'Produsele nu sunt disponibile momentan. Rulează Update DB dacă lipsește tabela products.',
            ]);
        }
    }
}

