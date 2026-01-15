<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\HplBoard;
use App\Models\HplStockPiece;
use App\Models\MagazieItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectDelivery;
use App\Models\ProjectDeliveryItem;
use App\Models\ProjectHplAllocation;
use App\Models\ProjectHplConsumption;
use App\Models\ProjectMagazieConsumption;
use App\Models\ProjectProduct;
use App\Models\EntityFile;
use App\Core\Upload;
use App\Models\ProjectWorkLog;
use App\Models\AuditLog;
use App\Models\Label;
use App\Models\EntityLabel;
use App\Models\AppSetting;
use App\Models\EntityComment;
use App\Core\Env;

final class ProjectsController
{
    /**
     * @param array<int, array<string,mixed>> $projectProducts
     * @return array{ppIds:array<int,int>,qtyById:array<int,float>,sumQty:float,weightById:array<int,float>}
     */
    private static function productQtyWeights(array $projectProducts): array
    {
        $ppIds = [];
        $qtyById = [];
        foreach ($projectProducts as $pp) {
            $id = (int)($pp['id'] ?? 0);
            if ($id <= 0) continue;
            $ppIds[] = $id;
            $q = (float)($pp['qty'] ?? 0);
            $m2 = isset($pp['m2_per_unit']) ? (float)($pp['m2_per_unit'] ?? 0) : 0.0;
            // Weight base: mp_total dacă există, altfel cantitate (buc).
            $base = 0.0;
            if ($m2 > 0 && $q > 0) {
                $base = $m2 * $q;
            } elseif ($q > 0) {
                $base = $q;
            }
            $qtyById[$id] = $base;
        }
        $sumQty = 0.0;
        foreach ($ppIds as $id) $sumQty += (float)($qtyById[$id] ?? 0.0);

        $weightById = [];
        $n = count($ppIds);
        foreach ($ppIds as $id) {
            if ($sumQty > 0.0) $weightById[$id] = ((float)($qtyById[$id] ?? 0.0)) / $sumQty;
            elseif ($n > 0) $weightById[$id] = 1.0 / $n;
            else $weightById[$id] = 0.0;
        }
        return ['ppIds' => $ppIds, 'qtyById' => $qtyById, 'sumQty' => $sumQty, 'weightById' => $weightById];
    }

    /**
     * Greutăți doar după cantitate (buc) — folosit pentru allocation_mode=by_qty.
     * @param array<int, array<string,mixed>> $projectProducts
     * @return array{ppIds:array<int,int>,sumQty:float,weightById:array<int,float>}
     */
    private static function productQtyOnlyWeights(array $projectProducts): array
    {
        $ppIds = [];
        $qtyById = [];
        foreach ($projectProducts as $pp) {
            $id = (int)($pp['id'] ?? 0);
            if ($id <= 0) continue;
            $ppIds[] = $id;
            $q = (float)($pp['qty'] ?? 0);
            $qtyById[$id] = ($q > 0) ? $q : 0.0;
        }
        $sum = 0.0;
        foreach ($ppIds as $id) $sum += (float)($qtyById[$id] ?? 0.0);
        $n = count($ppIds);
        $weightById = [];
        foreach ($ppIds as $id) {
            if ($sum > 0.0) $weightById[$id] = ((float)($qtyById[$id] ?? 0.0)) / $sum;
            elseif ($n > 0) $weightById[$id] = 1.0 / $n;
            else $weightById[$id] = 0.0;
        }
        return ['ppIds' => $ppIds, 'sumQty' => $sum, 'weightById' => $weightById];
    }

    /**
     * Recalculează alocările HPL (mp) pentru TOATE consumurile din proiect,
     * în funcție de allocation_mode și produsele curente.
     * - nu rulează dacă allocations_locked=1 sau allocation_mode=manual
     */
    private static function recalcHplAllocationsForProject(int $projectId): void
    {
        if ($projectId <= 0) return;
        $project = Project::find($projectId);
        if (!$project) return;

        $locked = (int)($project['allocations_locked'] ?? 0) === 1;
        if ($locked) return;

        $allocMode = (string)($project['allocation_mode'] ?? 'by_area');
        if ($allocMode === 'manual') return;

        $pps = ProjectProduct::forProject($projectId);
        if (!$pps) return;

        $w = ($allocMode === 'by_qty') ? self::productQtyOnlyWeights($pps) : self::productQtyWeights($pps);
        /** @var array<int,int> $ppIds */
        $ppIds = $w['ppIds'];
        if (!$ppIds) return;

        $cons = ProjectHplConsumption::forProject($projectId);
        foreach ($cons as $c) {
            $cid = (int)($c['id'] ?? 0);
            if ($cid <= 0) continue;
            $qtyM2 = isset($c['qty_m2']) ? (float)$c['qty_m2'] : 0.0;
            if ($qtyM2 <= 0) {
                ProjectHplAllocation::replaceForConsumption($cid, []);
                continue;
            }

            $rows = [];
            $sumAlloc = 0.0;
            $lastId = 0;
            foreach ($ppIds as $ppId) {
                $weight = (float)($w['weightById'][$ppId] ?? 0.0);
                if ($weight <= 0) continue;
                $m2 = $qtyM2 * $weight;
                $rows[] = ['project_product_id' => $ppId, 'qty_m2' => $m2];
                $sumAlloc += $m2;
                $lastId = $ppId;
            }
            // Ajustare reziduală (floating point) pe ultima intrare, ca suma să fie exact qtyM2.
            if ($rows && $lastId > 0) {
                $diff = $qtyM2 - $sumAlloc;
                if (abs($diff) > 0.0000001) {
                    for ($i = count($rows) - 1; $i >= 0; $i--) {
                        if ((int)$rows[$i]['project_product_id'] === $lastId) {
                            $rows[$i]['qty_m2'] = max(0.0, (float)$rows[$i]['qty_m2'] + $diff);
                            break;
                        }
                    }
                }
            }

            ProjectHplAllocation::replaceForConsumption($cid, $rows);
        }
    }

    /**
     * Distribuie manopera estimată (CNC/ATELIER) pe produse:
     * - înregistrările legate de produs rămân la produs
     * - înregistrările la nivel de proiect (fără project_product_id) se împart proporțional cu cantitatea (nr. bucăți)
     *
     * @param array<int, array<string,mixed>> $projectProducts
     * @param array<int, array<string,mixed>> $workLogs
     * @return array<int, array{qty:float,cnc_hours:float,cnc_cost:float,atelier_hours:float,atelier_cost:float,total_cost:float,cnc_rate:float,atelier_rate:float}>
     */
    private static function laborEstimateByProduct(array $projectProducts, array $workLogs): array
    {
        $w = self::productQtyWeights($projectProducts);
        /** @var array<int,int> $ppIds */
        $ppIds = $w['ppIds'];
        /** @var array<int,float> $ppQty */
        $ppQty = $w['qtyById'];
        $n = count($ppIds);
        $sumQty = (float)$w['sumQty'];
        $validPp = array_fill_keys($ppIds, true);

        // sum direct + project-level
        $direct = [];
        $projCncHours = 0.0;
        $projCncCost = 0.0;
        $projAtHours = 0.0;
        $projAtCost = 0.0;

        foreach ($workLogs as $w) {
            $he = isset($w['hours_estimated']) && $w['hours_estimated'] !== null && $w['hours_estimated'] !== '' ? (float)$w['hours_estimated'] : 0.0;
            if ($he <= 0) continue;
            $cph = isset($w['cost_per_hour']) && $w['cost_per_hour'] !== null && $w['cost_per_hour'] !== '' ? (float)$w['cost_per_hour'] : null;
            if ($cph === null || $cph < 0 || !is_finite($cph)) continue;
            $cost = $he * $cph;

            $type = (string)($w['work_type'] ?? '');
            $ppId = isset($w['project_product_id']) && $w['project_product_id'] !== null && $w['project_product_id'] !== ''
                ? (int)$w['project_product_id']
                : 0;

            // dacă worklog-ul e legat de un produs care nu mai există în proiect, îl tratăm ca "la nivel de proiect"
            if ($ppId > 0 && isset($validPp[$ppId])) {
                if (!isset($direct[$ppId])) {
                    $direct[$ppId] = ['cnc_hours' => 0.0, 'cnc_cost' => 0.0, 'atelier_hours' => 0.0, 'atelier_cost' => 0.0];
                }
                if ($type === 'CNC') {
                    $direct[$ppId]['cnc_hours'] += $he;
                    $direct[$ppId]['cnc_cost'] += $cost;
                } elseif ($type === 'ATELIER') {
                    $direct[$ppId]['atelier_hours'] += $he;
                    $direct[$ppId]['atelier_cost'] += $cost;
                }
            } else {
                if ($type === 'CNC') {
                    $projCncHours += $he;
                    $projCncCost += $cost;
                } elseif ($type === 'ATELIER') {
                    $projAtHours += $he;
                    $projAtCost += $cost;
                }
            }
        }

        $out = [];
        foreach ($ppIds as $ppId) {
            $qty = (float)($ppQty[$ppId] ?? 0.0);
            $weight = (float)($w['weightById'][$ppId] ?? 0.0);

            $shareCncHours = $projCncHours * $weight;
            $shareCncCost = $projCncCost * $weight;
            $shareAtHours = $projAtHours * $weight;
            $shareAtCost = $projAtCost * $weight;

            $cncH = ($direct[$ppId]['cnc_hours'] ?? 0.0) + $shareCncHours;
            $cncC = ($direct[$ppId]['cnc_cost'] ?? 0.0) + $shareCncCost;
            $atH = ($direct[$ppId]['atelier_hours'] ?? 0.0) + $shareAtHours;
            $atC = ($direct[$ppId]['atelier_cost'] ?? 0.0) + $shareAtCost;
            $cncRate = $cncH > 0 ? ($cncC / $cncH) : 0.0;
            $atRate = $atH > 0 ? ($atC / $atH) : 0.0;
            $out[$ppId] = [
                'qty' => $qty,
                'cnc_hours' => $cncH,
                'cnc_cost' => $cncC,
                'atelier_hours' => $atH,
                'atelier_cost' => $atC,
                'total_cost' => $cncC + $atC,
                'cnc_rate' => $cncRate,
                'atelier_rate' => $atRate,
            ];
        }
        return $out;
    }

