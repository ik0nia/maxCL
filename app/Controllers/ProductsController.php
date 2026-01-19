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
            $docsByPp = [];
            $ppIds = [];
            foreach ($rows as $r) {
                $ppId = (int)($r['project_product_id'] ?? 0);
                if ($ppId > 0) $ppIds[] = $ppId;
            }
            $ppIds = array_values(array_unique($ppIds));
            if ($ppIds) {
                $ppIdSet = array_fill_keys($ppIds, true);
                $in = implode(',', array_fill(0, count($ppIds), '?'));
                $sqlFiles = '
                    SELECT id, entity_type, entity_id, category, original_name, stored_name, created_at
                    FROM entity_files
                    WHERE (entity_type = "project_products" AND entity_id IN (' . $in . '))
                       OR (entity_type = "projects" AND (
                            stored_name LIKE "deviz-%-pp%.html"
                            OR stored_name LIKE "bon-consum-%-pp%.html"
                          ))
                    ORDER BY created_at DESC, id DESC
                ';
                try {
                    $stFiles = $pdo->prepare($sqlFiles);
                    $stFiles->execute($ppIds);
                    $files = $stFiles->fetchAll();
                } catch (\Throwable $e) {
                    $files = [];
                }
                $auditByFileId = [];
                if ($files) {
                    $fileIds = [];
                    foreach ($files as $f) {
                        $fid = (int)($f['id'] ?? 0);
                        if ($fid > 0) $fileIds[] = $fid;
                    }
                    $fileIds = array_values(array_unique($fileIds));
                    if ($fileIds) {
                        try {
                            $ph = implode(',', array_fill(0, count($fileIds), '?'));
                            $stA = $pdo->prepare("
                                SELECT entity_id, action, meta_json
                                FROM audit_log
                                WHERE entity_type = 'entity_files'
                                  AND entity_id IN ($ph)
                                  AND action IN ('DEVIZ_GENERATED','BON_CONSUM_GENERATED')
                            ");
                            $stA->execute($fileIds);
                            $audRows = $stA->fetchAll();
                            foreach ($audRows as $ar) {
                                $fid = (int)($ar['entity_id'] ?? 0);
                                if ($fid <= 0) continue;
                                $meta = is_string($ar['meta_json'] ?? null) ? json_decode((string)$ar['meta_json'], true) : null;
                                $ppId = is_array($meta) && isset($meta['project_product_id']) && is_numeric($meta['project_product_id'])
                                    ? (int)$meta['project_product_id']
                                    : 0;
                                if ($ppId <= 0) continue;
                                $action = (string)($ar['action'] ?? '');
                                $type = $action === 'DEVIZ_GENERATED' ? 'deviz' : ($action === 'BON_CONSUM_GENERATED' ? 'bon' : null);
                                $auditByFileId[$fid] = ['ppId' => $ppId, 'type' => $type];
                            }
                        } catch (\Throwable $e) {
                            $auditByFileId = [];
                        }
                    }
                }
                foreach ($files as $f) {
                    $etype = (string)($f['entity_type'] ?? '');
                    $stored = (string)($f['stored_name'] ?? '');
                    $category = (string)($f['category'] ?? '');
                    $fid = (int)($f['id'] ?? 0);
                    $audit = $fid > 0 && isset($auditByFileId[$fid]) ? $auditByFileId[$fid] : null;
                    $ppId = 0;
                    if ($etype === 'project_products') {
                        $ppId = (int)($f['entity_id'] ?? 0);
                    } else {
                        if (preg_match('/-pp(\d+)(?:-\d+)?\.html$/', $stored, $m)) {
                            $ppId = (int)$m[1];
                        }
                    }
                    if ($ppId <= 0 && $audit && isset($audit['ppId'])) {
                        $ppId = (int)$audit['ppId'];
                    }
                    if ($ppId <= 0 || !isset($ppIdSet[$ppId])) continue;

                    $type = null;
                    if (str_starts_with($stored, 'deviz-') || stripos($category, 'deviz') !== false) {
                        $type = 'deviz';
                    } elseif (str_starts_with($stored, 'bon-consum-') || stripos($category, 'bon consum') !== false) {
                        $type = 'bon';
                    }
                    if ($type === null && $audit && !empty($audit['type'])) {
                        $type = (string)$audit['type'];
                    }
                    if ($type === null) continue;
                    if (isset($docsByPp[$ppId][$type])) continue;

                    $label = $type === 'deviz' ? 'Deviz' : 'Bon consum';
                    $num = '';
                    if (preg_match('/nr\.?\s*([0-9]+)/i', $category, $m)) {
                        $num = (string)$m[1];
                    } elseif (preg_match('/^(deviz|bon-consum)-(\d+)/', $stored, $m)) {
                        $num = (string)$m[2];
                    }
                    if ($num !== '') $label .= ' ' . $num;

                    $docsByPp[$ppId][$type] = [
                        'stored_name' => $stored,
                        'label' => $label,
                    ];
                }
            }

            echo View::render('products/index', [
                'title' => 'Produse',
                'rows' => $rows,
                'q' => $q,
                'label' => $label,
                'docsByPp' => $docsByPp,
            ]);
        } catch (\Throwable $e) {
            echo View::render('system/placeholder', [
                'title' => 'Produse',
                'message' => 'Produsele nu sunt disponibile momentan. Rulează Update DB dacă lipsește tabela products.',
            ]);
        }
    }
}

