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
    private const HPL_NOTE_HALF_REMAINDER = 'REST_JUMATATE';
    private const HPL_NOTE_AUTO_CONSUME = 'CONSUM_AUTO';
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
     * Greutăți doar după cantitate (buc).
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

    // NOTĂ: alocarea automată HPL pe produse a fost eliminată (cerință).

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
        // IMPORTANT: manopera se distribuie pe bucăți (qty), nu pe mp.
        $w2 = self::productQtyOnlyWeights($projectProducts);
        /** @var array<int,int> $ppIds */
        $ppIds = $w2['ppIds'];
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
            $qty = 0.0;
            // qty real (buc) pentru afișare
            foreach ($projectProducts as $pp) {
                if ((int)($pp['id'] ?? 0) === $ppId) {
                    $qq = (float)($pp['qty'] ?? 0);
                    $qty = $qq > 0 ? $qq : 0.0;
                    break;
                }
            }
            $weight = (float)($w2['weightById'][$ppId] ?? 0.0);

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
        // IMPORTANT: Magazie se distribuie pe bucăți (qty), nu pe mp.
        $w = self::productQtyOnlyWeights($projectProducts);
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
     * HPL cost (fără alocare automată pe produse):
     * costul unui produs se calculează doar din placa selectată pe produs (hpl_board_id)
     * și suprafața lui (m2_per_unit × qty), la prețul lei/mp al plăcii.
     *
     * @param array<int, array<string,mixed>> $projectProducts
     * @return array<int, array{hpl_cost:float}>
     */
    private static function hplCostByProduct(array $projectProducts): array
    {
        $out = [];
        foreach ($projectProducts as $pp) {
            $ppId = (int)($pp['id'] ?? 0);
            if ($ppId <= 0) continue;
            $out[$ppId] = ['hpl_cost' => 0.0];

            $boardId = isset($pp['hpl_board_id']) && $pp['hpl_board_id'] !== null && $pp['hpl_board_id'] !== '' ? (int)$pp['hpl_board_id'] : 0;
            if ($boardId <= 0) continue;
            $qty = (float)($pp['qty'] ?? 0);
            $m2u = isset($pp['m2_per_unit']) ? (float)($pp['m2_per_unit'] ?? 0) : 0.0;
            if ($qty <= 0 || $m2u <= 0) continue;

            $ppm = 0.0;
            try {
                $b = HplBoard::find($boardId);
                if ($b) {
                    $ppm = isset($b['sale_price_per_m2']) && $b['sale_price_per_m2'] !== null && $b['sale_price_per_m2'] !== '' && is_numeric($b['sale_price_per_m2'])
                        ? (float)$b['sale_price_per_m2']
                        : 0.0;
                    if ($ppm <= 0) {
                        $sale = (isset($b['sale_price']) && $b['sale_price'] !== null && $b['sale_price'] !== '' && is_numeric($b['sale_price']))
                            ? (float)$b['sale_price']
                            : null;
                        $wmm = (int)($b['std_width_mm'] ?? 0);
                        $hmm = (int)($b['std_height_mm'] ?? 0);
                        $area = ($wmm > 0 && $hmm > 0) ? (($wmm * $hmm) / 1000000.0) : 0.0;
                        if ($sale !== null && $sale >= 0 && $area > 0) $ppm = $sale / $area;
                    }
                }
            } catch (\Throwable $e) {
                $ppm = 0.0;
            }
            if ($ppm <= 0) continue;
            $out[$ppId]['hpl_cost'] = ($m2u * $qty) * $ppm;
        }
        return $out;
    }

    /**
     * Sumar proiect derivat din alocările pe produse (pentru tab-ul Produse).
     * Totalurile de cost trebuie să fie suma costurilor afișate pe produse.
     *
     * @param array<int, array<string,mixed>> $projectProducts
     * @param array<int, array<string,mixed>> $laborByProduct
     * @param array<int, array<string,mixed>> $materialsByProduct
     * @param array<int, array<string,mixed>> $magConsum
     * @param array<int, array<string,mixed>> $hplConsum
     * @return array<string,mixed>
     */
    private static function projectSummaryFromProducts(
        array $projectProducts,
        array $laborByProduct,
        array $materialsByProduct,
        array $magConsum,
        array $hplConsum
    ): array {
        $laborCost = 0.0;
        $laborCncH = 0.0;
        $laborAtH = 0.0;
        foreach ($projectProducts as $pp) {
            $ppId = (int)($pp['id'] ?? 0);
            if ($ppId <= 0) continue;
            $lab = $laborByProduct[$ppId] ?? null;
            if (is_array($lab)) {
                $laborCost += (float)($lab['total_cost'] ?? 0.0);
                $laborCncH += (float)($lab['cnc_hours'] ?? 0.0);
                $laborAtH += (float)($lab['atelier_hours'] ?? 0.0);
            }
        }

        $magCost = 0.0;
        $hplCost = 0.0;
        foreach ($projectProducts as $pp) {
            $ppId = (int)($pp['id'] ?? 0);
            if ($ppId <= 0) continue;
            $m = $materialsByProduct[$ppId] ?? null;
            if (!is_array($m)) continue;
            $magCost += (float)($m['mag_cost'] ?? 0.0);
            $hplCost += (float)($m['hpl_cost'] ?? 0.0);
        }
        $totalCost = $laborCost + $magCost + $hplCost;

        // Magazie cantitativ: agregăm pe unit și pe mod
        $magConsumed = [];
        $magReserved = [];
        $magItems = [];
        foreach ($magConsum as $c) {
            $itemId = isset($c['item_id']) ? (int)$c['item_id'] : 0;
            $qty = isset($c['qty']) ? (float)$c['qty'] : 0.0;
            if ($qty <= 0) continue;
            $unit = (string)($c['unit'] ?? 'buc');
            $mode = (string)($c['mode'] ?? '');
            if ($mode === 'CONSUMED') {
                $magConsumed[$unit] = ($magConsumed[$unit] ?? 0.0) + $qty;
            } elseif ($mode === 'RESERVED') {
                $magReserved[$unit] = ($magReserved[$unit] ?? 0.0) + $qty;
            }

            // Agregare pe accesorii (cantități + preț + valoare)
            if ($itemId > 0) {
                if (!isset($magItems[$itemId])) {
                    $magItems[$itemId] = [
                        'item_id' => $itemId,
                        'winmentor_code' => (string)($c['winmentor_code'] ?? ''),
                        'item_name' => (string)($c['item_name'] ?? ''),
                        'unit' => $unit,
                        'unit_price' => (isset($c['item_unit_price']) && $c['item_unit_price'] !== null && $c['item_unit_price'] !== '' && is_numeric($c['item_unit_price']))
                            ? (float)$c['item_unit_price']
                            : 0.0,
                        'qty_consumed' => 0.0,
                        'qty_reserved' => 0.0,
                    ];
                }
                if ($mode === 'CONSUMED') {
                    $magItems[$itemId]['qty_consumed'] += $qty;
                } elseif ($mode === 'RESERVED') {
                    $magItems[$itemId]['qty_reserved'] += $qty;
                }
            }
        }

        // Sortăm accesorii după valoare desc (consumat + rezervat)
        $magItemsList = array_values($magItems);
        usort($magItemsList, function (array $a, array $b): int {
            $pa = (float)($a['unit_price'] ?? 0.0);
            $qa = (float)($a['qty_consumed'] ?? 0.0) + (float)($a['qty_reserved'] ?? 0.0);
            $va = $pa * $qa;
            $pb = (float)($b['unit_price'] ?? 0.0);
            $qb = (float)($b['qty_consumed'] ?? 0.0) + (float)($b['qty_reserved'] ?? 0.0);
            $vb = $pb * $qb;
            return $vb <=> $va;
        });

        // HPL cantitativ: mp rezervat/consumat din consumuri
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

        $hplResM2 = 0.0;
        $hplResCost = 0.0;
        $hplConM2 = 0.0;
        $hplConCost = 0.0;
        $hplTotM2 = 0.0;
        $hplTotCost = 0.0;
        foreach ($hplConsum as $c) {
            $m2 = isset($c['qty_m2']) ? (float)$c['qty_m2'] : 0.0;
            if ($m2 <= 0) continue;
            $ppm = $pricePm2($c);
            $cost = ($ppm > 0) ? ($m2 * $ppm) : 0.0;
            if ($ppm > 0) {
                $hplTotM2 += $m2;
                $hplTotCost += $cost;
            }
            $mode = (string)($c['mode'] ?? '');
            if ($mode === 'RESERVED') {
                $hplResM2 += $m2;
                $hplResCost += $cost;
            } elseif ($mode === 'CONSUMED') {
                $hplConM2 += $m2;
                $hplConCost += $cost;
            }
        }
        $hplAvgPpm = ($hplTotM2 > 0) ? ($hplTotCost / $hplTotM2) : 0.0;

        // mp necesari (din m2_per_unit × qty)
        $needM2 = 0.0;
        $needByPp = [];
        foreach ($projectProducts as $pp) {
            $ppId = (int)($pp['id'] ?? 0);
            if ($ppId <= 0) continue;
            $qty = (float)($pp['qty'] ?? 0);
            $m2u = isset($pp['m2_per_unit']) ? (float)($pp['m2_per_unit'] ?? 0) : 0.0;
            $m2t = ($qty > 0 && $m2u > 0) ? ($qty * $m2u) : 0.0;
            $needByPp[$ppId] = $m2t;
            $needM2 += $m2t;
        }
        $productsHplM2 = 0.0;
        foreach ($projectProducts as $pp) {
            $ppId = (int)($pp['id'] ?? 0);
            if ($ppId <= 0) continue;
            $m2t = (float)($needByPp[$ppId] ?? 0.0);
            $productsHplM2 += $m2t;
        }

        $reservedRemainingM2 = max(0.0, $hplResM2 - $needM2);
        $reservedRemainingCost = ($hplAvgPpm > 0) ? ($reservedRemainingM2 * $hplAvgPpm) : 0.0;

        return [
            // costuri din produse (cerință)
            'labor_cost' => $laborCost,
            'mag_cost' => $magCost,
            'hpl_cost' => $hplCost,
            'total_cost' => $totalCost,
            // cantitativ
            'labor_cnc_hours' => $laborCncH,
            'labor_atelier_hours' => $laborAtH,
            'mag_consumed_by_unit' => $magConsumed,
            'mag_reserved_by_unit' => $magReserved,
            'mag_items' => $magItemsList,
            'hpl_reserved_m2' => $hplResM2,
            'hpl_consumed_m2' => $hplConM2,
            'products_need_m2' => $needM2,
            'products_hpl_m2' => $productsHplM2,
            // valori suplimentare
            'hpl_avg_ppm' => $hplAvgPpm,
            'hpl_reserved_remaining_m2' => $reservedRemainingM2,
            'hpl_reserved_remaining_cost' => $reservedRemainingCost,
            'hpl_reserved_cost' => $hplResCost,
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

    // NOTĂ: alocarea automată HPL pe produse a fost eliminată (cerință).

    /**
     * Mută plăci întregi (piece_type=FULL) între status-uri în stoc (AVAILABLE/RESERVED/CONSUMED).
     * Se face split/merge pe rânduri cu qty.
     */
    private static function moveFullBoards(
        int $boardId,
        int $qty,
        string $fromStatus,
        string $toStatus,
        ?string $noteAppend = null,
        ?int $projectId = null,
        ?string $fromLocation = null,
        ?string $toLocation = null
    ): void
    {
        $qty = (int)$qty;
        if ($qty <= 0) return;

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();

        $fromLocation = $fromLocation !== null ? trim((string)$fromLocation) : null;
        $toLocation = $toLocation !== null ? trim((string)$toLocation) : null;

        // Notă: pentru plăcile FULL nu filtrăm după is_accounting (compat cu date vechi).
        $rows = [];
        try {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = ?
                  AND (? IS NULL OR location = ?)
                  AND (
                        ? IS NULL
                        OR ? = 0
                        OR status = 'AVAILABLE'
                        OR project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                ORDER BY (location = 'Producție') DESC, created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([
                (int)$boardId,
                $fromStatus,
                $fromLocation,
                $fromLocation,
                $projectId,
                $projectId,
                $projectId,
                $projectId,
                $projectId,
                $projectId,
            ]);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = ?
                  AND (? IS NULL OR location = ?)
                  AND (
                        ? IS NULL
                        OR ? = 0
                        OR status = 'AVAILABLE'
                        OR project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                ORDER BY (location = 'Producție') DESC, created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([
                (int)$boardId,
                $fromStatus,
                $fromLocation,
                $fromLocation,
                $projectId,
                $projectId,
                $projectId,
                $projectId,
                $projectId,
                $projectId,
            ]);
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
            $destLocation = ($toLocation !== null && $toLocation !== '') ? $toLocation : $location;
            $isAcc = (int)($r['is_accounting'] ?? 1);
            $notes = (string)($r['notes'] ?? '');

            if ($take === $rowQty) {
                // Mută întreg rândul: încercăm să cumulăm într-un rând identic în destinație,
                // ca să evităm duplicatele (ex: la anulare rezervare/consum).
                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, 'FULL', $toStatus, $width, $height, $destLocation, $isAcc, $toStatus === 'AVAILABLE' ? null : $projectId, $id);
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
                        // sincronizare project_id pe destinație
                        try {
                            if ($toStatus === 'AVAILABLE') {
                                HplStockPiece::updateFields($destId, ['project_id' => null]);
                            } elseif ($projectId !== null && $projectId > 0) {
                                HplStockPiece::updateFields($destId, ['project_id' => $projectId]);
                            }
                        } catch (\Throwable $e) {}
                        // sursa a fost cumulată
                        HplStockPiece::delete($id);
                    } else {
                        // fallback: dacă nu avem id valid, mutăm în loc
                        HplStockPiece::updateFields($id, ['status' => $toStatus, 'project_id' => ($toStatus === 'AVAILABLE' ? null : $projectId), 'location' => $destLocation]);
                        if ($noteAppend) HplStockPiece::appendNote($id, $noteAppend);
                    }
                } else {
                    HplStockPiece::updateFields($id, ['status' => $toStatus, 'project_id' => ($toStatus === 'AVAILABLE' ? null : $projectId), 'location' => $destLocation]);
                    if ($noteAppend) HplStockPiece::appendNote($id, $noteAppend);
                }
            } else {
                // scade din rândul sursă
                HplStockPiece::updateQty($id, $rowQty - $take);

                // adaugă/îmbină în rândul destinație
                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, 'FULL', $toStatus, $width, $height, $destLocation, $isAcc, $toStatus === 'AVAILABLE' ? null : $projectId);
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
                        'project_id' => ($toStatus === 'AVAILABLE') ? null : $projectId,
                        'is_accounting' => $isAcc,
                        'piece_type' => 'FULL',
                        'status' => $toStatus,
                        'width_mm' => $width,
                        'height_mm' => $height,
                        'qty' => $take,
                        'location' => $destLocation,
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

    private static function moveReservedOffcutHalfToLocation(
        \PDO $pdo,
        int $projectId,
        int $boardId,
        int $halfHeightMm,
        int $widthMm,
        int $qty,
        string $fromLocation,
        string $toLocation,
        ?string $noteAppend = null
    ): void {
        $qty = (int)$qty;
        if ($qty <= 0 || $projectId <= 0 || $boardId <= 0 || $halfHeightMm <= 0 || $widthMm <= 0) return;
        $fromLocation = trim($fromLocation);
        $toLocation = trim($toLocation);

        $rows = [];
        try {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'OFFCUT'
                  AND status = 'RESERVED'
                  AND width_mm = ?
                  AND height_mm = ?
                  AND location = ?
                  AND qty > 0
                  AND (
                        project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                  AND (is_accounting = 1 OR is_accounting IS NULL)
                ORDER BY created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, (int)$widthMm, (int)$halfHeightMm, $fromLocation, (int)$projectId, (int)$projectId, (int)$projectId, (int)$projectId]);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'OFFCUT'
                  AND status = 'RESERVED'
                  AND width_mm = ?
                  AND height_mm = ?
                  AND location = ?
                  AND qty > 0
                  AND (
                        project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                ORDER BY created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, (int)$widthMm, (int)$halfHeightMm, $fromLocation, (int)$projectId, (int)$projectId, (int)$projectId, (int)$projectId]);
            $rows = $st->fetchAll();
        }
        if (!$rows) return;

        $need = $qty;
        foreach ($rows as $r) {
            if ($need <= 0) break;
            $id = (int)($r['id'] ?? 0);
            $rowQty = (int)($r['qty'] ?? 0);
            if ($id <= 0 || $rowQty <= 0) continue;

            $take = min($need, $rowQty);
            if ($take <= 0) continue;

            $isAcc = (int)($r['is_accounting'] ?? 1);
            $notes = (string)($r['notes'] ?? '');

            if ($take === $rowQty) {
                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, 'OFFCUT', 'RESERVED', $widthMm, $halfHeightMm, $toLocation, $isAcc, $projectId, $id);
                } catch (\Throwable $e) {}
                if ($ident) {
                    $destId = (int)($ident['id'] ?? 0);
                    if ($destId > 0) {
                        HplStockPiece::incrementQty($destId, $take);
                        if ($noteAppend) HplStockPiece::appendNote($destId, $noteAppend);
                        try { HplStockPiece::updateFields($destId, ['project_id' => $projectId]); } catch (\Throwable $e) {}
                        HplStockPiece::delete($id);
                    } else {
                        HplStockPiece::updateFields($id, ['location' => $toLocation, 'project_id' => $projectId]);
                        if ($noteAppend) HplStockPiece::appendNote($id, $noteAppend);
                    }
                } else {
                    HplStockPiece::updateFields($id, ['location' => $toLocation, 'project_id' => $projectId]);
                    if ($noteAppend) HplStockPiece::appendNote($id, $noteAppend);
                }
            } else {
                HplStockPiece::updateQty($id, $rowQty - $take);
                $ident = null;
                try { $ident = HplStockPiece::findIdentical($boardId, 'OFFCUT', 'RESERVED', $widthMm, $halfHeightMm, $toLocation, $isAcc, $projectId); } catch (\Throwable $e) {}
                if ($ident) {
                    HplStockPiece::incrementQty((int)$ident['id'], $take);
                    if ($noteAppend) HplStockPiece::appendNote((int)$ident['id'], $noteAppend);
                } else {
                    $newNotes = trim($notes);
                    if ($noteAppend) $newNotes = trim($newNotes . ($newNotes !== '' ? "\n" : '') . $noteAppend);
                    HplStockPiece::create([
                        'board_id' => $boardId,
                        'project_id' => $projectId,
                        'is_accounting' => $isAcc,
                        'piece_type' => 'OFFCUT',
                        'status' => 'RESERVED',
                        'width_mm' => $widthMm,
                        'height_mm' => $halfHeightMm,
                        'qty' => $take,
                        'location' => $toLocation,
                        'notes' => $newNotes !== '' ? $newNotes : null,
                    ]);
                }
            }
            $need -= $take;
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
            ],
            'errors' => [],
            'statuses' => self::statuses(),
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

        if ($errors) {
            echo View::render('projects/form', [
                'title' => 'Proiect nou',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
                'statuses' => self::statuses(),
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
                    $projectHplPieces = [];
                    try { $projectHplPieces = HplStockPiece::forProject($id); } catch (\Throwable $e) { $projectHplPieces = []; }
                    $magBy = self::magazieCostByProduct($projectProducts, $magazieConsum);
        $hplBy = self::hplCostByProduct($projectProducts);
                    foreach ($projectProducts as $pp) {
                        $ppId = (int)($pp['id'] ?? 0);
                        if ($ppId <= 0) continue;
                        $materialsByProduct[$ppId] = [
                            'mag_cost' => (float)($magBy[$ppId]['mag_cost'] ?? 0.0),
                            'hpl_cost' => (float)($hplBy[$ppId]['hpl_cost'] ?? 0.0),
                        ];
                    }
            $projectCostSummary = self::projectSummaryFromProducts($projectProducts, $laborByProduct, $materialsByProduct, $magazieConsum, $hplConsum);
                } elseif ($tab === 'consum') {
                    try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
                    try { $magazieConsum = ProjectMagazieConsumption::forProject($id); } catch (\Throwable $e) { $magazieConsum = []; }
                    try { $hplConsum = ProjectHplConsumption::forProject($id); } catch (\Throwable $e) { $hplConsum = []; }
                    try { $hplBoards = HplBoard::allWithTotals(null, null); } catch (\Throwable $e) { $hplBoards = []; }
                    try { $magazieItems = MagazieItem::all(null, 5000); } catch (\Throwable $e) { $magazieItems = []; }
                    $projectHplPieces = [];
                    try { $projectHplPieces = HplStockPiece::forProject($id); } catch (\Throwable $e) { $projectHplPieces = []; }
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
                    'hplAlloc' => [],
                    'hplBoards' => $hplBoards,
                    'magazieItems' => $magazieItems,
                    'projectHplPieces' => $projectHplPieces,
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
                    'allocationModes' => [],
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

    public static function canSetProjectProductStatus(): bool
    {
        $u = Auth::user();
        return $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
    }

    public static function canSetProjectProductFinalStatus(): bool
    {
        $u = Auth::user();
        return $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
    }

    /** @return array<int, array{value:string,label:string}> */
    public static function projectProductStatuses(): array
    {
        return [
            ['value' => 'CREAT', 'label' => 'Creat'],
            ['value' => 'PROIECTARE', 'label' => 'Proiectare'],
            ['value' => 'CNC', 'label' => 'CNC'],
            ['value' => 'MONTAJ', 'label' => 'Montaj'],
            ['value' => 'GATA_DE_LIVRARE', 'label' => 'Gata de livrare'],
            ['value' => 'AVIZAT', 'label' => 'Avizat'],
            ['value' => 'LIVRAT', 'label' => 'Livrat'],
        ];
    }

    /** @return array<int,string> */
    private static function allowedProjectProductStatusesForCurrentUser(): array
    {
        $all = array_map(fn($s) => (string)$s['value'], self::projectProductStatuses());
        if (self::canSetProjectProductFinalStatus()) return $all;
        // Operatorii nu pot seta statusurile finale.
        return array_values(array_filter($all, fn($v) => !in_array($v, ['AVIZAT', 'LIVRAT'], true)));
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
                'production_status' => 'CREAT',
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
            'surface_mode' => 'Suprafață',
        ]);
        $errors = $check['errors'];
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
        $salePrice = $salePriceRaw !== '' ? (Validator::dec($salePriceRaw) ?? null) : null;
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $hplBoardId = Validator::int(trim((string)($_POST['hpl_board_id'] ?? '')), 1);
        $surfaceMode = trim((string)($_POST['surface_mode'] ?? ''));
        $surfaceM2 = Validator::dec(trim((string)($_POST['surface_m2'] ?? ''))) ?? null;
        if ($surfaceM2 !== null && $surfaceM2 < 0) $surfaceM2 = null;

        // Suprafață:
        // - 1 placă => value=1 (plăci/buc)
        // - 1/2 placă => value=0.5 (plăci/buc)
        // - mp => value=surface_m2 (mp/buc, salvat cu 2 zecimale)
        $surfaceType = null;
        $surfaceValue = null;
        $m2 = null; // m2_per_unit pentru calcule
        if ($surfaceMode === '1' || $surfaceMode === '0.5') {
            $surfaceType = 'BOARD';
            $surfaceValue = (float)$surfaceMode; // 1 sau 0.5
            [$hmm, $wmm] = self::defaultBoardDimsMmForProject($projectId);
            if ($hmm <= 0 || $wmm <= 0) { $hmm = 2800; $wmm = 2070; } // fallback
            // Regulă: 1/2 placă = jumătate din lungime (h/2), lățime constantă (w).
            $effH = ($surfaceValue < 0.999) ? ($hmm / 2.0) : (float)$hmm;
            $m2 = ($effH * (float)$wmm) / 1000000.0;
        } elseif ($surfaceMode === 'M2') {
            $surfaceType = 'M2';
            $surfaceValue = $surfaceM2 !== null ? round((float)$surfaceM2, 2) : null;
            $m2 = $surfaceValue !== null ? (float)$surfaceValue : null;
        }
        if ($surfaceType === null || $surfaceValue === null || (float)$surfaceValue <= 0 || $m2 === null || $m2 <= 0) {
            $errors['surface_mode'] = 'Suprafață invalidă.';
        }
        if ($surfaceMode === 'M2' && ($surfaceValue === null || (float)$surfaceValue <= 0)) {
            $errors['surface_m2'] = 'Introdu suprafața (mp) per bucată.';
        }
        if ($hplBoardId !== null) {
            if (!self::isHplBoardReservedForProject($projectId, (int)$hplBoardId)) {
                $errors['hpl_board_id'] = 'Placa HPL selectată nu este rezervată pe acest proiect.';
            }
        }
        // Pentru suprafață în plăci (1 / 1/2), avem nevoie de o placă HPL selectată (sau inferată).
        if ($surfaceType === 'BOARD' && $surfaceValue !== null && (abs((float)$surfaceValue - 1.0) < 1e-9 || abs((float)$surfaceValue - 0.5) < 1e-9)) {
            if ($hplBoardId === null) {
                $inf = self::inferSingleReservedBoardIdForProject($projectId);
                if ($inf !== null) {
                    $hplBoardId = $inf;
                } else {
                    $errors['hpl_board_id'] = 'Selectează placa HPL pentru această piesă (suprafață în plăci).';
                }
            }
        }

        if ($qty <= 0) $errors['qty'] = 'Cantitate invalidă.';
        if ($salePriceRaw !== '' && ($salePrice === null || $salePrice < 0)) {
            $errors['sale_price'] = 'Preț vânzare invalid.';
        }
        if ($errors) {
            Session::flash('toast_error', 'Completează corect produsul.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            $pid = Product::create([
                'code' => $code !== '' ? $code : null,
                'name' => $name,
                'sale_price' => ($salePrice !== null && $salePrice >= 0) ? round((float)$salePrice, 2) : null,
                'width_mm' => null,
                'height_mm' => null,
                'notes' => $desc !== '' ? $desc : null,
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
                'unit' => 'buc',
                'm2_per_unit' => $m2 !== null ? (float)$m2 : 0.0,
                'surface_type' => $surfaceType,
                'surface_value' => $surfaceValue,
                'production_status' => 'CREAT',
                'hpl_board_id' => $hplBoardId !== null ? (int)$hplBoardId : null,
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
                'unit' => 'buc',
                'm2_per_unit' => $m2 !== null ? (float)$m2 : null,
                'surface_type' => $surfaceType,
                'surface_value' => $surfaceValue,
                'hpl_board_id' => $hplBoardId !== null ? (int)$hplBoardId : null,
            ]);

            Session::flash('toast_success', 'Produs creat și adăugat în proiect.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot crea produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    private static function defaultBoardAreaM2ForProject(int $projectId): float
    {
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        try {
            $st = $pdo->prepare("
                SELECT b.std_width_mm, b.std_height_mm, COALESCE(SUM(c.qty_boards),0) AS q
                FROM project_hpl_consumptions c
                INNER JOIN hpl_boards b ON b.id = c.board_id
                WHERE c.project_id = ?
                GROUP BY c.board_id, b.std_width_mm, b.std_height_mm
                ORDER BY q DESC, c.board_id DESC
                LIMIT 1
            ");
            $st->execute([(int)$projectId]);
            $r = $st->fetch();
            if (!$r) return 0.0;
            $w = (int)($r['std_width_mm'] ?? 0);
            $h = (int)($r['std_height_mm'] ?? 0);
            if ($w <= 0 || $h <= 0) return 0.0;
            return ($w * $h) / 1000000.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /** @return array{0:int,1:int} height_mm,width_mm */
    private static function defaultBoardDimsMmForProject(int $projectId): array
    {
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        try {
            $st = $pdo->prepare("
                SELECT b.std_width_mm, b.std_height_mm, COALESCE(SUM(c.qty_boards),0) AS q
                FROM project_hpl_consumptions c
                INNER JOIN hpl_boards b ON b.id = c.board_id
                WHERE c.project_id = ?
                GROUP BY c.board_id, b.std_width_mm, b.std_height_mm
                ORDER BY q DESC, c.board_id DESC
                LIMIT 1
            ");
            $st->execute([(int)$projectId]);
            $r = $st->fetch();
            if (!$r) return [0, 0];
            $w = (int)($r['std_width_mm'] ?? 0);
            $h = (int)($r['std_height_mm'] ?? 0);
            return [$h, $w];
        } catch (\Throwable $e) {
            return [0, 0];
        }
    }

    private static function isHplBoardReservedForProject(int $projectId, int $boardId): bool
    {
        if ($projectId <= 0 || $boardId <= 0) return false;
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        try {
            $st = $pdo->prepare("
                SELECT COUNT(*) AS c
                FROM project_hpl_consumptions
                WHERE project_id = ?
                  AND board_id = ?
                  AND mode = 'RESERVED'
                  AND COALESCE(qty_boards,0) > 0
            ");
            $st->execute([(int)$projectId, (int)$boardId]);
            $r = $st->fetch();
            return ((int)($r['c'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            // Compat: fără qty_boards
            try {
                $st = $pdo->prepare("
                    SELECT COUNT(*) AS c
                    FROM project_hpl_consumptions
                    WHERE project_id = ?
                      AND board_id = ?
                      AND mode = 'RESERVED'
                ");
                $st->execute([(int)$projectId, (int)$boardId]);
                $r = $st->fetch();
                return ((int)($r['c'] ?? 0)) > 0;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    /**
     * Dacă proiectul are EXACT o singură placă rezervată (tip) returnează board_id, altfel null.
     * UX fallback când utilizatorul nu selectează explicit placa pe piesă.
     */
    private static function inferSingleReservedBoardIdForProject(int $projectId): ?int
    {
        if ($projectId <= 0) return null;
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        try {
            $st = $pdo->prepare("
                SELECT board_id
                FROM project_hpl_consumptions
                WHERE project_id = ?
                  AND mode = 'RESERVED'
                  AND COALESCE(qty_boards,0) > 0
                GROUP BY board_id
                ORDER BY board_id ASC
                LIMIT 2
            ");
            $st->execute([(int)$projectId]);
            $rows = $st->fetchAll();
            if (count($rows) === 1) {
                $bid = (int)($rows[0]['board_id'] ?? 0);
                return $bid > 0 ? $bid : null;
            }
            return null;
        } catch (\Throwable $e) {
            // Compat fără qty_boards: folosim qty_m2 > 0
            try {
                $st = $pdo->prepare("
                    SELECT board_id
                    FROM project_hpl_consumptions
                    WHERE project_id = ?
                      AND mode = 'RESERVED'
                      AND qty_m2 > 0
                    GROUP BY board_id
                    ORDER BY board_id ASC
                    LIMIT 2
                ");
                $st->execute([(int)$projectId]);
                $rows = $st->fetchAll();
                if (count($rows) === 1) {
                    $bid = (int)($rows[0]['board_id'] ?? 0);
                    return $bid > 0 ? $bid : null;
                }
            } catch (\Throwable $e2) {}
            return null;
        }
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

        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
        $salePrice = $salePriceRaw !== '' ? (Validator::dec($salePriceRaw) ?? null) : null;
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $hplBoardId = Validator::int(trim((string)($_POST['hpl_board_id'] ?? '')), 1);
        $surfaceMode = trim((string)($_POST['surface_mode'] ?? ''));
        $surfaceM2 = Validator::dec(trim((string)($_POST['surface_m2'] ?? ''))) ?? null;
        if ($surfaceM2 !== null && $surfaceM2 < 0) $surfaceM2 = null;

        $errors = [];
        if ($name === '') $errors['name'] = 'Denumire invalidă.';
        if ($qty <= 0) $errors['qty'] = 'Cantitate invalidă.';
        if ($surfaceMode === '') $errors['surface_mode'] = 'Suprafață invalidă.';

        $surfaceType = null;
        $surfaceValue = null;
        $m2 = null;
        if ($surfaceMode === '1' || $surfaceMode === '0.5') {
            $surfaceType = 'BOARD';
            $surfaceValue = (float)$surfaceMode;
            [$hmm, $wmm] = self::defaultBoardDimsMmForProject($projectId);
            if ($hmm <= 0 || $wmm <= 0) { $hmm = 2800; $wmm = 2070; }
            $effH = ($surfaceValue < 0.999) ? ($hmm / 2.0) : (float)$hmm;
            $m2 = ($effH * (float)$wmm) / 1000000.0;
        } elseif ($surfaceMode === 'M2') {
            $surfaceType = 'M2';
            $surfaceValue = $surfaceM2 !== null ? round((float)$surfaceM2, 2) : null;
            $m2 = $surfaceValue !== null ? (float)$surfaceValue : null;
        }
        if ($surfaceType === null || $surfaceValue === null || (float)$surfaceValue <= 0 || $m2 === null || $m2 <= 0) {
            $errors['surface_mode'] = 'Suprafață invalidă.';
        }
        if ($surfaceMode === 'M2' && ($surfaceValue === null || (float)$surfaceValue <= 0)) {
            $errors['surface_m2'] = 'Introdu suprafața (mp) per bucată.';
        }

        if ($hplBoardId !== null) {
            if (!self::isHplBoardReservedForProject($projectId, (int)$hplBoardId)) {
                $errors['hpl_board_id'] = 'Placa HPL selectată nu este rezervată pe acest proiect.';
            }
        }
        // Pentru suprafață în plăci (1 / 1/2), avem nevoie de o placă HPL selectată (sau inferată).
        if ($surfaceType === 'BOARD' && $surfaceValue !== null && (abs((float)$surfaceValue - 1.0) < 1e-9 || abs((float)$surfaceValue - 0.5) < 1e-9)) {
            if ($hplBoardId === null) {
                $inf = self::inferSingleReservedBoardIdForProject($projectId);
                if ($inf !== null) {
                    $hplBoardId = $inf;
                } else {
                    $errors['hpl_board_id'] = 'Selectează placa HPL pentru această piesă (suprafață în plăci).';
                }
            }
        }
        if ($salePriceRaw !== '' && ($salePrice === null || $salePrice < 0)) {
            $errors['sale_price'] = 'Preț vânzare invalid.';
        }

        if ($errors) {
            Session::flash('toast_error', 'Completează corect produsul.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $prodId = (int)($before['product_id'] ?? 0);
        $prodBefore = $prodId > 0 ? Product::find($prodId) : null;

        $after = [
            'qty' => $qty,
            'unit' => 'buc',
            'm2_per_unit' => (float)$m2,
            'surface_type' => $surfaceType,
            'surface_value' => $surfaceValue,
            // Statusul se schimbă doar pe flow (pas cu pas), nu din edit.
            'production_status' => (string)($before['production_status'] ?? 'CREAT'),
            'hpl_board_id' => $hplBoardId !== null ? (int)$hplBoardId : null,
            'delivered_qty' => (float)($before['delivered_qty'] ?? 0),
            'notes' => $before['notes'] ?? null,
        ];

        try {
            if ($prodId > 0) {
                Product::updateFields($prodId, [
                    'code' => $code !== '' ? $code : null,
                    'name' => $name,
                    'sale_price' => ($salePrice !== null && $salePrice >= 0) ? round((float)$salePrice, 2) : null,
                    'notes' => $desc !== '' ? $desc : null,
                ]);
                $prodAfter = Product::find($prodId);
                Audit::log('PRODUCT_UPDATE', 'products', $prodId, $prodBefore, $prodAfter, [
                    'message' => 'A actualizat produs (din proiect).',
                    'project_id' => $projectId,
                    'project_product_id' => $ppId,
                ]);
            }
            ProjectProduct::updateFields($ppId, $after);
            Audit::log('PROJECT_PRODUCT_UPDATE', 'project_products', $ppId, $before, $after, [
                'message' => 'A actualizat produs în proiect.',
                'project_id' => $projectId,
            ]);
            Session::flash('toast_success', 'Produs actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot actualiza produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function updateProjectProductStatus(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);

        if (!self::canSetProjectProductStatus()) {
            Session::flash('toast_error', 'Nu ai drepturi pentru a schimba statusul.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $before = ProjectProduct::find($ppId);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Produs proiect invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        // Dacă piesa e pe suprafață în plăci (1/0.5), asigurăm că are selectat un HPL (sau îl inferăm dacă există exact unul rezervat).
        $stype0 = (string)($before['surface_type'] ?? '');
        $sval0 = isset($before['surface_value']) && $before['surface_value'] !== null && $before['surface_value'] !== '' ? (float)$before['surface_value'] : null;
        $needsHpl = ($stype0 === 'BOARD' && $sval0 !== null && (abs($sval0 - 1.0) < 1e-9 || abs($sval0 - 0.5) < 1e-9));
        $curBid = isset($before['hpl_board_id']) && $before['hpl_board_id'] !== null && $before['hpl_board_id'] !== '' ? (int)$before['hpl_board_id'] : 0;
        if ($needsHpl && $curBid <= 0) {
            $inf = self::inferSingleReservedBoardIdForProject($projectId);
            if ($inf !== null) {
                try {
                    ProjectProduct::updateFields($ppId, ['hpl_board_id' => (int)$inf]);
                    $before['hpl_board_id'] = (int)$inf;
                    Audit::log('PROJECT_PRODUCT_UPDATE', 'project_products', $ppId, null, null, [
                        'message' => 'Auto-select placă HPL pe piesă (singura rezervată în proiect).',
                        'project_id' => $projectId,
                        'project_product_id' => $ppId,
                        'board_id' => (int)$inf,
                        'via' => 'status_flow',
                    ]);
                } catch (\Throwable $e) {}
            } else {
                Session::flash('toast_error', 'Piesa are suprafață în plăci (1/1⁄2), dar nu are selectată nicio placă HPL. Editează piesa și selectează placa.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        $flow = array_map(fn($s) => (string)$s['value'], self::projectProductStatuses());
        $old = (string)($before['production_status'] ?? 'CREAT');
        $idx = array_search($old, $flow, true);
        if ($idx === false) $idx = 0;
        $next = $flow[$idx + 1] ?? null;
        if ($next === null) {
            Session::flash('toast_success', 'Piesa este deja la ultimul status.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $allowed = self::allowedProjectProductStatusesForCurrentUser();
        if (!in_array($next, $allowed, true)) {
            Session::flash('toast_error', 'Nu ai drepturi să avansezi la următorul status (Avizat/Livrat sunt doar pentru Admin/Gestionar).');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            // La trecerea în CNC: mutăm materialul necesar din Depozit în Producție (rămâne RESERVED).
            if ($next === 'CNC') {
                try {
                    self::ensureHplInProductionOnCnc($projectId, $before);
                } catch (\Throwable $e) {
                    // best-effort: nu blocăm schimbarea statusului dacă mutarea stocului eșuează
                }
            }

            // CNC -> Montaj: consum HPL automat (doar pentru suprafață în plăci: 1 / 0.5)
            if ($old === 'CNC' && $next === 'MONTAJ') {
                try {
                    $err = self::autoConsumeHplOnCncToMontaj($projectId, $before, (string)($_POST['remainder_action'] ?? ''));
                    if ($err !== null) {
                        Session::flash('toast_error', $err);
                        Response::redirect('/projects/' . $projectId . '?tab=products');
                    }
                } catch (\Throwable $e) {
                    Session::flash('toast_error', 'Nu pot consuma HPL automat: ' . $e->getMessage());
                    Response::redirect('/projects/' . $projectId . '?tab=products');
                }
            }

            // Montaj -> Gata de livrare: consumăm automat accesoriile rezervate pe piesă (Magazie)
            if ($next === 'GATA_DE_LIVRARE') {
                try {
                    self::autoConsumeMagazieOnReadyToDeliver($projectId, $ppId);
                } catch (\Throwable $e) {
                    Session::flash('toast_error', $e->getMessage());
                    Response::redirect('/projects/' . $projectId . '?tab=products');
                }
            }

            ProjectProduct::updateStatus($ppId, $next);
            $after = $before;
            $after['production_status'] = $next;
            Audit::log('PROJECT_PRODUCT_STATUS_CHANGE', 'project_products', $ppId, $before, $after, [
                'message' => 'Schimbare status piesă: ' . $old . ' → ' . $next,
                'project_id' => $projectId,
                'old_status' => $old,
                'new_status' => $next,
            ]);
            Session::flash('toast_success', 'Status piesă actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot schimba statusul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    /**
     * Best-effort: la intrarea în CNC, mută materialul piesei din Depozit în Producție.
     * - pentru 1 placă: mută 1 FULL RESERVED (Depozit -> Producție)
     * - pentru 1/2 placă: încearcă să mute 1 OFFCUT (jumătate) RESERVED; dacă nu există, mută 1 FULL RESERVED.
     */
    private static function ensureHplInProductionOnCnc(int $projectId, array $ppRow): void
    {
        $boardId = isset($ppRow['hpl_board_id']) && $ppRow['hpl_board_id'] !== null && $ppRow['hpl_board_id'] !== '' ? (int)$ppRow['hpl_board_id'] : 0;
        if ($boardId <= 0) return;
        $stype = (string)($ppRow['surface_type'] ?? '');
        $sval = isset($ppRow['surface_value']) && $ppRow['surface_value'] !== null && $ppRow['surface_value'] !== '' ? (float)$ppRow['surface_value'] : null;
        if ($stype !== 'BOARD' || $sval === null) return;
        if (!(abs($sval - 1.0) < 1e-9 || abs($sval - 0.5) < 1e-9)) return;

        $ppId = (int)($ppRow['id'] ?? 0);
        $pname = '';
        try {
            $prodId = (int)($ppRow['product_id'] ?? 0);
            if ($prodId > 0) {
                $p = Product::find($prodId);
                $pname = $p ? (string)($p['name'] ?? '') : '';
            }
        } catch (\Throwable $e) {}

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            [$hmm, $wmm] = self::boardStdDimsMm($boardId);
            $halfHmm = (int)floor(((float)$hmm) / 2.0);
            $note = 'TRANSFER_CNC · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : '');

            if (abs($sval - 0.5) < 1e-9 && $halfHmm > 0 && $wmm > 0) {
                // întâi încercăm să mutăm un rest jumătate (OFFCUT)
                try {
                    self::moveReservedOffcutHalfToLocation($pdo, $projectId, $boardId, $halfHmm, (int)$wmm, 1, 'Depozit', 'Producție', $note);
                } catch (\Throwable $e) {}
            }

            // mutăm 1 FULL RESERVED (dacă există în Depozit) – nu blocăm dacă nu există acolo
            try {
                self::moveFullBoards($boardId, 1, 'RESERVED', 'RESERVED', $note, $projectId, 'Depozit', 'Producție');
            } catch (\Throwable $e) {}

            $pdo->commit();
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
        }
    }

    /**
     * Automat: când piesa trece la "Gata de livrare", consumăm accesoriile rezervate pe ea.
     * - reserved (fără scădere stoc) -> consumed (OUT din stoc + mișcare)
     * - fără notă (cerință)
     */
    private static function autoConsumeMagazieOnReadyToDeliver(int $projectId, int $projectProductId): void
    {
        if ($projectId <= 0 || $projectProductId <= 0) return;
        $project = Project::find($projectId);
        if (!$project) throw new \RuntimeException('Proiect inexistent.');

        $rows = [];
        try {
            $rows = ProjectMagazieConsumption::reservedForProjectProduct($projectId, $projectProductId);
        } catch (\Throwable $e) {
            $rows = [];
        }
        if (!$rows) return;

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            // 1) verificăm stoc suficient per item (agregat)
            $needByItem = [];
            foreach ($rows as $r) {
                $iid = (int)($r['item_id'] ?? 0);
                $q = isset($r['qty']) ? (float)$r['qty'] : 0.0;
                if ($iid <= 0 || $q <= 0) continue;
                $needByItem[$iid] = ($needByItem[$iid] ?? 0.0) + $q;
            }
            foreach ($needByItem as $iid => $need) {
                $beforeItem = MagazieItem::findForUpdate((int)$iid);
                if (!$beforeItem) {
                    throw new \RuntimeException('Accesoriu inexistent (id=' . (int)$iid . ').');
                }
                $stock = (float)($beforeItem['stock_qty'] ?? 0.0);
                if ($need > $stock + 1e-9) {
                    $code = (string)($beforeItem['winmentor_code'] ?? '');
                    $name = (string)($beforeItem['name'] ?? '');
                    throw new \RuntimeException('Stoc insuficient pentru accesoriu: ' . trim($code . ' · ' . $name) . ' (necesar ' . number_format($need, 3, '.', '') . ', stoc ' . number_format($stock, 3, '.', '') . ').');
                }
            }

            // 2) procesăm fiecare rezervare: update mode + OUT din stoc
            foreach ($rows as $r) {
                $cid = (int)($r['id'] ?? 0);
                $iid = (int)($r['item_id'] ?? 0);
                $qty = isset($r['qty']) ? (float)$r['qty'] : 0.0;
                if ($cid <= 0 || $iid <= 0 || $qty <= 0) continue;

                $beforeRow = ProjectMagazieConsumption::find($cid);
                if (!$beforeRow) continue;
                if ((string)($beforeRow['mode'] ?? '') !== 'RESERVED') continue;

                $beforeItem = MagazieItem::findForUpdate($iid);
                if (!$beforeItem) continue;

                if (!MagazieItem::adjustStock($iid, -(float)$qty)) {
                    throw new \RuntimeException('Nu pot scădea stocul (concurență / stoc insuficient).');
                }

                ProjectMagazieConsumption::update($cid, [
                    'project_product_id' => $projectProductId,
                    'qty' => (float)$qty,
                    'unit' => (string)($beforeRow['unit'] ?? (string)($beforeItem['unit'] ?? 'buc')),
                    'mode' => 'CONSUMED',
                    'note' => null,
                ]);

                // mișcare Magazie (OUT) + log pe accesoriu
                $movementId = \App\Models\MagazieMovement::create([
                    'item_id' => $iid,
                    'direction' => 'OUT',
                    'qty' => (float)$qty,
                    'unit_price' => (isset($beforeItem['unit_price']) && $beforeItem['unit_price'] !== '' && is_numeric($beforeItem['unit_price'])) ? (float)$beforeItem['unit_price'] : null,
                    'project_id' => $projectId,
                    'project_code' => (string)($project['code'] ?? null),
                    'note' => null,
                    'created_by' => Auth::id(),
                ]);
                $afterItem = MagazieItem::findForUpdate($iid) ?: $beforeItem;
                Audit::log('MAGAZIE_OUT', 'magazie_items', $iid, $beforeItem, $afterItem, [
                    'movement_id' => $movementId,
                    'project_id' => $projectId,
                    'project_code' => (string)($project['code'] ?? ''),
                    'project_product_id' => $projectProductId,
                    'qty' => (float)$qty,
                ]);

                $afterRow = $beforeRow;
                $afterRow['mode'] = 'CONSUMED';
                $afterRow['note'] = null;
                Audit::log('PROJECT_CONSUMPTION_UPDATE', 'project_magazie_consumptions', $cid, $beforeRow, $afterRow, [
                    'message' => 'Consum Magazie auto (Gata de livrare).',
                    'project_id' => $projectId,
                    'project_product_id' => $projectProductId,
                    'item_id' => $iid,
                    'qty' => (float)$qty,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            throw $e;
        }
    }

    /**
     * Automat: când piesa trece CNC -> Montaj, consumăm din rezervările proiectului.
     * - dacă surface_type != BOARD sau surface_value nu e 1/0.5 => nu facem nimic
     * - dacă nu are hpl_board_id => nu facem nimic
     * - pentru 1 placă: trebuie să existe rezervare full (qty_boards) pe proiect
     * - pentru 1/2 placă: dacă există resturi "REST_JUMATATE" le folosim; altfel luăm 1 placă rezervată
     *   și cerem remainder_action: RETURN (rest în depozit) sau KEEP (rest rămâne rezervat ca jumătate)
     *
     * @param array<string,mixed> $ppRow (ProjectProduct::find row)
     * @return string|null error
     */
    private static function autoConsumeHplOnCncToMontaj(int $projectId, array $ppRow, string $remainderAction): ?string
    {
        $boardId = isset($ppRow['hpl_board_id']) && $ppRow['hpl_board_id'] !== null && $ppRow['hpl_board_id'] !== '' ? (int)$ppRow['hpl_board_id'] : 0;
        if ($boardId <= 0) return null;
        $stype = (string)($ppRow['surface_type'] ?? '');
        $sval = isset($ppRow['surface_value']) && $ppRow['surface_value'] !== null && $ppRow['surface_value'] !== '' ? (float)$ppRow['surface_value'] : null;
        if ($stype !== 'BOARD' || $sval === null) return null;
        if (!(abs($sval - 1.0) < 1e-9 || abs($sval - 0.5) < 1e-9)) return null;

        $ppId = (int)($ppRow['id'] ?? 0);
        $pname = '';
        $project = null;
        try {
            $prodId = (int)($ppRow['product_id'] ?? 0);
            if ($prodId > 0) {
                $p = Product::find($prodId);
                $pname = $p ? (string)($p['name'] ?? '') : '';
            }
        } catch (\Throwable $e) {}
        try {
            $project = Project::find($projectId);
        } catch (\Throwable $e) {}
        $projCode = $project ? (string)($project['code'] ?? '') : '';
        $projName = $project ? (string)($project['name'] ?? '') : '';

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            [$hmm, $wmm] = self::boardStdDimsMm($boardId);
            if ($hmm <= 0 || $wmm <= 0) throw new \RuntimeException('Dimensiuni placă lipsă.');
            $fullM2 = ((float)$hmm * (float)$wmm) / 1000000.0;
            $halfM2 = (((float)$hmm / 2.0) * (float)$wmm) / 1000000.0;
            $halfHmm = (int)floor($hmm / 2.0);

            if (abs($sval - 1.0) < 1e-9) {
                // Asigurăm materialul în Producție înainte de consum (Depozit -> Producție).
                try {
                    self::moveFullBoards($boardId, 1, 'RESERVED', 'RESERVED', 'TRANSFER_MONTAJ · piesă #' . $ppId, $projectId, 'Depozit', 'Producție');
                } catch (\Throwable $e) {}
                // 1 placă: consumăm 1 buc din rezervarea full
                if (!self::takeReservedFullBoard($pdo, $projectId, $boardId, $fullM2)) {
                    $pdo->rollBack();
                    return 'Nu există placă rezervată disponibilă în proiect pentru consum (1 placă).';
                }
                // și în stoc: RESERVED -> CONSUMED (plăci întregi)
                try {
                    self::moveFullBoards(
                        $boardId,
                        1,
                        'RESERVED',
                        'CONSUMED',
                        self::HPL_NOTE_AUTO_CONSUME . ' · 1 placă · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : ''),
                        $projectId,
                        'Producție',
                        'Producție'
                    );
                } catch (\Throwable $e) {
                    // fallback: dacă nu era în Producție, încercăm să transferăm și apoi să consumăm.
                    try {
                        self::moveFullBoards($boardId, 1, 'RESERVED', 'RESERVED', 'TRANSFER_MONTAJ · piesă #' . $ppId, $projectId, 'Depozit', 'Producție');
                        self::moveFullBoards(
                            $boardId,
                            1,
                            'RESERVED',
                            'CONSUMED',
                            self::HPL_NOTE_AUTO_CONSUME . ' · 1 placă · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : ''),
                            $projectId,
                            'Producție',
                            'Producție'
                        );
                    } catch (\Throwable $e2) {
                        $pdo->rollBack();
                        return 'Nu pot consuma placa întreagă din Producție. Verifică dacă placa rezervată a fost mutată în Producție (CNC) sau dacă există în stocul proiectului.';
                    }
                }
                self::insertProjectHplConsumption($pdo, $projectId, $boardId, 1, $fullM2, 'CONSUMED',
                    self::HPL_NOTE_AUTO_CONSUME . ' · 1 placă · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : ''), Auth::id());
                Audit::log('HPL_STOCK_CONSUME', 'hpl_boards', $boardId, null, null, [
                    'message' => 'Consum HPL auto: 1 placă (CNC → Montaj) · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : '') .
                        ' · Proiect: ' . ($projCode !== '' ? $projCode : ('#' . $projectId)) . ($projName !== '' ? (' · ' . $projName) : ''),
                    'board_id' => $boardId,
                    'project_id' => $projectId,
                    'project_code' => $projCode !== '' ? $projCode : null,
                    'project_name' => $projName !== '' ? $projName : null,
                    'project_product_id' => $ppId,
                    'product_name' => $pname !== '' ? $pname : null,
                    'qty_boards' => 1,
                    'qty_m2' => (float)$fullM2,
                    'via' => 'auto_consume_cnc_to_montaj',
                    'url_board' => \App\Core\Url::to('/stock/boards/' . $boardId),
                    'url_project' => \App\Core\Url::to('/projects/' . $projectId),
                    'url_project_consum' => \App\Core\Url::to('/projects/' . $projectId . '?tab=consum'),
                ]);
            } else {
                // 1/2 placă
                if (self::takeReservedHalfRemainder($pdo, $projectId, $boardId, $halfHmm, (int)$wmm, $halfM2)) {
                    // mutăm restul în Producție înainte de consum (Depozit -> Producție)
                    try { self::moveReservedOffcutHalfToLocation($pdo, $projectId, $boardId, $halfHmm, (int)$wmm, 1, 'Depozit', 'Producție', 'TRANSFER_MONTAJ · piesă #' . $ppId); } catch (\Throwable $e) {}
                    // consumăm 1 buc dintr-un offcut rezervat (jumătate), dacă există
                    if (!self::consumeReservedHalfOffcut($pdo, $projectId, $boardId, $halfHmm, (int)$wmm,
                        self::HPL_NOTE_AUTO_CONSUME . ' · 1/2 placă (din rest) · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : ''))) {
                        $pdo->rollBack();
                        return 'Nu pot consuma 1/2 placă din Producție. Verifică dacă restul (jumătate) a fost mutat în Producție.';
                    }
                    self::insertProjectHplConsumption($pdo, $projectId, $boardId, 0, $halfM2, 'CONSUMED',
                        self::HPL_NOTE_AUTO_CONSUME . ' · 1/2 placă (din rest) · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : ''), Auth::id());
                    Audit::log('HPL_STOCK_CONSUME', 'hpl_boards', $boardId, null, null, [
                        'message' => 'Consum HPL auto: 1/2 placă (din rest) (CNC → Montaj) · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : '') .
                            ' · Proiect: ' . ($projCode !== '' ? $projCode : ('#' . $projectId)) . ($projName !== '' ? (' · ' . $projName) : ''),
                        'board_id' => $boardId,
                        'project_id' => $projectId,
                        'project_code' => $projCode !== '' ? $projCode : null,
                        'project_name' => $projName !== '' ? $projName : null,
                        'project_product_id' => $ppId,
                        'product_name' => $pname !== '' ? $pname : null,
                        'qty_boards' => 0,
                        'qty_m2' => (float)$halfM2,
                        'via' => 'auto_consume_cnc_to_montaj',
                        'from' => 'half_remainder',
                        'url_board' => \App\Core\Url::to('/stock/boards/' . $boardId),
                        'url_project' => \App\Core\Url::to('/projects/' . $projectId),
                        'url_project_consum' => \App\Core\Url::to('/projects/' . $projectId . '?tab=consum'),
                    ]);
                } else {
                    // nu avem jumătate -> luăm 1 placă full rezervată
                    // asigurăm full-ul în Producție (Depozit -> Producție) înainte de tăiere/consum
                    try { self::moveFullBoards($boardId, 1, 'RESERVED', 'RESERVED', 'TRANSFER_MONTAJ · piesă #' . $ppId, $projectId, 'Depozit', 'Producție'); } catch (\Throwable $e) {}
                    if (!self::takeReservedFullBoard($pdo, $projectId, $boardId, $fullM2)) {
                        $pdo->rollBack();
                        return 'Nu există placă rezervată disponibilă în proiect pentru a tăia 1/2 placă.';
                    }
                    $ra = strtoupper(trim($remainderAction));
                    if ($ra !== 'RETURN' && $ra !== 'KEEP') {
                        $pdo->rollBack();
                        return 'Alege ce se întâmplă cu restul pentru 1/2 placă: RETURN (în depozit) sau KEEP (rămâne rezervat).';
                    }
                    // În stoc: tăiem 1 placă FULL rezervată în 2 bucăți OFFCUT (jumătate):
                    // - una CONSUMED (jumătatea folosită)
                    // - una AVAILABLE/RESERVED (restul, după alegere)
                    $remStatus = ($ra === 'KEEP') ? 'RESERVED' : 'AVAILABLE';
                    $remNote = self::HPL_NOTE_HALF_REMAINDER . ' · 1 buc · ' . $halfHmm . '×' . (int)$wmm . ' mm · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : '');
                    $consNote = self::HPL_NOTE_AUTO_CONSUME . ' · 1 buc · ' . $halfHmm . '×' . (int)$wmm . ' mm · piesă #' . $ppId . ' · rest=' . $ra . ($pname !== '' ? (' · ' . $pname) : '');
                    if (!self::cutOneReservedFullIntoHalves($pdo, $projectId, $boardId, $halfHmm, (int)$wmm, $remStatus, $remNote, $consNote)) {
                        $pdo->rollBack();
                        return 'Nu pot găsi o placă FULL rezervată în stoc pentru tăiere (1/2).';
                    }
                    if ($ra === 'KEEP') {
                        // păstrăm și evidența în consumurile proiectului (pentru totaluri/rapoarte)
                    self::insertProjectHplConsumption($pdo, $projectId, $boardId, 0, $halfM2, 'RESERVED', $remNote, Auth::id());
                    }
                    self::insertProjectHplConsumption($pdo, $projectId, $boardId, 0, $halfM2, 'CONSUMED',
                        $consNote, Auth::id());
                    Audit::log('HPL_STOCK_CONSUME', 'hpl_boards', $boardId, null, null, [
                        'message' => 'Consum HPL auto: 1/2 placă (tăiere din FULL) (CNC → Montaj) · piesă #' . $ppId . ($pname !== '' ? (' · ' . $pname) : '') .
                            ' · rest=' . $ra .
                            ' · Proiect: ' . ($projCode !== '' ? $projCode : ('#' . $projectId)) . ($projName !== '' ? (' · ' . $projName) : ''),
                        'board_id' => $boardId,
                        'project_id' => $projectId,
                        'project_code' => $projCode !== '' ? $projCode : null,
                        'project_name' => $projName !== '' ? $projName : null,
                        'project_product_id' => $ppId,
                        'product_name' => $pname !== '' ? $pname : null,
                        'qty_boards' => 0,
                        'qty_m2' => (float)$halfM2,
                        'via' => 'auto_consume_cnc_to_montaj',
                        'from' => 'cut_full',
                        'remainder_action' => $ra,
                        'url_board' => \App\Core\Url::to('/stock/boards/' . $boardId),
                        'url_project' => \App\Core\Url::to('/projects/' . $projectId),
                        'url_project_consum' => \App\Core\Url::to('/projects/' . $projectId . '?tab=consum'),
                    ]);
                }
            }

            $pdo->commit();
            return null;
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            throw $e;
        }
    }

    /** @return array{0:int,1:int} height_mm,width_mm */
    private static function boardStdDimsMm(int $boardId): array
    {
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $st = $pdo->prepare('SELECT std_height_mm, std_width_mm FROM hpl_boards WHERE id = ?');
        $st->execute([(int)$boardId]);
        $r = $st->fetch();
        if (!$r) return [0, 0];
        return [(int)($r['std_height_mm'] ?? 0), (int)($r['std_width_mm'] ?? 0)];
    }

    private static function insertProjectHplConsumption(\PDO $pdo, int $projectId, int $boardId, int $qtyBoards, float $qtyM2, string $mode, ?string $note, ?int $createdBy): int
    {
        $qtyM2 = max(0.0, (float)$qtyM2);
        try {
            $st = $pdo->prepare('
                INSERT INTO project_hpl_consumptions
                  (project_id, board_id, qty_boards, qty_m2, mode, note, created_by)
                VALUES
                  (:project_id, :board_id, :qty_boards, :qty_m2, :mode, :note, :created_by)
            ');
            $st->execute([
                ':project_id' => $projectId,
                ':board_id' => $boardId,
                ':qty_boards' => $qtyBoards,
                ':qty_m2' => $qtyM2,
                ':mode' => $mode,
                ':note' => ($note !== null && trim($note) !== '') ? trim($note) : null,
                ':created_by' => $createdBy,
            ]);
            return (int)$pdo->lastInsertId();
        } catch (\Throwable $e) {
            // Compat: fără qty_boards
            $st = $pdo->prepare('
                INSERT INTO project_hpl_consumptions
                  (project_id, board_id, qty_m2, mode, note, created_by)
                VALUES
                  (:project_id, :board_id, :qty_m2, :mode, :note, :created_by)
            ');
            $st->execute([
                ':project_id' => $projectId,
                ':board_id' => $boardId,
                ':qty_m2' => $qtyM2,
                ':mode' => $mode,
                ':note' => ($note !== null && trim($note) !== '') ? trim($note) : null,
                ':created_by' => $createdBy,
            ]);
            return (int)$pdo->lastInsertId();
        }
    }

    private static function takeReservedFullBoard(\PDO $pdo, int $projectId, int $boardId, float $fullM2): bool
    {
        // IMPORTANT: folosim stocul proiectului (hpl_stock_pieces), nu doar project_hpl_consumptions.
        // 1) Verificăm că există cel puțin 1 FULL rezervat în stoc pentru proiect.
        $ok = false;
        try {
            $st = $pdo->prepare("
                SELECT id
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = 'RESERVED'
                  AND qty > 0
                  AND (
                        project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                ORDER BY (location = 'Producție') DESC, created_at ASC, id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, (int)$projectId, (int)$projectId, (int)$projectId, (int)$projectId]);
            $ok = (bool)$st->fetch();
        } catch (\Throwable $e) {
            $ok = false;
        }
        if (!$ok) return false;

        // 2) Best-effort: dacă există și rânduri RESERVED în project_hpl_consumptions, le decrementăm (pentru raportări).
        try {
            $st2 = $pdo->prepare("
                SELECT id, qty_boards, qty_m2
                FROM project_hpl_consumptions
                WHERE project_id = ?
                  AND board_id = ?
                  AND mode = 'RESERVED'
                  AND COALESCE(qty_boards,0) > 0
                ORDER BY created_at ASC, id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $st2->execute([(int)$projectId, (int)$boardId]);
            $r = $st2->fetch();
            if ($r) {
                $id = (int)($r['id'] ?? 0);
                $qb = (int)($r['qty_boards'] ?? 0);
                $qm = (float)($r['qty_m2'] ?? 0.0);
                if ($id > 0 && $qb > 0) {
                    $newQb = $qb - 1;
                    $newQm = max(0.0, $qm - $fullM2);
                    $pdo->prepare('UPDATE project_hpl_consumptions SET qty_boards=?, qty_m2=? WHERE id=?')->execute([$newQb, $newQm, $id]);
                    if ($newQb <= 0 && $newQm <= 0.00001) {
                        $pdo->prepare('DELETE FROM project_hpl_consumptions WHERE id=?')->execute([$id]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return true;
    }

    private static function takeReservedHalfRemainder(\PDO $pdo, int $projectId, int $boardId, int $halfHeightMm, int $widthMm, float $halfM2): bool
    {
        // IMPORTANT: folosim stocul proiectului (hpl_stock_pieces).
        // 1) Verificăm că există un OFFCUT rezervat "jumătate" în stoc.
        if ($halfHeightMm <= 0 || $widthMm <= 0) return false;
        $ok = false;
        try {
            $st = $pdo->prepare("
                SELECT id
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'OFFCUT'
                  AND status = 'RESERVED'
                  AND width_mm = ?
                  AND height_mm = ?
                  AND qty > 0
                  AND (
                        project_id = ?
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                ORDER BY created_at ASC, id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, (int)$widthMm, (int)$halfHeightMm, (int)$projectId, (int)$projectId]);
            $ok = (bool)$st->fetch();
        } catch (\Throwable $e) {
            $ok = false;
        }
        if (!$ok) return false;

        // 2) Best-effort: decrementăm și rândul RESERVED "REST_JUMATATE" din project_hpl_consumptions (dacă există).
        try {
            $st2 = $pdo->prepare("
                SELECT id, qty_m2
                FROM project_hpl_consumptions
                WHERE project_id = ?
                  AND board_id = ?
                  AND mode = 'RESERVED'
                  AND COALESCE(qty_boards,0) = 0
                  AND note LIKE ?
                ORDER BY created_at ASC, id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $st2->execute([(int)$projectId, (int)$boardId, self::HPL_NOTE_HALF_REMAINDER . '%']);
            $r = $st2->fetch();
            if ($r) {
                $id = (int)($r['id'] ?? 0);
                $qm = (float)($r['qty_m2'] ?? 0.0);
                if ($id > 0 && $qm + 1e-9 >= $halfM2) {
                    $newQm = $qm - $halfM2;
                    if ($newQm <= 0.00001) {
                        $pdo->prepare('DELETE FROM project_hpl_consumptions WHERE id=?')->execute([$id]);
                    } else {
                        $pdo->prepare('UPDATE project_hpl_consumptions SET qty_m2=? WHERE id=?')->execute([$newQm, $id]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return true;
    }

    private static function consumeReservedHalfOffcut(\PDO $pdo, int $projectId, int $boardId, int $halfHeightMm, int $widthMm, string $noteAppend): bool
    {
        $noteAppend = trim($noteAppend);
        if ($halfHeightMm <= 0 || $widthMm <= 0) return false;
        // Căutăm un OFFCUT rezervat de dimensiune "jumătate".
        $rows = [];
        try {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'OFFCUT'
                  AND status = 'RESERVED'
                  AND width_mm = ?
                  AND height_mm = ?
                  AND location = 'Producție'
                  AND (
                        project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                  /* FULL: nu filtrăm după is_accounting (compat) */
                ORDER BY (location = 'Producție') DESC, created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, (int)$widthMm, (int)$halfHeightMm, (int)$projectId, (int)$projectId, (int)$projectId, (int)$projectId]);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'OFFCUT'
                  AND status = 'RESERVED'
                  AND width_mm = ?
                  AND height_mm = ?
                  AND location = 'Producție'
                  AND (
                        project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                ORDER BY (location = 'Producție') DESC, created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, (int)$widthMm, (int)$halfHeightMm, (int)$projectId, (int)$projectId, (int)$projectId, (int)$projectId]);
            $rows = $st->fetchAll();
        }
        if (!$rows) return false;
        $r = $rows[0];
        $id = (int)($r['id'] ?? 0);
        $rowQty = (int)($r['qty'] ?? 0);
        if ($id <= 0 || $rowQty <= 0) return false;
        $location = (string)($r['location'] ?? '');
        $isAcc = (int)($r['is_accounting'] ?? 1);
        // mutăm 1 buc în CONSUMED
        if ($rowQty === 1) {
            HplStockPiece::updateFields($id, ['status' => 'CONSUMED', 'project_id' => $projectId]);
            if ($noteAppend !== '') HplStockPiece::appendNote($id, $noteAppend);
        } else {
            HplStockPiece::updateQty($id, $rowQty - 1);
            $ident = null;
            try { $ident = HplStockPiece::findIdentical($boardId, 'OFFCUT', 'CONSUMED', $widthMm, $halfHeightMm, $location, $isAcc, $projectId); } catch (\Throwable $e) {}
            if ($ident) {
                HplStockPiece::incrementQty((int)$ident['id'], 1);
                if ($noteAppend !== '') HplStockPiece::appendNote((int)$ident['id'], $noteAppend);
            } else {
                HplStockPiece::create([
                    'board_id' => $boardId,
                    'project_id' => $projectId,
                    'is_accounting' => $isAcc,
                    'piece_type' => 'OFFCUT',
                    'status' => 'CONSUMED',
                    'width_mm' => $widthMm,
                    'height_mm' => $halfHeightMm,
                    'qty' => 1,
                    'location' => $location,
                    'notes' => $noteAppend !== '' ? $noteAppend : null,
                ]);
            }
        }
        return true;
    }

    private static function cutOneReservedFullIntoHalves(
        \PDO $pdo,
        int $projectId,
        int $boardId,
        int $halfHeightMm,
        int $widthMm,
        string $remainderStatus,
        string $remainderNote,
        string $consumedNote
    ): bool {
        if ($boardId <= 0 || $halfHeightMm <= 0 || $widthMm <= 0) return false;
        $remainderStatus = strtoupper(trim($remainderStatus));
        if (!in_array($remainderStatus, ['AVAILABLE', 'RESERVED'], true)) $remainderStatus = 'AVAILABLE';

        // Luăm 1 buc dintr-un FULL rezervat (stoc) și îl înlocuim cu 2 OFFCUT-uri.
        $rows = [];
        try {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = 'RESERVED'
                  AND location = 'Producție'
                  AND (
                        project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                  /* FULL: nu filtrăm după is_accounting (compat) */
                ORDER BY (location = 'Producție') DESC, created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, (int)$projectId, (int)$projectId, (int)$projectId, (int)$projectId]);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'FULL'
                  AND status = 'RESERVED'
                  AND location = 'Producție'
                  AND (
                        project_id = ?
                        OR (project_id IS NULL AND (notes LIKE CONCAT('%Proiect ', ?, '%') OR notes LIKE CONCAT('%proiect ', ?, '%')))
                        OR EXISTS (
                            SELECT 1
                            FROM project_hpl_consumptions c
                            WHERE c.project_id = ?
                              AND hpl_stock_pieces.notes LIKE CONCAT('%consum HPL #', c.id, '%')
                        )
                  )
                ORDER BY (location = 'Producție') DESC, created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([(int)$boardId, (int)$projectId, (int)$projectId, (int)$projectId, (int)$projectId]);
            $rows = $st->fetchAll();
        }
        if (!$rows) return false;
        $src = $rows[0];
        $id = (int)($src['id'] ?? 0);
        $rowQty = (int)($src['qty'] ?? 0);
        if ($id <= 0 || $rowQty <= 0) return false;
        $location = (string)($src['location'] ?? '');
        $isAcc = (int)($src['is_accounting'] ?? 1);

        // Scoatem 1 buc din FULL rezervat
        if ($rowQty === 1) HplStockPiece::delete($id);
        else HplStockPiece::updateQty($id, $rowQty - 1);

        // 1) Consumăm jumătatea (OFFCUT CONSUMED)
        $identC = null;
        try { $identC = HplStockPiece::findIdentical($boardId, 'OFFCUT', 'CONSUMED', $widthMm, $halfHeightMm, $location, $isAcc, $projectId); } catch (\Throwable $e) {}
        if ($identC) {
            HplStockPiece::incrementQty((int)$identC['id'], 1);
            if (trim($consumedNote) !== '') HplStockPiece::appendNote((int)$identC['id'], $consumedNote);
        } else {
            HplStockPiece::create([
                'board_id' => $boardId,
                'project_id' => $projectId,
                'is_accounting' => $isAcc,
                'piece_type' => 'OFFCUT',
                'status' => 'CONSUMED',
                'width_mm' => $widthMm,
                'height_mm' => $halfHeightMm,
                'qty' => 1,
                'location' => $location,
                'notes' => trim($consumedNote) !== '' ? trim($consumedNote) : null,
            ]);
        }

        // 2) Restul jumătății (OFFCUT AVAILABLE/RESERVED)
        $remProjectId = ($remainderStatus === 'AVAILABLE') ? null : $projectId;
        $remLocation = ($remainderStatus === 'AVAILABLE') ? 'Depozit' : $location;
        $identR = null;
        try { $identR = HplStockPiece::findIdentical($boardId, 'OFFCUT', $remainderStatus, $widthMm, $halfHeightMm, $remLocation, $isAcc, $remProjectId); } catch (\Throwable $e) {}
        if ($identR) {
            HplStockPiece::incrementQty((int)$identR['id'], 1);
            if (trim($remainderNote) !== '') HplStockPiece::appendNote((int)$identR['id'], $remainderNote);
            try { HplStockPiece::updateFields((int)$identR['id'], ['project_id' => $remProjectId]); } catch (\Throwable $e) {}
        } else {
            HplStockPiece::create([
                'board_id' => $boardId,
                'project_id' => $remProjectId,
                'is_accounting' => $isAcc,
                'piece_type' => 'OFFCUT',
                'status' => $remainderStatus,
                'width_mm' => $widthMm,
                'height_mm' => $halfHeightMm,
                'qty' => 1,
                'location' => $remLocation,
                'notes' => trim($remainderNote) !== '' ? trim($remainderNote) : null,
            ]);
        }
        return true;
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

    /**
     * Add accessories for a specific project product:
     * - always RESERVED
     * - linked to project_product_id
     * - no note / no mode selection
     */
    public static function addMagazieConsumptionForProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }
        $pp = ProjectProduct::find($ppId);
        if (!$pp || (int)($pp['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Produs proiect invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $itemId = Validator::int(trim((string)($_POST['item_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        if ($itemId === null || $qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Consum invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $item = MagazieItem::find((int)$itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }
        $unit = trim((string)($item['unit'] ?? 'buc'));

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
                'mode' => 'RESERVED',
                'note' => null,
                'created_by' => Auth::id(),
            ]);
            $pdo->commit();

            Audit::log('PROJECT_CONSUMPTION_CREATE', 'project_magazie_consumptions', $cid, null, null, [
                'message' => 'Accesoriu rezervat pe piesă: ' . (string)($item['winmentor_code'] ?? '') . ' · ' . (string)($item['name'] ?? ''),
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'item_id' => (int)$itemId,
                'qty' => (float)$qty,
                'unit' => $unit,
                'mode' => 'RESERVED',
            ]);
            Session::flash('toast_success', 'Accesoriu rezervat pe piesă.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva accesoriul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
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
            self::moveFullBoards((int)$boardId, (int)$qtyBoards, 'AVAILABLE', $target, $noteAppend, $projectId);

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
                    self::moveFullBoards((int)($before['board_id'] ?? 0), $qtyBoards, $from, 'AVAILABLE', 'Anulare consum HPL #' . $cid, $projectId);
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

                // update status based on delivered (doar livrat complet)
                $newStatus = (string)($pp['production_status'] ?? 'CREAT');
                if ($newDelivered >= $totalQty - 1e-9) $newStatus = 'LIVRAT';

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