    /**
     * @param array<int, array<string,mixed>> $projectProducts
     * @param array<int, array<string,mixed>> $magConsum
     * @return array<int, array{mag_cost:float}>
     */
    private static function magazieCostByProduct(array $projectProducts, array $magConsum): array
    {
        $w = self::productQtyWeights($projectProducts);
        /** @var array<int,int> $ppIds */
        $ppIds = $w['ppIds'];
        $out = [];
        foreach ($ppIds as $ppId) $out[$ppId] = ['mag_cost' => 0.0];

        foreach ($magConsum as $c) {
            $qty = isset($c['qty']) ? (float)$c['qty'] : 0.0;
            if ($qty <= 0) continue;
            $unitPrice = isset($c['item_unit_price']) && $c['item_unit_price'] !== null && $c['item_unit_price'] !== '' && is_numeric($c['item_unit_price'])
                ? (float)$c['item_unit_price']
                : 0.0;
            $cost = $qty * $unitPrice;
            if ($cost <= 0) continue;

            $ppId = isset($c['project_product_id']) && $c['project_product_id'] !== null && $c['project_product_id'] !== ''
                ? (int)$c['project_product_id']
                : 0;
            if ($ppId > 0 && isset($out[$ppId])) {
                $out[$ppId]['mag_cost'] += $cost;
            } else {
                // nivel proiect -> distribuim pe bucăți
                foreach ($ppIds as $pid) {
                    $weight = (float)($w['weightById'][$pid] ?? 0.0);
                    if ($weight <= 0) continue;
                    $out[$pid]['mag_cost'] += ($cost * $weight);
                }
            }
        }
        return $out;
    }

    /**
     * @param array<int, array<string,mixed>> $projectProducts
     * @param array<int, array<string,mixed>> $hplAlloc
     * @param array<int, array<string,mixed>> $hplConsum
     * @return array<int, array{hpl_cost:float}>
     */
    private static function hplCostByProduct(array $projectProducts, array $hplAlloc, array $hplConsum): array
    {
        $w = self::productQtyWeights($projectProducts);
        /** @var array<int,int> $ppIds */
        $ppIds = $w['ppIds'];
        $out = [];
        foreach ($ppIds as $ppId) $out[$ppId] = ['hpl_cost' => 0.0];

        $pricePm2 = function (array $row): float {
            if (isset($row['board_sale_price_per_m2']) && $row['board_sale_price_per_m2'] !== null && $row['board_sale_price_per_m2'] !== '' && is_numeric($row['board_sale_price_per_m2'])) {
                return (float)$row['board_sale_price_per_m2'];
            }
            $sale = (isset($row['board_sale_price']) && $row['board_sale_price'] !== null && $row['board_sale_price'] !== '' && is_numeric($row['board_sale_price']))
                ? (float)$row['board_sale_price']
                : null;
            $wmm = (int)($row['board_std_width_mm'] ?? 0);
            $hmm = (int)($row['board_std_height_mm'] ?? 0);
            $area = ($wmm > 0 && $hmm > 0) ? (($wmm * $hmm) / 1000000.0) : 0.0;
            if ($sale !== null && $sale >= 0 && $area > 0) return $sale / $area;
            return 0.0;
        };

        if ($hplAlloc) {
            foreach ($hplAlloc as $a) {
                $ppId = (int)($a['project_product_id'] ?? 0);
                if ($ppId <= 0 || !isset($out[$ppId])) continue;
                $m2 = isset($a['qty_m2']) ? (float)$a['qty_m2'] : 0.0;
                if ($m2 <= 0) continue;
                $c = $m2 * $pricePm2($a);
                if ($c > 0) $out[$ppId]['hpl_cost'] += $c;
            }
            return $out;
        }

        // Fallback: fără alocări -> distribuim consumul pe bucăți
        foreach ($hplConsum as $c) {
            $m2 = isset($c['qty_m2']) ? (float)$c['qty_m2'] : 0.0;
            if ($m2 <= 0) continue;
            $cost = $m2 * $pricePm2($c);
            if ($cost <= 0) continue;
            foreach ($ppIds as $pid) {
                $weight = (float)($w['weightById'][$pid] ?? 0.0);
                if ($weight <= 0) continue;
                $out[$pid]['hpl_cost'] += ($cost * $weight);
            }
        }
        return $out;
    }

    /**
     * @param array<int, array<string,mixed>> $workLogs
     * @param array<int, array<string,mixed>> $magConsum
     * @param array<int, array<string,mixed>> $hplConsum
     * @param array<int, array<string,mixed>> $hplAlloc
     * @return array{labor_cost:float,mag_cost:float,hpl_cost:float,total_cost:float,hpl_reserved_m2:float,hpl_reserved_cost:float,hpl_consumed_m2:float,hpl_consumed_cost:float}
     */
    private static function projectCostSummary(array $workLogs, array $magConsum, array $hplConsum, array $hplAlloc): array
    {
        $labor = 0.0;
        foreach ($workLogs as $w) {
            $he = isset($w['hours_estimated']) && $w['hours_estimated'] !== null && $w['hours_estimated'] !== '' ? (float)$w['hours_estimated'] : 0.0;
            if ($he <= 0) continue;
            $cph = isset($w['cost_per_hour']) && $w['cost_per_hour'] !== null && $w['cost_per_hour'] !== '' ? (float)$w['cost_per_hour'] : null;
            if ($cph === null || $cph < 0 || !is_finite($cph)) continue;
            $labor += ($he * $cph);
        }

        $mag = 0.0;
        foreach ($magConsum as $c) {
            $qty = isset($c['qty']) ? (float)$c['qty'] : 0.0;
            if ($qty <= 0) continue;
            $unitPrice = isset($c['item_unit_price']) && $c['item_unit_price'] !== null && $c['item_unit_price'] !== '' && is_numeric($c['item_unit_price'])
                ? (float)$c['item_unit_price']
                : 0.0;
            if ($unitPrice <= 0) continue;
            $mag += ($qty * $unitPrice);
        }

        $pricePm2 = function (array $row): float {
            if (isset($row['board_sale_price_per_m2']) && $row['board_sale_price_per_m2'] !== null && $row['board_sale_price_per_m2'] !== '' && is_numeric($row['board_sale_price_per_m2'])) {
                return (float)$row['board_sale_price_per_m2'];
            }
            $sale = (isset($row['board_sale_price']) && $row['board_sale_price'] !== null && $row['board_sale_price'] !== '' && is_numeric($row['board_sale_price']))
                ? (float)$row['board_sale_price']
                : null;
            $wmm = (int)($row['board_std_width_mm'] ?? 0);
            $hmm = (int)($row['board_std_height_mm'] ?? 0);
            $area = ($wmm > 0 && $hmm > 0) ? (($wmm * $hmm) / 1000000.0) : 0.0;
            if ($sale !== null && $sale >= 0 && $area > 0) return $sale / $area;
            return 0.0;
        };

        $hpl = 0.0;
        if ($hplAlloc) {
            foreach ($hplAlloc as $a) {
                $m2 = isset($a['qty_m2']) ? (float)$a['qty_m2'] : 0.0;
                if ($m2 <= 0) continue;
                $ppm = $pricePm2($a);
                if ($ppm <= 0) continue;
                $hpl += ($m2 * $ppm);
            }
        } else {
            foreach ($hplConsum as $c) {
                $m2 = isset($c['qty_m2']) ? (float)$c['qty_m2'] : 0.0;
                if ($m2 <= 0) continue;
                $ppm = $pricePm2($c);
                if ($ppm <= 0) continue;
                $hpl += ($m2 * $ppm);
            }
        }

        $hplResM2 = 0.0;
        $hplResCost = 0.0;
        $hplConM2 = 0.0;
        $hplConCost = 0.0;
        foreach ($hplConsum as $c) {
            $m2 = isset($c['qty_m2']) ? (float)$c['qty_m2'] : 0.0;
            if ($m2 <= 0) continue;
            $ppm = $pricePm2($c);
            $cost = ($ppm > 0) ? ($m2 * $ppm) : 0.0;
            $mode = (string)($c['mode'] ?? '');
            if ($mode === 'RESERVED') {
                $hplResM2 += $m2;
                $hplResCost += $cost;
            } elseif ($mode === 'CONSUMED') {
                $hplConM2 += $m2;
                $hplConCost += $cost;
            }
        }

        $total = $labor + $mag + $hpl;
        return [
            'labor_cost' => $labor,
            'mag_cost' => $mag,
            'hpl_cost' => $hpl,
            'total_cost' => $total,
            'hpl_reserved_m2' => $hplResM2,
            'hpl_reserved_cost' => $hplResCost,
            'hpl_consumed_m2' => $hplConM2,
            'hpl_consumed_cost' => $hplConCost,
        ];
    }

    private static function countFullBoardsAvailable(int $boardId): int
    {
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        try {
            $st = $pdo->prepare("
                SELECT COALESCE(SUM(qty),0) AS c
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = 'AVAILABLE'
                  AND (is_accounting = 1 OR is_accounting IS NULL)
            ");
            $st->execute([(int)$boardId]);
            $r = $st->fetch();
            return (int)($r['c'] ?? 0);
        } catch (\Throwable $e) {
            // Compat: vechi schema fără is_accounting
            $st = $pdo->prepare("
                SELECT COALESCE(SUM(qty),0) AS c
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = 'AVAILABLE'
            ");
            $st->execute([(int)$boardId]);
            $r = $st->fetch();
            return (int)($r['c'] ?? 0);
        }
    }

    /** @return array<int, array{value:string,label:string}> */
    private static function statuses(): array
    {
        return [
            ['value' => 'DRAFT', 'label' => 'Draft'],
            ['value' => 'CONFIRMAT', 'label' => 'Confirmat'],
            ['value' => 'IN_PRODUCTIE', 'label' => 'În producție'],
            ['value' => 'IN_ASTEPTARE', 'label' => 'În așteptare'],
            ['value' => 'FINALIZAT_TEHNIC', 'label' => 'Finalizat tehnic'],
            ['value' => 'LIVRAT_PARTIAL', 'label' => 'Livrat parțial'],
            ['value' => 'LIVRAT_COMPLET', 'label' => 'Livrat complet'],
            ['value' => 'ANULAT', 'label' => 'Anulat'],
        ];
    }

    /** @return array<int, array{value:string,label:string}> */
    private static function allocationModes(): array
    {
        return [
            ['value' => 'by_area', 'label' => 'by_area (după suprafață)'],
            ['value' => 'by_qty', 'label' => 'by_qty (după cantitate)'],
            ['value' => 'manual', 'label' => 'manual'],
        ];
    }

    /**
     * Mută plăci întregi (piece_type=FULL) între status-uri în stoc (AVAILABLE/RESERVED/CONSUMED).
     * Se face split/merge pe rânduri cu qty.
     */
    private static function moveFullBoards(int $boardId, int $qty, string $fromStatus, string $toStatus, ?string $noteAppend = null): void
    {
        $qty = (int)$qty;
        if ($qty <= 0) return;

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();

        // Compat: is_accounting poate lipsi; încercăm filtrat, altfel fără.
        $rows = [];
        try {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = ?
                  AND (is_accounting = 1 OR is_accounting IS NULL)
                ORDER BY created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, $fromStatus]);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = ?
                ORDER BY created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, $fromStatus]);
            $rows = $st->fetchAll();
        }

        $need = $qty;
        foreach ($rows as $r) {
            if ($need <= 0) break;
            $id = (int)($r['id'] ?? 0);
            $rowQty = (int)($r['qty'] ?? 0);
            if ($id <= 0 || $rowQty <= 0) continue;

            $take = min($need, $rowQty);
            if ($take <= 0) continue;

            $width = (int)($r['width_mm'] ?? 0);
            $height = (int)($r['height_mm'] ?? 0);
            $location = (string)($r['location'] ?? '');
            $isAcc = (int)($r['is_accounting'] ?? 1);
            $notes = (string)($r['notes'] ?? '');

            if ($take === $rowQty) {
                // Mută întreg rândul: încercăm să cumulăm într-un rând identic în destinație,
                // ca să evităm duplicatele (ex: la anulare rezervare/consum).
                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, 'FULL', $toStatus, $width, $height, $location, $isAcc, $id);
                } catch (\Throwable $e) {
                    $ident = null;
                }
                if ($ident) {
                    $destId = (int)($ident['id'] ?? 0);
                    if ($destId > 0) {
                        HplStockPiece::incrementQty($destId, $take);
                        if ($noteAppend) {
                            HplStockPiece::appendNote($destId, $noteAppend);
                        }
                        // sursa a fost cumulată
                        HplStockPiece::delete($id);
                    } else {
                        // fallback: dacă nu avem id valid, mutăm în loc
                        HplStockPiece::updateFields($id, ['status' => $toStatus]);
                        if ($noteAppend) HplStockPiece::appendNote($id, $noteAppend);
                    }
                } else {
                    HplStockPiece::updateFields($id, ['status' => $toStatus]);
                    if ($noteAppend) HplStockPiece::appendNote($id, $noteAppend);
                }
            } else {
                // scade din rândul sursă
                HplStockPiece::updateQty($id, $rowQty - $take);

                // adaugă/îmbină în rândul destinație
                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, 'FULL', $toStatus, $width, $height, $location, $isAcc);
                } catch (\Throwable $e) {
                    $ident = null;
                }
                if ($ident) {
                    HplStockPiece::incrementQty((int)$ident['id'], $take);
                    if ($noteAppend) HplStockPiece::appendNote((int)$ident['id'], $noteAppend);
                } else {
                    $newNotes = trim($notes);
                    if ($noteAppend) $newNotes = trim($newNotes . ($newNotes !== '' ? "\n" : '') . $noteAppend);
                    HplStockPiece::create([
                        'board_id' => $boardId,
                        'is_accounting' => $isAcc,
                        'piece_type' => 'FULL',
                        'status' => $toStatus,
                        'width_mm' => $width,
                        'height_mm' => $height,
                        'qty' => $take,
                        'location' => $location,
                        'notes' => $newNotes !== '' ? $newNotes : null,
                    ]);
                }
            }

            $need -= $take;
        }

        if ($need > 0) {
            throw new \RuntimeException('Stoc insuficient (plăci întregi).');
        }
    }

    public static function index(): void
    {
        $q = isset($_GET['q']) ? trim((string)($_GET['q'] ?? '')) : '';
        $status = isset($_GET['status']) ? trim((string)($_GET['status'] ?? '')) : '';

        try {
            $rows = Project::all($q !== '' ? $q : null, $status !== '' ? $status : null, 800);
            echo View::render('projects/index', [
                'title' => 'Proiecte',
                'rows' => $rows,
                'q' => $q,
                'status' => $status,
                'statuses' => self::statuses(),
            ]);
        } catch (\Throwable $e) {
            echo View::render('system/placeholder', [
                'title' => 'Proiecte',
                'message' => 'Modulul Proiecte nu este disponibil momentan. Rulează Update DB dacă lipsesc tabelele.',
            ]);
        }
    }

    public static function createForm(): void
    {
        echo View::render('projects/form', [
            'title' => 'Proiect nou',
            'mode' => 'create',
            'row' => [
                'status' => 'DRAFT',
                'priority' => 0,
                'allocation_mode' => 'by_area',
                'allocations_locked' => 0,
            ],
            'errors' => [],
            'statuses' => self::statuses(),
            'allocationModes' => self::allocationModes(),
            'clients' => Client::allWithProjects(), // reuse list (name/type)
            'groups' => ClientGroup::forSelect(),
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'name' => 'Nume',
        ]);
        $errors = $check['errors'];

        $clientIdRaw = trim((string)($_POST['client_id'] ?? ''));
        $groupIdRaw = trim((string)($_POST['client_group_id'] ?? ''));
        $clientId = $clientIdRaw !== '' ? (Validator::int($clientIdRaw, 1) ?? null) : null;
        $groupId = $groupIdRaw !== '' ? (Validator::int($groupIdRaw, 1) ?? null) : null;
        if ($clientIdRaw !== '' && $clientId === null) $errors['client_id'] = 'Client invalid.';
        if ($groupIdRaw !== '' && $groupId === null) $errors['client_group_id'] = 'Grup invalid.';
        if ($clientId !== null && $groupId !== null) {
            $errors['client_id'] = 'Alege fie client, fie grup.';
            $errors['client_group_id'] = 'Alege fie client, fie grup.';
        }

        $priority = Validator::int(trim((string)($_POST['priority'] ?? '0')), -100000, 100000) ?? 0;
        $status = trim((string)($_POST['status'] ?? 'DRAFT'));
        $allowedStatuses = array_map(fn($s) => (string)$s['value'], self::statuses());
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) $errors['status'] = 'Status invalid.';

        $allocationMode = trim((string)($_POST['allocation_mode'] ?? 'by_area'));
        $allowedAlloc = array_map(fn($m) => (string)$m['value'], self::allocationModes());
        if ($allocationMode !== '' && !in_array($allocationMode, $allowedAlloc, true)) $errors['allocation_mode'] = 'Mod invalid.';
        $allocLocked = isset($_POST['allocations_locked']) ? 1 : 0;

        if ($errors) {
            echo View::render('projects/form', [
                'title' => 'Proiect nou',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
                'statuses' => self::statuses(),
                'allocationModes' => self::allocationModes(),
                'clients' => Client::allWithProjects(),
                'groups' => ClientGroup::forSelect(),
            ]);
            return;
        }

        $data = [
            'code' => trim((string)$_POST['code']),
            'name' => trim((string)$_POST['name']),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'category' => trim((string)($_POST['category'] ?? '')) ?: null,
            'status' => $status ?: 'DRAFT',
            'priority' => $priority,
            'start_date' => trim((string)($_POST['start_date'] ?? '')) ?: null,
            'due_date' => trim((string)($_POST['due_date'] ?? '')) ?: null,
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
            'technical_notes' => trim((string)($_POST['technical_notes'] ?? '')) ?: null,
            'tags' => trim((string)($_POST['tags'] ?? '')) ?: null,
            'client_id' => $clientId,
            'client_group_id' => $groupId,
            'allocation_mode' => $allocationMode ?: 'by_area',
            'allocations_locked' => $allocLocked,
            'created_by' => Auth::id(),
        ];

        try {
            $id = Project::create($data);
            Audit::log('PROJECT_CREATE', 'projects', $id, null, $data, [
                'message' => 'A creat proiect: ' . $data['code'] . ' · ' . $data['name'],
            ]);
            Session::flash('toast_success', 'Proiect creat.');
            Response::redirect('/projects/' . $id);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot crea proiectul: ' . $e->getMessage());
            Response::redirect('/projects/create');
        }
    }

    public static function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $tab = isset($_GET['tab']) ? trim((string)($_GET['tab'] ?? '')) : '';
        if ($tab === '') $tab = 'general';

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $project = Project::find($id);
                if (!$project) {
                    Session::flash('toast_error', 'Proiect inexistent.');
                    Response::redirect('/projects');
                }

                $projectProducts = [];
                $magazieConsum = [];
                $hplConsum = [];
                $hplAlloc = [];
                $hplBoards = [];
                $magazieItems = [];
                $deliveries = [];
                $deliveryItems = [];
                $projectFiles = [];
                $workLogs = [];
                $projectLabels = [];
                $cncFiles = [];
                $laborByProduct = [];
                $materialsByProduct = [];
                $projectCostSummary = [];
                $discussions = [];
                if ($tab === 'products') {
                    try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
                    try { $workLogs = ProjectWorkLog::forProject($id); } catch (\Throwable $e) { $workLogs = []; }
                    $laborByProduct = self::laborEstimateByProduct($projectProducts, $workLogs);
                    try { $magazieConsum = ProjectMagazieConsumption::forProject($id); } catch (\Throwable $e) { $magazieConsum = []; }
                    try { $hplConsum = ProjectHplConsumption::forProject($id); } catch (\Throwable $e) { $hplConsum = []; }
                    try { $hplAlloc = ProjectHplAllocation::forProject($id); } catch (\Throwable $e) { $hplAlloc = []; }
                    $magBy = self::magazieCostByProduct($projectProducts, $magazieConsum);
                    $hplBy = self::hplCostByProduct($projectProducts, $hplAlloc, $hplConsum);
                    foreach ($projectProducts as $pp) {
                        $ppId = (int)($pp['id'] ?? 0);
                        if ($ppId <= 0) continue;
                        $materialsByProduct[$ppId] = [
                            'mag_cost' => (float)($magBy[$ppId]['mag_cost'] ?? 0.0),
                            'hpl_cost' => (float)($hplBy[$ppId]['hpl_cost'] ?? 0.0),
                        ];
                    }
                    $projectCostSummary = self::projectCostSummary($workLogs, $magazieConsum, $hplConsum, $hplAlloc);
                } elseif ($tab === 'consum') {
                    try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
                    try { $magazieConsum = ProjectMagazieConsumption::forProject($id); } catch (\Throwable $e) { $magazieConsum = []; }
                    try { $hplConsum = ProjectHplConsumption::forProject($id); } catch (\Throwable $e) { $hplConsum = []; }
                    try { $hplAlloc = ProjectHplAllocation::forProject($id); } catch (\Throwable $e) { $hplAlloc = []; }
                    try { $hplBoards = HplBoard::allWithTotals(null, null); } catch (\Throwable $e) { $hplBoards = []; }
                    try { $magazieItems = MagazieItem::all(null, 5000); } catch (\Throwable $e) { $magazieItems = []; }
                } elseif ($tab === 'deliveries') {
                    try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
                    try { $deliveries = ProjectDelivery::forProject($id); } catch (\Throwable $e) { $deliveries = []; }
                    $deliveryItems = [];
                    foreach ($deliveries as $d) {
                        $did = (int)($d['id'] ?? 0);
                        if ($did <= 0) continue;
                        try { $deliveryItems[$did] = ProjectDelivery::itemsForDelivery($did); } catch (\Throwable $e) { $deliveryItems[$did] = []; }
                    }
                } elseif ($tab === 'files') {
                    try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
                    try { $projectFiles = EntityFile::forEntity('projects', $id); } catch (\Throwable $e) { $projectFiles = []; }
                } elseif ($tab === 'hours') {
                    try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
                    try { $workLogs = ProjectWorkLog::forProject($id); } catch (\Throwable $e) { $workLogs = []; }
                } elseif ($tab === 'history') {
                    try { $projectFiles = EntityFile::forEntity('projects', $id); } catch (\Throwable $e) { $projectFiles = []; }
                } elseif ($tab === 'discutii') {
                    try { $discussions = EntityComment::forEntity('projects', $id, 800); } catch (\Throwable $e) { $discussions = []; }
                } elseif ($tab === 'general') {
                    try { $projectLabels = EntityLabel::labelsForEntity('projects', $id); } catch (\Throwable $e) { $projectLabels = []; }
                } elseif ($tab === 'cnc') {
                    try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
                    $cncFiles = [];
                    try { $cncFiles = array_merge($cncFiles, EntityFile::forEntity('projects', $id)); } catch (\Throwable $e) {}
                    foreach ($projectProducts as $pp) {
                        $ppId = (int)($pp['id'] ?? 0);
                        if ($ppId <= 0) continue;
                        try {
                            $files = EntityFile::forEntity('project_products', $ppId);
                            foreach ($files as $f) {
                                $f['_product_name'] = (string)($pp['product_name'] ?? '');
                                $cncFiles[] = $f;
                            }
                        } catch (\Throwable $e) {}
                    }
                }

                $history = [];
                if ($tab === 'history') {
                    try { $history = AuditLog::forProject($id, 300); } catch (\Throwable $e) { $history = []; }
                }

                echo View::render('projects/show', [
                    'title' => 'Proiect',
                    'project' => $project,
                    'tab' => $tab,
                    'projectProducts' => $projectProducts,
                    'magazieConsum' => $magazieConsum,
                    'hplConsum' => $hplConsum,
                    'hplAlloc' => $hplAlloc,
                    'hplBoards' => $hplBoards,
                    'magazieItems' => $magazieItems,
                    'deliveries' => $deliveries,
                    'deliveryItems' => $deliveryItems,
                    'projectFiles' => $projectFiles,
                    'workLogs' => $workLogs,
                    'laborByProduct' => $laborByProduct,
                    'materialsByProduct' => $materialsByProduct,
                    'projectCostSummary' => $projectCostSummary,
                    'discussions' => $discussions,
                    'costSettings' => [
                        'labor' => (function () { try { return AppSetting::getFloat(AppSetting::KEY_COST_LABOR_PER_HOUR); } catch (\Throwable $e) { return null; } })(),
                        'cnc' => (function () { try { return AppSetting::getFloat(AppSetting::KEY_COST_CNC_PER_HOUR); } catch (\Throwable $e) { return null; } })(),
                    ],
                    'history' => $history,
                    'projectLabels' => $projectLabels,
                    'cncFiles' => $cncFiles,
                    'statuses' => self::statuses(),
                    'allocationModes' => self::allocationModes(),
                    'clients' => Client::allWithProjects(),
                    'groups' => ClientGroup::forSelect(),
                ]);
                return;
            } catch (\Throwable $e) {
                if ($attempt === 0) {
                    try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
                    continue;
                }
                $u = Auth::user();
                $env = strtolower((string)Env::get('APP_ENV', 'prod'));
                $debug = Env::bool('APP_DEBUG', false) || ($u && strtolower((string)($u['email'] ?? '')) === 'sacodrut@ikonia.ro') || ($env !== 'prod' && $env !== 'production');
                $msg = 'Proiect indisponibil momentan.';
                if ($debug) {
                    $msg .= ' Eroare: ' . get_class($e) . ' · ' . $e->getMessage() . ' · ' . basename((string)$e->getFile()) . ':' . (int)$e->getLine();
                }
                echo View::render('system/placeholder', ['title' => 'Proiect', 'message' => $msg]);
                return;
            }
        }
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Project::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'name' => 'Nume',
        ]);
        $errors = $check['errors'];

        $clientIdRaw = trim((string)($_POST['client_id'] ?? ''));
        $groupIdRaw = trim((string)($_POST['client_group_id'] ?? ''));
        $clientId = $clientIdRaw !== '' ? (Validator::int($clientIdRaw, 1) ?? null) : null;
        $groupId = $groupIdRaw !== '' ? (Validator::int($groupIdRaw, 1) ?? null) : null;
        if ($clientIdRaw !== '' && $clientId === null) $errors['client_id'] = 'Client invalid.';
        if ($groupIdRaw !== '' && $groupId === null) $errors['client_group_id'] = 'Grup invalid.';
        if ($clientId !== null && $groupId !== null) {
            $errors['client_id'] = 'Alege fie client, fie grup.';
            $errors['client_group_id'] = 'Alege fie client, fie grup.';
        }

        $priority = Validator::int(trim((string)($_POST['priority'] ?? '0')), -100000, 100000) ?? 0;
        $status = trim((string)($_POST['status'] ?? (string)($before['status'] ?? 'DRAFT')));
        $allowedStatuses = array_map(fn($s) => (string)$s['value'], self::statuses());
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) $errors['status'] = 'Status invalid.';

        $allocationMode = trim((string)($_POST['allocation_mode'] ?? (string)($before['allocation_mode'] ?? 'by_area')));
        $allowedAlloc = array_map(fn($m) => (string)$m['value'], self::allocationModes());
        if ($allocationMode !== '' && !in_array($allocationMode, $allowedAlloc, true)) $errors['allocation_mode'] = 'Mod invalid.';
        $allocLocked = isset($_POST['allocations_locked']) ? 1 : 0;

        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile proiectului.');
            Response::redirect('/projects/' . $id . '?tab=general');
        }

        $after = [
            'code' => trim((string)$_POST['code']),
            'name' => trim((string)$_POST['name']),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'category' => trim((string)($_POST['category'] ?? '')) ?: null,
            'status' => $status ?: 'DRAFT',
            'priority' => $priority,
            'start_date' => trim((string)($_POST['start_date'] ?? '')) ?: null,
            'due_date' => trim((string)($_POST['due_date'] ?? '')) ?: null,
            'completed_at' => $before['completed_at'] ?? null,
            'cancelled_at' => $before['cancelled_at'] ?? null,
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
            'technical_notes' => trim((string)($_POST['technical_notes'] ?? '')) ?: null,
            'tags' => trim((string)($_POST['tags'] ?? '')) ?: null,
            'client_id' => $clientId,
            'client_group_id' => $groupId,
            'allocation_mode' => $allocationMode ?: 'by_area',
            'allocations_locked' => $allocLocked,
        ];

        try {
            Project::update($id, $after);
            Audit::log('PROJECT_UPDATE', 'projects', $id, $before, $after, [
                'message' => 'A actualizat proiect: ' . $after['code'] . ' · ' . $after['name'],
            ]);
            Session::flash('toast_success', 'Proiect actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot actualiza proiectul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $id . '?tab=general');
    }

    public static function changeStatus(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Project::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $newStatus = trim((string)($_POST['status'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $allowedStatuses = array_map(fn($s) => (string)$s['value'], self::statuses());
        if ($newStatus === '' || !in_array($newStatus, $allowedStatuses, true)) {
            Session::flash('toast_error', 'Status invalid.');
            Response::redirect('/projects/' . $id . '?tab=general');
        }

        $oldStatus = (string)($before['status'] ?? '');
        if ($oldStatus === $newStatus) {
            Session::flash('toast_error', 'Statusul este deja setat.');
            Response::redirect('/projects/' . $id . '?tab=general');
        }

        $after = $before;
        $after['status'] = $newStatus;
        // timestamp-uri best-effort
        if ($newStatus === 'LIVRAT_COMPLET') {
            $after['completed_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'ANULAT') {
            $after['cancelled_at'] = date('Y-m-d H:i:s');
        }

        try {
            Project::update($id, $after);
            Audit::log('PROJECT_STATUS_CHANGE', 'projects', $id, $before, $after, [
                'message' => 'Schimbare status proiect: ' . $oldStatus . ' → ' . $newStatus,
                'note' => $note !== '' ? $note : null,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
            Session::flash('toast_success', 'Status actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot schimba statusul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $id . '?tab=general');
    }

    public static function canWrite(): bool
    {
        $u = Auth::user();
        return $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
    }

    public static function addExistingProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $productId = Validator::int(trim((string)($_POST['product_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $unit = trim((string)($_POST['unit'] ?? 'buc'));
        $m2 = Validator::dec(trim((string)($_POST['m2_per_unit'] ?? ''))) ?? null;
        if ($productId === null) {
            Session::flash('toast_error', 'Produs invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }
        if ($qty <= 0) $qty = 1.0;
        if ($m2 !== null && $m2 < 0) $m2 = null;

        $prod = Product::find((int)$productId);
        if (!$prod) {
            Session::flash('toast_error', 'Produs inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            $ppId = ProjectProduct::addToProject([
                'project_id' => $projectId,
                'product_id' => (int)$productId,
                'qty' => $qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'm2_per_unit' => $m2 !== null ? (float)$m2 : 0.0,
                'production_status' => 'DE_PREGATIT',
                'delivered_qty' => 0,
                'notes' => null,
            ]);
            // Propagă etichetele proiectului către produs (INHERITED)
            try {
                $labelIds = EntityLabel::labelIdsForEntity('projects', $projectId, 'DIRECT');
                EntityLabel::propagateToProjectProducts([$ppId], $labelIds, Auth::id());
            } catch (\Throwable $e) {}
            Audit::log('PROJECT_PRODUCT_ATTACH', 'project_products', $ppId, null, null, [
                'message' => 'A atașat produs la proiect: ' . (string)($prod['name'] ?? ''),
                'project_id' => $projectId,
                'product_id' => (int)$productId,
                'qty' => $qty,
                'unit' => $unit,
                'm2_per_unit' => $m2 !== null ? (float)$m2 : null,
            ]);
            try { self::recalcHplAllocationsForProject($projectId); } catch (\Throwable $e) {}
            Session::flash('toast_success', 'Produs adăugat în proiect.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot adăuga produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function createProductInProject(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $check = Validator::required($_POST, [
            'name' => 'Denumire',
            'qty' => 'Cantitate',
        ]);
        $errors = $check['errors'];
        $name = trim((string)($_POST['name'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $unit = trim((string)($_POST['unit'] ?? 'buc'));
        $m2 = Validator::dec(trim((string)($_POST['m2_per_unit'] ?? ''))) ?? null;
        if ($m2 !== null && $m2 < 0) $m2 = null;

        if ($qty <= 0) $errors['qty'] = 'Cantitate invalidă.';
        if ($errors) {
            Session::flash('toast_error', 'Completează corect produsul.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            $pid = Product::create([
                'code' => $code !== '' ? $code : null,
                'name' => $name,
                'width_mm' => null,
                'height_mm' => null,
                'notes' => null,
                'cnc_settings_json' => null,
            ]);
            Audit::log('PRODUCT_CREATE', 'products', $pid, null, null, [
                'message' => 'A creat produs: ' . $name,
                'project_id' => $projectId,
            ]);

            $ppId = ProjectProduct::addToProject([
                'project_id' => $projectId,
                'product_id' => $pid,
                'qty' => $qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'm2_per_unit' => $m2 !== null ? (float)$m2 : 0.0,
                'production_status' => 'DE_PREGATIT',
                'delivered_qty' => 0,
                'notes' => null,
            ]);
            try {
                $labelIds = EntityLabel::labelIdsForEntity('projects', $projectId, 'DIRECT');
                EntityLabel::propagateToProjectProducts([$ppId], $labelIds, Auth::id());
            } catch (\Throwable $e) {}
            Audit::log('PROJECT_PRODUCT_ATTACH', 'project_products', $ppId, null, null, [
                'message' => 'A atașat produs nou la proiect: ' . $name,
                'project_id' => $projectId,
                'product_id' => $pid,
                'qty' => $qty,
                'unit' => $unit,
                'm2_per_unit' => $m2 !== null ? (float)$m2 : null,
            ]);
            try { self::recalcHplAllocationsForProject($projectId); } catch (\Throwable $e) {}

            Session::flash('toast_success', 'Produs creat și adăugat în proiect.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot crea produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function updateProjectProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);

        $before = ProjectProduct::find($ppId);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Produs proiect invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $del = Validator::dec(trim((string)($_POST['delivered_qty'] ?? '0'))) ?? 0.0;
        $unit = trim((string)($_POST['unit'] ?? (string)($before['unit'] ?? 'buc')));
        $m2 = Validator::dec(trim((string)($_POST['m2_per_unit'] ?? ''))) ?? null;
        $st = trim((string)($_POST['production_status'] ?? (string)($before['production_status'] ?? 'DE_PREGATIT')));
        $note = trim((string)($_POST['notes'] ?? ''));

        $allowed = ['DE_PREGATIT','CNC','ATELIER','FINISARE','GATA','LIVRAT_PARTIAL','LIVRAT_COMPLET','REBUT'];
        if (!in_array($st, $allowed, true)) $st = (string)($before['production_status'] ?? 'DE_PREGATIT');
        if ($qty <= 0) $qty = 1.0;
        if ($del < 0) $del = 0.0;
        if ($del > $qty) $del = $qty;
        if ($m2 !== null && $m2 < 0) $m2 = null;

        $after = [
            'qty' => $qty,
            'unit' => $unit !== '' ? $unit : 'buc',
            'm2_per_unit' => $m2 !== null ? (float)$m2 : (float)($before['m2_per_unit'] ?? 0),
            'production_status' => $st,
            'delivered_qty' => $del,
            'notes' => $note !== '' ? $note : null,
        ];

        try {
            ProjectProduct::updateFields($ppId, $after);
            Audit::log('PROJECT_PRODUCT_UPDATE', 'project_products', $ppId, $before, $after, [
                'message' => 'A actualizat produs în proiect.',
                'project_id' => $projectId,
            ]);
            try { self::recalcHplAllocationsForProject($projectId); } catch (\Throwable $e) {}
            Session::flash('toast_success', 'Produs actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot actualiza produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function unlinkProjectProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);
        $before = ProjectProduct::find($ppId);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Produs proiect invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            ProjectProduct::delete($ppId);
            Audit::log('PROJECT_PRODUCT_DETACH', 'project_products', $ppId, $before, null, [
                'message' => 'A dezlegat produs din proiect.',
                'project_id' => $projectId,
                'product_id' => (int)($before['product_id'] ?? 0),
            ]);
            try { self::recalcHplAllocationsForProject($projectId); } catch (\Throwable $e) {}
            Session::flash('toast_success', 'Produs scos din proiect.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot scoate produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function addMagazieConsumption(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $itemId = Validator::int(trim((string)($_POST['item_id'] ?? '')), 1);
        $ppId = Validator::int(trim((string)($_POST['project_product_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        $mode = trim((string)($_POST['mode'] ?? 'CONSUMED'));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($itemId === null || $qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Consum invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        if (!in_array($mode, ['RESERVED','CONSUMED'], true)) $mode = 'CONSUMED';

        $item = MagazieItem::find((int)$itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        $unit = trim((string)($item['unit'] ?? 'buc'));

        // dacă e consumat, verificăm stoc
        if ($mode === 'CONSUMED') {
            $stock = (float)($item['stock_qty'] ?? 0);
            if ($qty > $stock + 1e-9) {
                Session::flash('toast_error', 'Stoc insuficient în Magazie.');
                Response::redirect('/projects/' . $projectId . '?tab=consum');
            }
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $cid = ProjectMagazieConsumption::create([
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'item_id' => (int)$itemId,
                'qty' => (float)$qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            if ($mode === 'CONSUMED') {
                if (!MagazieItem::adjustStock((int)$itemId, -(float)$qty)) {
                    throw new \RuntimeException('Nu pot scădea stocul.');
                }
                // mișcare Magazie
                \App\Models\MagazieMovement::create([
                    'item_id' => (int)$itemId,
                    'direction' => 'OUT',
                    'qty' => (float)$qty,
                    'unit_price' => (isset($item['unit_price']) && $item['unit_price'] !== '' && is_numeric($item['unit_price'])) ? (float)$item['unit_price'] : null,
                    'project_id' => $projectId,
                    'project_code' => (string)($project['code'] ?? null),
                    'note' => $note !== '' ? $note : 'Consum proiect',
                    'created_by' => Auth::id(),
                ]);
            }

            $pdo->commit();

            Audit::log('PROJECT_CONSUMPTION_CREATE', 'project_magazie_consumptions', $cid, null, null, [
                'message' => 'Consum Magazie ' . $mode . ': ' . (string)($item['winmentor_code'] ?? '') . ' · ' . (string)($item['name'] ?? ''),
                'project_id' => $projectId,
                'item_id' => (int)$itemId,
                'qty' => (float)$qty,
                'unit' => $unit,
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
            ]);
            Session::flash('toast_success', 'Consum Magazie salvat.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva consumul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=consum');
    }

    public static function addHplConsumption(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $boardId = Validator::int(trim((string)($_POST['board_id'] ?? '')), 1);
        $qtyBoards = Validator::int(trim((string)($_POST['qty_boards'] ?? '')), 1);
        $mode = trim((string)($_POST['mode'] ?? 'RESERVED'));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($boardId === null || $qtyBoards === null || $qtyBoards <= 0) {
            Session::flash('toast_error', 'Consum HPL invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        if (!in_array($mode, ['RESERVED','CONSUMED'], true)) $mode = 'RESERVED';

        try {
            $board = HplBoard::find((int)$boardId);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot încărca placa HPL.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        if (!$board) {
            Session::flash('toast_error', 'Placă HPL inexistentă.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }

        // Stoc disponibil (plăci întregi)
        $stockFull = 0;
        try {
            $stockFull = self::countFullBoardsAvailable((int)$boardId);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot calcula stocul HPL.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        if ($qtyBoards > $stockFull) {
            Session::flash('toast_error', 'Stoc HPL insuficient (plăci întregi).');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $wStd = (int)($board['std_width_mm'] ?? 0);
            $hStd = (int)($board['std_height_mm'] ?? 0);
            $areaPer = ($wStd > 0 && $hStd > 0) ? (($wStd * $hStd) / 1000000.0) : 0.0;
            $qtyM2 = $areaPer > 0 ? ($areaPer * (float)$qtyBoards) : 0.0;

            $cid = ProjectHplConsumption::create([
                'project_id' => $projectId,
                'board_id' => (int)$boardId,
                'qty_boards' => (int)$qtyBoards,
                'qty_m2' => (float)$qtyM2,
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            // Actualizează stocul pe plăci întregi (AVAILABLE -> RESERVED/CONSUMED)
            $target = $mode === 'CONSUMED' ? 'CONSUMED' : 'RESERVED';
            $projCode = (string)($project['code'] ?? '');
            $projName = (string)($project['name'] ?? '');
            // IMPORTANT (cerință): nota afișată pe piesa din stoc trebuie să coincidă cu nota din proiect.
            // Păstrăm mesajul tehnic (proiect/consumption id) în Audit, nu în notes.
            $noteAppend = ($note !== '') ? $note : ('Proiect ' . $projCode . ' · consum HPL #' . $cid);
            self::moveFullBoards((int)$boardId, (int)$qtyBoards, 'AVAILABLE', $target, $noteAppend);

            // Auto-alocare pe produse (dacă nu e blocată distribuția și există produse)
            $allocLocked = (int)($project['allocations_locked'] ?? 0) === 1;
            if (!$allocLocked) {
                $pps = ProjectProduct::forProject($projectId);
                $weights = [];
                $sum = 0.0;
                $allocMode = (string)($project['allocation_mode'] ?? 'by_area');
                $byArea = ($allocMode === 'by_area');
                foreach ($pps as $pp) {
                    $ppid = (int)($pp['id'] ?? 0);
                    if ($ppid <= 0) continue;
                    $qq = (float)($pp['qty'] ?? 0);
                    $weight = 0.0;
                    $m2u = isset($pp['m2_per_unit']) ? (float)($pp['m2_per_unit'] ?? 0) : 0.0;
                    if ($byArea && $m2u > 0 && $qq > 0) {
                        $weight = ($m2u * $qq);
                    } elseif ($qq > 0) {
                        $weight = $qq;
                    }
                    if ($weight <= 0) continue;
                    $weights[$ppid] = $weight;
                    $sum += $weight;
                }
                if ($sum > 0.0) {
                    $alloc = [];
                    foreach ($weights as $ppid => $wgt) {
                        $alloc[] = [
                            'project_product_id' => (int)$ppid,
                            'qty_m2' => (float)$qtyM2 * ($wgt / $sum),
                        ];
                    }
                    ProjectHplAllocation::replaceForConsumption($cid, $alloc);
                }
            }

            $pdo->commit();

            Audit::log('PROJECT_CONSUMPTION_CREATE', 'project_hpl_consumptions', $cid, null, null, [
                'message' => 'Proiect ' . (string)($project['code'] ?? '') . ' · ' . (string)($project['name'] ?? '') . ' — HPL ' . $mode . ': ' . (string)($board['code'] ?? '') . ' · ' . (string)($board['name'] ?? '') . ' · ' . (int)$qtyBoards . ' buc',
                'project_id' => $projectId,
                'board_id' => (int)$boardId,
                'qty_boards' => (int)$qtyBoards,
                'qty_m2' => (float)$qtyM2,
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
            ]);

            // Log explicit pe placă (pentru Istoric placă + Jurnal activitate), cu link-uri către proiect și stoc.
            Audit::log('HPL_STOCK_' . ($target === 'RESERVED' ? 'RESERVE' : 'CONSUME'), 'hpl_boards', (int)$boardId, null, null, [
                'message' => ($target === 'RESERVED' ? 'Rezervat' : 'Consumat') . ' ' . (int)$qtyBoards . ' buc (FULL) pentru Proiect: ' . (string)($project['code'] ?? '') . ' · ' . (string)($project['name'] ?? ''),
                'board_id' => (int)$boardId,
                'board_code' => (string)($board['code'] ?? ''),
                'board_name' => (string)($board['name'] ?? ''),
                'project_id' => $projectId,
                'project_code' => (string)($project['code'] ?? ''),
                'project_name' => (string)($project['name'] ?? ''),
                'consumption_id' => $cid,
                'mode' => $mode,
                'qty_boards' => (int)$qtyBoards,
                'to_status' => $target,
                'url_board' => \App\Core\Url::to('/stock/boards/' . (int)$boardId),
                'url_project' => \App\Core\Url::to('/projects/' . $projectId),
                'url_project_consum' => \App\Core\Url::to('/projects/' . $projectId . '?tab=consum'),
            ]);
            Session::flash('toast_success', 'Consum HPL salvat.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva consumul HPL: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=consum');
    }

    public static function updateMagazieConsumption(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $cid = (int)($params['cid'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }
        $before = ProjectMagazieConsumption::find($cid);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Consum inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }

        $ppId = Validator::int(trim((string)($_POST['project_product_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        $unit = trim((string)($_POST['unit'] ?? (string)($before['unit'] ?? 'buc')));
        $mode = trim((string)($_POST['mode'] ?? (string)($before['mode'] ?? 'CONSUMED')));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Cantitate invalidă.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        if (!in_array($mode, ['RESERVED','CONSUMED'], true)) $mode = (string)($before['mode'] ?? 'CONSUMED');

        $itemId = (int)($before['item_id'] ?? 0);
        $item = MagazieItem::find($itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }

        $beforeMode = (string)($before['mode'] ?? '');
        $beforeQty = (float)($before['qty'] ?? 0);
        $afterQty = (float)$qty;
        $afterMode = $mode;

        $stockDelta = 0.0;
        if ($beforeMode === 'CONSUMED') $stockDelta += $beforeQty;
        if ($afterMode === 'CONSUMED') $stockDelta -= $afterQty;

        $stock = (float)($item['stock_qty'] ?? 0);
        if ($stockDelta < 0 && (($stock + $stockDelta) < -1e-9)) {
            Session::flash('toast_error', 'Stoc insuficient pentru actualizare.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            ProjectMagazieConsumption::update($cid, [
                'project_product_id' => $ppId,
                'qty' => $afterQty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'mode' => $afterMode,
                'note' => $note !== '' ? $note : null,
            ]);
            if (abs($stockDelta) > 0.0000001) {
                MagazieItem::adjustStock($itemId, $stockDelta);
                \App\Models\MagazieMovement::create([
                    'item_id' => $itemId,
                    'direction' => 'ADJUST',
                    'qty' => $stockDelta,
                    'unit_price' => (isset($item['unit_price']) && $item['unit_price'] !== '' && is_numeric($item['unit_price'])) ? (float)$item['unit_price'] : null,
                    'project_id' => $projectId,
                    'project_code' => (string)($project['code'] ?? null),
                    'note' => 'Corecție consum proiect #' . $cid,
                    'created_by' => Auth::id(),
                ]);
            }
            $pdo->commit();
            Audit::log('PROJECT_CONSUMPTION_UPDATE', 'project_magazie_consumptions', $cid, $before, null, [
                'message' => 'Actualizare consum Magazie.',
                'project_id' => $projectId,
                'item_id' => $itemId,
                'stock_delta' => $stockDelta,
            ]);
            Session::flash('toast_success', 'Consum actualizat.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot actualiza consumul.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=consum');
    }

    public static function deleteMagazieConsumption(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $cid = (int)($params['cid'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }
        $before = ProjectMagazieConsumption::find($cid);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Consum inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        $itemId = (int)($before['item_id'] ?? 0);
        $item = MagazieItem::find($itemId);
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $stockDelta = 0.0;
            if ((string)($before['mode'] ?? '') === 'CONSUMED') {
                $stockDelta = (float)($before['qty'] ?? 0);
                if ($stockDelta > 0) {
                    MagazieItem::adjustStock($itemId, $stockDelta);
                    if ($item) {
                        \App\Models\MagazieMovement::create([
                            'item_id' => $itemId,
                            'direction' => 'ADJUST',
                            'qty' => $stockDelta,
                            'unit_price' => (isset($item['unit_price']) && $item['unit_price'] !== '' && is_numeric($item['unit_price'])) ? (float)$item['unit_price'] : null,
                            'project_id' => $projectId,
                            'project_code' => (string)($project['code'] ?? null),
                            'note' => 'Ștergere consum proiect #' . $cid,
                            'created_by' => Auth::id(),
                        ]);
                    }
                }
            }
            ProjectMagazieConsumption::delete($cid);
            $pdo->commit();
            Audit::log('PROJECT_CONSUMPTION_DELETE', 'project_magazie_consumptions', $cid, $before, null, [
                'message' => 'Ștergere consum Magazie.',
                'project_id' => $projectId,
                'item_id' => $itemId,
                'stock_delta' => $stockDelta,
            ]);
            Session::flash('toast_success', 'Consum șters.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot șterge consumul.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=consum');
    }

    public static function deleteHplConsumption(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $cid = (int)($params['cid'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }
        $before = ProjectHplConsumption::find($cid);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Consum inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        try {
            // Reverse stoc (best-effort): RESERVED/CONSUMED -> AVAILABLE
            $mode = (string)($before['mode'] ?? 'RESERVED');
            $from = $mode === 'CONSUMED' ? 'CONSUMED' : 'RESERVED';
            $qtyBoards = (int)($before['qty_boards'] ?? 0);
            if ($qtyBoards > 0) {
                try {
                    self::moveFullBoards((int)($before['board_id'] ?? 0), $qtyBoards, $from, 'AVAILABLE', 'Anulare consum HPL #' . $cid);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            ProjectHplConsumption::delete($cid);
            // Log pe placă (Istoric placă + Jurnal)
            $bid = (int)($before['board_id'] ?? 0);
            if ($bid > 0 && $qtyBoards > 0) {
                Audit::log('HPL_STOCK_UNRESERVE', 'hpl_boards', $bid, null, null, [
                    'message' => 'Anulare rezervare/consum HPL: +' . $qtyBoards . ' buc (FULL) înapoi la Disponibil pentru Proiect: ' . (string)($project['code'] ?? '') . ' · ' . (string)($project['name'] ?? ''),
                    'board_id' => $bid,
                    'project_id' => $projectId,
                    'project_code' => (string)($project['code'] ?? ''),
                    'project_name' => (string)($project['name'] ?? ''),
                    'consumption_id' => $cid,
                    'qty_boards' => (int)$qtyBoards,
                    'from_status' => $from,
                    'to_status' => 'AVAILABLE',
                    'url_board' => \App\Core\Url::to('/stock/boards/' . $bid),
                    'url_project' => \App\Core\Url::to('/projects/' . $projectId),
                    'url_project_consum' => \App\Core\Url::to('/projects/' . $projectId . '?tab=consum'),
                ]);
            }
            Audit::log('PROJECT_CONSUMPTION_DELETE', 'project_hpl_consumptions', $cid, $before, null, [
                'message' => 'Ștergere consum HPL.',
                'project_id' => $projectId,
                'board_id' => (int)($before['board_id'] ?? 0),
            ]);
            Session::flash('toast_success', 'Consum HPL șters.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge consumul HPL.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=consum');
    }

    public static function createDelivery(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $date = trim((string)($_POST['delivery_date'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($date === '') {
            Session::flash('toast_error', 'Data livrării este obligatorie.');
            Response::redirect('/projects/' . $projectId . '?tab=deliveries');
        }

        // qty per project_product_id: delivery_qty[ppId] => val
        $qtyMap = $_POST['delivery_qty'] ?? [];
        if (!is_array($qtyMap)) $qtyMap = [];

        $pps = ProjectProduct::forProject($projectId);
        $ppById = [];
        foreach ($pps as $pp) {
            $ppById[(int)$pp['id']] = $pp;
        }

        $items = [];
        foreach ($qtyMap as $k => $v) {
            $ppId = is_numeric($k) ? (int)$k : 0;
            if ($ppId <= 0 || !isset($ppById[$ppId])) continue;
            $qty = Validator::dec(is_scalar($v) ? (string)$v : '') ?? 0.0;
            if ($qty <= 0) continue;

            $pp = $ppById[$ppId];
            $max = max(0.0, (float)($pp['qty'] ?? 0) - (float)($pp['delivered_qty'] ?? 0));
            if ($qty > $max + 1e-9) {
                Session::flash('toast_error', 'Nu poți livra peste cantitatea rămasă pentru produsul ' . (string)($pp['product_name'] ?? '') . '.');
                Response::redirect('/projects/' . $projectId . '?tab=deliveries');
            }
            $items[] = ['pp_id' => $ppId, 'qty' => $qty];
        }

        if (!$items) {
            Session::flash('toast_error', 'Nu ai selectat nicio cantitate de livrat.');
            Response::redirect('/projects/' . $projectId . '?tab=deliveries');
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $deliveryId = ProjectDelivery::create([
                'project_id' => $projectId,
                'delivery_date' => $date,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $it) {
                $ppId = (int)$it['pp_id'];
                $qty = (float)$it['qty'];

                ProjectDeliveryItem::create([
                    'delivery_id' => $deliveryId,
                    'project_product_id' => $ppId,
                    'qty' => $qty,
                ]);

                $pp = $ppById[$ppId];
                $beforePP = $pp;
                $newDelivered = (float)($pp['delivered_qty'] ?? 0) + $qty;
                $totalQty = (float)($pp['qty'] ?? 0);
                if ($newDelivered > $totalQty) $newDelivered = $totalQty;

                // update status based on delivered
                $newStatus = (string)($pp['production_status'] ?? 'DE_PREGATIT');
                if ($newDelivered >= $totalQty - 1e-9) $newStatus = 'LIVRAT_COMPLET';
                elseif ($newDelivered > 0) $newStatus = 'LIVRAT_PARTIAL';

                ProjectProduct::updateFields($ppId, [
                    'qty' => $totalQty,
                    'unit' => (string)($pp['unit'] ?? 'buc'),
                    'production_status' => $newStatus,
                    'delivered_qty' => $newDelivered,
                    'notes' => $pp['notes'] ?? null,
                ]);

                Audit::log('PROJECT_DELIVERY_ITEM', 'project_products', $ppId, $beforePP, null, [
                    'message' => 'Livrare produs: +' . $qty . ' (total livrat ' . $newDelivered . '/' . $totalQty . ')',
                    'project_id' => $projectId,
                    'delivery_id' => $deliveryId,
                    'qty' => $qty,
                ]);
            }

            // update project status based on product deliveries
            $ppsNow = ProjectProduct::forProject($projectId);
            $allDelivered = true;
            $anyDelivered = false;
            foreach ($ppsNow as $pp) {
                $t = (float)($pp['qty'] ?? 0);
                $d = (float)($pp['delivered_qty'] ?? 0);
                if ($d > 0) $anyDelivered = true;
                if ($t > 0 && $d < $t - 1e-9) $allDelivered = false;
            }
            $beforeProj = $project;
            $afterProj = $project;
            if ($allDelivered && $ppsNow) {
                $afterProj['status'] = 'LIVRAT_COMPLET';
                $afterProj['completed_at'] = date('Y-m-d H:i:s');
            } elseif ($anyDelivered) {
                $afterProj['status'] = 'LIVRAT_PARTIAL';
            }
            if ((string)($beforeProj['status'] ?? '') !== (string)($afterProj['status'] ?? '')) {
                Project::update($projectId, $afterProj);
                Audit::log('PROJECT_STATUS_CHANGE', 'projects', $projectId, $beforeProj, $afterProj, [
                    'message' => 'Status proiect actualizat automat după livrare.',
                    'delivery_id' => $deliveryId,
                ]);
            }

            $pdo->commit();

            Audit::log('PROJECT_DELIVERY_CREATE', 'project_deliveries', $deliveryId, null, null, [
                'message' => 'A creat livrare pentru proiect.',
                'project_id' => $projectId,
                'delivery_date' => $date,
                'note' => $note !== '' ? $note : null,
                'items_count' => count($items),
            ]);
            Session::flash('toast_success', 'Livrare salvată.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva livrarea: ' . $e->getMessage());
        }

        Response::redirect('/projects/' . $projectId . '?tab=deliveries');
    }

    public static function uploadProjectFile(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        if (empty($_FILES['file']['name'] ?? '')) {
            Session::flash('toast_error', 'Alege un fișier.');
            Response::redirect('/projects/' . $projectId . '?tab=files');
        }

        $category = trim((string)($_POST['category'] ?? ''));
        $entityType = trim((string)($_POST['entity_type'] ?? 'projects'));
        $entityId = Validator::int(trim((string)($_POST['entity_id'] ?? (string)$projectId)), 1) ?? $projectId;

        // Validate entity ownership
        if ($entityType === 'projects') {
            $entityId = $projectId;
        } elseif ($entityType === 'project_products') {
            $pp = ProjectProduct::find($entityId);
            if (!$pp || (int)($pp['project_id'] ?? 0) !== $projectId) {
                Session::flash('toast_error', 'Produs proiect invalid.');
                Response::redirect('/projects/' . $projectId . '?tab=files');
            }
        } else {
            Session::flash('toast_error', 'Tip entitate invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=files');
        }

        try {
            $up = Upload::saveEntityFile($_FILES['file']);
            $fid = EntityFile::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'category' => $category !== '' ? $category : null,
                'original_name' => $up['original_name'],
                'stored_name' => $up['stored_name'],
                'mime' => $up['mime'],
                'size_bytes' => $up['size_bytes'],
                'uploaded_by' => Auth::id(),
            ]);
            Audit::log('FILE_UPLOAD', 'entity_files', $fid, null, null, [
                'message' => 'Upload fișier: ' . $up['original_name'],
                'project_id' => $projectId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'category' => $category !== '' ? $category : null,
                'stored_name' => $up['stored_name'],
            ]);
            Session::flash('toast_success', 'Fișier încărcat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot încărca fișierul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=files');
    }

    public static function deleteProjectFile(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $fileId = (int)($params['fileId'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $file = EntityFile::find($fileId);
        if (!$file) {
            Session::flash('toast_error', 'Fișier inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=files');
        }

        $etype = (string)($file['entity_type'] ?? '');
        $eid = (int)($file['entity_id'] ?? 0);
        if ($etype === 'projects') {
            if ($eid !== $projectId) {
                Session::flash('toast_error', 'Fișier invalid.');
                Response::redirect('/projects/' . $projectId . '?tab=files');
            }
        } elseif ($etype === 'project_products') {
            $pp = ProjectProduct::find($eid);
            if (!$pp || (int)($pp['project_id'] ?? 0) !== $projectId) {
                Session::flash('toast_error', 'Fișier invalid.');
                Response::redirect('/projects/' . $projectId . '?tab=files');
            }
        } else {
            Session::flash('toast_error', 'Fișier invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=files');
        }

        $stored = (string)($file['stored_name'] ?? '');
        $fs = dirname(__DIR__, 2) . '/storage/uploads/files/' . $stored;
        try {
            EntityFile::delete($fileId);
            if ($stored !== '' && is_file($fs)) {
                @unlink($fs);
            }
            Audit::log('FILE_DELETE', 'entity_files', $fileId, $file, null, [
                'message' => 'Ștergere fișier: ' . (string)($file['original_name'] ?? ''),
                'project_id' => $projectId,
                'entity_type' => $etype,
                'entity_id' => $eid,
                'stored_name' => $stored,
            ]);
            Session::flash('toast_success', 'Fișier șters.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge fișierul.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=files');
    }

    public static function addWorkLog(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $type = trim((string)($_POST['work_type'] ?? ''));
        if (!in_array($type, ['CNC','ATELIER'], true)) {
            Session::flash('toast_error', 'Tip invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=hours');
        }
        $ppId = Validator::int(trim((string)($_POST['project_product_id'] ?? '')), 1);
        $he = Validator::dec(trim((string)($_POST['hours_estimated'] ?? '')));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($he !== null && $he <= 0) $he = null;
        $ha = null; // nu mai folosim ore reale în formular

        if ($he === null) {
            Session::flash('toast_error', 'Completează orele estimate (valoare > 0).');
            Response::redirect('/projects/' . $projectId . '?tab=hours');
        }

        // Cost/oră din Setări costuri (admin-only)
        $cph = null;
        try {
            $cph = ($type === 'CNC')
                ? AppSetting::getFloat(AppSetting::KEY_COST_CNC_PER_HOUR)
                : AppSetting::getFloat(AppSetting::KEY_COST_LABOR_PER_HOUR);
        } catch (\Throwable $e) {
            $cph = null;
        }
        if ($cph === null || $cph < 0) {
            Session::flash('toast_error', 'Costurile nu sunt setate. (Setări costuri)');
            Response::redirect('/projects/' . $projectId . '?tab=hours');
        }

        // validate pp belongs to project if set
        if ($ppId !== null) {
            $pp = ProjectProduct::find($ppId);
            if (!$pp || (int)($pp['project_id'] ?? 0) !== $projectId) {
                Session::flash('toast_error', 'Produs proiect invalid.');
                Response::redirect('/projects/' . $projectId . '?tab=hours');
            }
        }

        try {
            $wid = ProjectWorkLog::create([
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'work_type' => $type,
                'hours_estimated' => $he,
                'hours_actual' => $ha,
                'cost_per_hour' => $cph,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);
            Audit::log('PROJECT_WORK_LOG_CREATE', 'project_work_logs', $wid, null, null, [
                'message' => 'A adăugat ore ' . $type,
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'hours_estimated' => $he,
                'hours_actual' => $ha,
                'cost_per_hour' => $cph,
                'note' => $note !== '' ? $note : null,
            ]);
            Session::flash('toast_success', 'Înregistrare salvată.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot salva: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=hours');
    }

    public static function addDiscussion(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }
        $msg = trim((string)($_POST['comment'] ?? ''));
        if ($msg === '') {
            Session::flash('toast_error', 'Mesaj gol.');
            Response::redirect('/projects/' . $projectId . '?tab=discutii');
        }
        if (mb_strlen($msg) > 4000) {
            $msg = mb_substr($msg, 0, 4000);
        }
        try {
            $cid = EntityComment::create('projects', $projectId, $msg, Auth::id());
            if ($cid > 0) {
                Audit::log('PROJECT_DISCUSSION_ADD', 'entity_comments', $cid, null, null, [
                    'message' => 'Mesaj în discuții proiect.',
                    'project_id' => $projectId,
                ]);
                Session::flash('toast_success', 'Mesaj trimis.');
            } else {
                Session::flash('toast_error', 'Nu pot salva mesajul.');
            }
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot salva mesajul.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=discutii');
    }

    public static function deleteWorkLog(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $workId = (int)($params['workId'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $before = ProjectWorkLog::find($workId);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Înregistrare inexistentă.');
            Response::redirect('/projects/' . $projectId . '?tab=hours');
        }

        try {
            ProjectWorkLog::delete($workId);
            Audit::log('PROJECT_WORK_LOG_DELETE', 'project_work_logs', $workId, $before, null, [
                'message' => 'A șters înregistrare ore.',
                'project_id' => $projectId,
            ]);
            Session::flash('toast_success', 'Înregistrare ștearsă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=hours');
    }

    public static function addProjectLabel(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }
        $name = trim((string)($_POST['label_name'] ?? ''));
        if ($name === '') {
            Session::flash('toast_error', 'Etichetă invalidă.');
            Response::redirect('/projects/' . $projectId . '?tab=general');
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $lid = Label::upsert($name, null);
            if ($lid <= 0) throw new \RuntimeException('Nu pot crea eticheta.');
            EntityLabel::attach('projects', $projectId, $lid, 'DIRECT', Auth::id());

            // propagare pe toate produsele proiectului
            $pps = ProjectProduct::forProject($projectId);
            $ppIds = array_map(fn($pp) => (int)($pp['id'] ?? 0), $pps);
            EntityLabel::propagateToProjectProducts($ppIds, [$lid], Auth::id());

            $pdo->commit();
            Audit::log('PROJECT_LABEL_ADD', 'projects', $projectId, null, null, [
                'message' => 'A adăugat etichetă: ' . $name,
                'project_id' => $projectId,
                'label_id' => $lid,
                'label_name' => $name,
            ]);
            Session::flash('toast_success', 'Etichetă adăugată.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot adăuga eticheta.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=general');
    }

    public static function removeProjectLabel(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $labelId = (int)($params['labelId'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }
        if ($labelId <= 0) {
            Session::flash('toast_error', 'Etichetă invalidă.');
            Response::redirect('/projects/' . $projectId . '?tab=general');
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            EntityLabel::detach('projects', $projectId, $labelId, 'DIRECT');
            $pps = ProjectProduct::forProject($projectId);
            $ppIds = array_map(fn($pp) => (int)($pp['id'] ?? 0), $pps);
            EntityLabel::removeInheritedFromProjectProducts($ppIds, $labelId);
            $pdo->commit();

            Audit::log('PROJECT_LABEL_REMOVE', 'projects', $projectId, null, null, [
                'message' => 'A șters etichetă de pe proiect.',
                'project_id' => $projectId,
                'label_id' => $labelId,
            ]);
            Session::flash('toast_success', 'Etichetă ștearsă.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot șterge eticheta.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=general');
    }
}

