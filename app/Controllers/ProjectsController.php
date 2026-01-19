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
use App\Models\ClientAddress;
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
use App\Models\ProjectProductHplConsumption;
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

    private static function dtToTs(?string $dt): ?int
    {
        $dt = $dt !== null ? trim($dt) : '';
        if ($dt === '') return null;
        $t = strtotime($dt);
        return $t !== false ? (int)$t : null;
    }

    private static function boolFromPost(string $key, int $default = 1): int
    {
        if (!array_key_exists($key, $_POST)) return $default ? 1 : 0;
        $val = $_POST[$key];
        if (is_array($val)) {
            foreach ($val as $v) {
                $s = strtolower(trim((string)$v));
                if (in_array($s, ['1', 'true', 'on', 'da', 'yes'], true)) return 1;
            }
            return 0;
        }
        $s = strtolower(trim((string)$val));
        return in_array($s, ['1', 'true', 'on', 'da', 'yes'], true) ? 1 : 0;
    }

    private static function includeInDevizFromPost(int $default = 1): int
    {
        if (array_key_exists('include_in_deviz_flag', $_POST)) return 1;
        if (array_key_exists('include_in_deviz', $_POST)) return self::boolFromPost('include_in_deviz', $default);
        return $default ? 1 : 0;
    }

    private static function formatLabel(string $code, string $name, string $fallback): string
    {
        $code = trim($code);
        $name = trim($name);
        if ($code !== '' && $name !== '') return $code . ' · ' . $name;
        if ($code !== '') return $code;
        if ($name !== '') return $name;
        return $fallback;
    }

    private static function projectLabel(?array $project): string
    {
        if (!$project) return 'Proiect';
        return self::formatLabel((string)($project['code'] ?? ''), (string)($project['name'] ?? ''), 'Proiect');
    }

    private static function productLabelFromRow(?array $product): string
    {
        if (!$product) return 'Produs';
        return self::formatLabel((string)($product['code'] ?? ''), (string)($product['name'] ?? ''), 'Produs');
    }

    private static function productLabelFromProjectProduct(?array $pp): string
    {
        if (!$pp) return 'Produs';
        $productId = (int)($pp['product_id'] ?? 0);
        if ($productId <= 0) return 'Produs';
        $product = Product::find($productId);
        return self::productLabelFromRow($product);
    }

    private static function isFinalProductStatus(string $st): bool
    {
        $st = strtoupper(trim($st));
        return in_array($st, ['GATA_DE_LIVRARE', 'AVIZAT', 'LIVRAT'], true);
    }

    /**
     * @param array<int, array<string,mixed>> $projectProducts
     * @return array<int, array{qty:float,finalized_ts:?int}>
     */
    private static function productMetaForAlloc(array $projectProducts): array
    {
        $out = [];
        foreach ($projectProducts as $pp) {
            $id = (int)($pp['id'] ?? 0);
            if ($id <= 0) continue;
            $qty = (float)($pp['qty'] ?? 0);
            $qty = $qty > 0 ? $qty : 0.0;
            $finalTs = self::dtToTs(isset($pp['finalized_at']) ? (string)$pp['finalized_at'] : null);
            $out[$id] = ['qty' => $qty, 'finalized_ts' => $finalTs];
        }
        return $out;
    }

    /** @param array<int, array{qty:float,finalized_ts:?int}> $meta */
    private static function weightsForIds(array $meta, array $ids): array
    {
        $sum = 0.0;
        foreach ($ids as $id) $sum += (float)($meta[$id]['qty'] ?? 0.0);
        $n = count($ids);
        $w = [];
        foreach ($ids as $id) {
            if ($sum > 0.0) $w[$id] = ((float)($meta[$id]['qty'] ?? 0.0)) / $sum;
            elseif ($n > 0) $w[$id] = 1.0 / $n;
            else $w[$id] = 0.0;
        }
        return $w;
    }

    /** @param array<int, array{qty:float,finalized_ts:?int}> $meta */
    private static function eligibleIdsForEvent(array $meta, ?int $eventTs): array
    {
        $ids = [];
        foreach ($meta as $id => $m) {
            $fin = $m['finalized_ts'] ?? null;
            // dacă nu știm data evenimentului, nu influențăm piesele finalizate (doar active)
            if ($eventTs === null) {
                if ($fin === null) $ids[] = (int)$id;
                continue;
            }
            if ($fin === null || $fin >= $eventTs) $ids[] = (int)$id;
        }
        return $ids;
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
        // Cerință: după Gata de livrare, piesa nu mai e influențată de manopere/proiect-level create ulterior.
        $meta = self::productMetaForAlloc($projectProducts);
        $ppIds = array_keys($meta);
        $validPp = array_fill_keys($ppIds, true);

        $direct = [];
        $projShares = []; // per ppId: cnc/atelier share from project-level
        foreach ($ppIds as $ppId) {
            $projShares[$ppId] = ['cnc_hours' => 0.0, 'cnc_cost' => 0.0, 'atelier_hours' => 0.0, 'atelier_cost' => 0.0];
        }

        foreach ($workLogs as $w) {
            $he = isset($w['hours_estimated']) && $w['hours_estimated'] !== null && $w['hours_estimated'] !== '' ? (float)$w['hours_estimated'] : 0.0;
            if ($he <= 0) continue;
            $cph = isset($w['cost_per_hour']) && $w['cost_per_hour'] !== null && $w['cost_per_hour'] !== '' ? (float)$w['cost_per_hour'] : null;
            if ($cph === null || $cph < 0 || !is_finite($cph)) continue;
            $cost = $he * $cph;
            $type = (string)($w['work_type'] ?? '');
            $logTs = self::dtToTs(isset($w['created_at']) ? (string)$w['created_at'] : null);

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
                continue;
            }

            // Project-level -> distribuim doar către piesele eligibile la momentul logului
            $eligible = self::eligibleIdsForEvent($meta, $logTs);
            if (!$eligible) continue;
            $weights = self::weightsForIds($meta, $eligible);
            foreach ($eligible as $pid) {
                $wgt = (float)($weights[$pid] ?? 0.0);
                if ($wgt <= 0) continue;
                if ($type === 'CNC') {
                    $projShares[$pid]['cnc_hours'] += $he * $wgt;
                    $projShares[$pid]['cnc_cost'] += $cost * $wgt;
                } elseif ($type === 'ATELIER') {
                    $projShares[$pid]['atelier_hours'] += $he * $wgt;
                    $projShares[$pid]['atelier_cost'] += $cost * $wgt;
                }
            }
        }

        $out = [];
        foreach ($ppIds as $ppId) {
            $qty = (float)($meta[$ppId]['qty'] ?? 0.0);
            $cncH = ($direct[$ppId]['cnc_hours'] ?? 0.0) + ($projShares[$ppId]['cnc_hours'] ?? 0.0);
            $cncC = ($direct[$ppId]['cnc_cost'] ?? 0.0) + ($projShares[$ppId]['cnc_cost'] ?? 0.0);
            $atH = ($direct[$ppId]['atelier_hours'] ?? 0.0) + ($projShares[$ppId]['atelier_hours'] ?? 0.0);
            $atC = ($direct[$ppId]['atelier_cost'] ?? 0.0) + ($projShares[$ppId]['atelier_cost'] ?? 0.0);
            $out[$ppId] = [
                'qty' => $qty,
                'cnc_hours' => $cncH,
                'cnc_cost' => $cncC,
                'atelier_hours' => $atH,
                'atelier_cost' => $atC,
                'total_cost' => $cncC + $atC,
                'cnc_rate' => $cncH > 0 ? ($cncC / $cncH) : 0.0,
                'atelier_rate' => $atH > 0 ? ($atC / $atH) : 0.0,
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
        // Cerință: după Gata de livrare, piesa nu mai e influențată de consumuri/proiect-level create ulterior.
        $meta = self::productMetaForAlloc($projectProducts);
        $ppIds = array_keys($meta);
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

            $evtTs = self::dtToTs(isset($c['created_at']) ? (string)$c['created_at'] : null);
            $ppId = isset($c['project_product_id']) && $c['project_product_id'] !== null && $c['project_product_id'] !== ''
                ? (int)$c['project_product_id']
                : 0;
            if ($ppId > 0 && isset($out[$ppId])) {
                $out[$ppId]['mag_cost'] += $cost;
                continue;
            }

            // nivel proiect -> distribuim doar către piesele eligibile la momentul consumului
            $eligible = self::eligibleIdsForEvent($meta, $evtTs);
            if (!$eligible) continue;
            $weights = self::weightsForIds($meta, $eligible);
            foreach ($eligible as $pid) {
                $wgt = (float)($weights[$pid] ?? 0.0);
                if ($wgt <= 0) continue;
                $out[$pid]['mag_cost'] += ($cost * $wgt);
            }
        }
        return $out;
    }

    /**
     * Accesorii (cantitativ) pe produs pentru afișarea în cardul "Consum".
     * - consumurile legate direct de produs rămân pe produs
     * - consumurile la nivel de proiect se distribuie pe bucăți (qty) doar către piesele eligibile la momentul consumului
     *
     * @return array<int, array<int, array{item_id:int,mode:string,src:string,code:string,name:string,unit:string,qty:float,unit_price:?float}>>
     */
    private static function accessoriesByProductForDisplay(array $projectProducts, array $magConsum): array
    {
        $meta = self::productMetaForAlloc($projectProducts);
        $ppIds = array_keys($meta);
        $valid = array_fill_keys($ppIds, true);
        $out = [];
        foreach ($ppIds as $ppId) $out[$ppId] = [];

        foreach ($magConsum as $c) {
            $qty = isset($c['qty']) ? (float)$c['qty'] : 0.0;
            if ($qty <= 0) continue;
            $evtTs = self::dtToTs(isset($c['created_at']) ? (string)$c['created_at'] : null);
            $unit = (string)($c['unit'] ?? '');
            $mode = (string)($c['mode'] ?? '');
            $iid = (int)($c['item_id'] ?? 0);
            if ($iid <= 0) continue;
            $code = (string)($c['winmentor_code'] ?? '');
            $name = (string)($c['item_name'] ?? '');
            $up = (isset($c['item_unit_price']) && $c['item_unit_price'] !== null && $c['item_unit_price'] !== '' && is_numeric($c['item_unit_price']))
                ? (float)$c['item_unit_price']
                : null;

            $includeInDeviz = isset($c['include_in_deviz']) ? (int)$c['include_in_deviz'] : 1;
            $ppId = isset($c['project_product_id']) && $c['project_product_id'] !== null && $c['project_product_id'] !== ''
                ? (int)$c['project_product_id']
                : 0;
            if ($ppId > 0 && isset($valid[$ppId])) {
                $key = $iid . '|' . $mode . '|DIRECT|' . $includeInDeviz;
                if (!isset($out[$ppId][$key])) {
                    $out[$ppId][$key] = [
                        'item_id' => $iid,
                        'mode' => $mode,
                        'src' => 'DIRECT',
                        'code' => $code,
                        'name' => $name,
                        'unit' => $unit,
                        'qty' => 0.0,
                        'unit_price' => $up,
                        'include_in_deviz' => $includeInDeviz,
                    ];
                }
                $out[$ppId][$key]['qty'] += $qty;
                continue;
            }

            // nivel proiect -> distribuim către piesele eligibile
            $eligible = self::eligibleIdsForEvent($meta, $evtTs);
            if (!$eligible) continue;
            $weights = self::weightsForIds($meta, $eligible);
            foreach ($eligible as $pid) {
                $wgt = (float)($weights[$pid] ?? 0.0);
                if ($wgt <= 0) continue;
                $allocQty = $qty * $wgt;
                if ($allocQty <= 0) continue;
                $key = $iid . '|' . $mode . '|PROIECT|' . $includeInDeviz;
                if (!isset($out[$pid][$key])) {
                    $out[$pid][$key] = [
                        'item_id' => $iid,
                        'mode' => $mode,
                        'src' => 'PROIECT',
                        'code' => $code,
                        'name' => $name,
                        'unit' => $unit,
                        'qty' => 0.0,
                        'unit_price' => $up,
                        'include_in_deviz' => $includeInDeviz,
                    ];
                }
                $out[$pid][$key]['qty'] += $allocQty;
            }
        }

        // normalize arrays
        $res = [];
        foreach ($out as $ppId => $rows) {
            $res[$ppId] = array_values($rows);
        }
        return $res;
    }

    private static function fmtQty(float $qty, int $dec = 3): string
    {
        $txt = number_format($qty, $dec, '.', '');
        $txt = rtrim(rtrim($txt, '0'), '.');
        return $txt !== '' ? $txt : '0';
    }

    private static function fmtMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private static function nextDocNumber(string $key, int $startAt = 10000): int
    {
        $key = trim($key);
        if ($key === '') {
            throw new \RuntimeException('Cheie document invalidă.');
        }
        $startAt = max(1, (int)$startAt);
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT value FROM app_settings WHERE `key` = ? FOR UPDATE');
            $st->execute([$key]);
            $row = $st->fetch();
            $last = ($row && isset($row['value']) && is_numeric($row['value'])) ? (int)$row['value'] : 0;
            $next = $last >= $startAt ? ($last + 1) : $startAt;
            if ($row) {
                $st2 = $pdo->prepare('UPDATE app_settings SET value = ?, updated_by = ? WHERE `key` = ?');
                $st2->execute([(string)$next, Auth::id(), $key]);
            } else {
                $st2 = $pdo->prepare('INSERT INTO app_settings (`key`, value, updated_by) VALUES (?,?,?)');
                $st2->execute([$key, (string)$next, Auth::id()]);
            }
            $pdo->commit();
            return $next;
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            throw $e;
        }
    }

    /** @return array<string,string> */
    private static function companySettingsForDocs(): array
    {
        $keys = [
            'company_name',
            'company_cui',
            'company_reg',
            'company_address',
            'company_phone',
            'company_email',
            'company_contact_name',
            'company_contact_phone',
            'company_contact_email',
            'company_contact_position',
            'company_logo_url',
            'company_logo_thumb_url',
        ];
        $vals = AppSetting::getMany($keys);
        $name = trim((string)($vals['company_name'] ?? ''));
        if ($name === '') $name = 'HPL Manager';
        return [
            'name' => $name,
            'cui' => trim((string)($vals['company_cui'] ?? '')),
            'reg' => trim((string)($vals['company_reg'] ?? '')),
            'address' => trim((string)($vals['company_address'] ?? '')),
            'phone' => trim((string)($vals['company_phone'] ?? '')),
            'email' => trim((string)($vals['company_email'] ?? '')),
            'contact_name' => trim((string)($vals['company_contact_name'] ?? '')),
            'contact_phone' => trim((string)($vals['company_contact_phone'] ?? '')),
            'contact_email' => trim((string)($vals['company_contact_email'] ?? '')),
            'contact_position' => trim((string)($vals['company_contact_position'] ?? '')),
            'logo_url' => trim((string)($vals['company_logo_url'] ?? '')),
            'logo_thumb' => trim((string)($vals['company_logo_thumb_url'] ?? '')),
        ];
    }

    /**
     * @param array<int, array{item_id:int,mode:string,src:string,code:string,name:string,unit:string,qty:float,unit_price:?float}> $rows
     * @return array<int, array{item_id:int,code:string,name:string,unit:string,qty:float,unit_price:?float}>
     */
    private static function aggregateAccessories(array $rows, ?string $mode = null): array
    {
        $out = [];
        $mode = $mode !== null ? strtoupper($mode) : null;
        foreach ($rows as $r) {
            $rowMode = strtoupper((string)($r['mode'] ?? ''));
            if ($mode !== null && $rowMode !== $mode) continue;
            $itemId = (int)($r['item_id'] ?? 0);
            if ($itemId <= 0) continue;
            $qty = isset($r['qty']) ? (float)($r['qty'] ?? 0) : 0.0;
            if ($qty <= 0) continue;
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $unit = trim((string)($r['unit'] ?? ''));
            $up = (isset($r['unit_price']) && $r['unit_price'] !== null && $r['unit_price'] !== '' && is_numeric($r['unit_price']))
                ? (float)$r['unit_price']
                : null;
            if (!isset($out[$itemId])) {
                $out[$itemId] = [
                    'item_id' => $itemId,
                    'code' => $code,
                    'name' => $name,
                    'unit' => $unit,
                    'qty' => 0.0,
                    'unit_price' => $up,
                ];
            }
            $out[$itemId]['qty'] += $qty;
            if ($out[$itemId]['code'] === '' && $code !== '') $out[$itemId]['code'] = $code;
            if ($out[$itemId]['name'] === '' && $name !== '') $out[$itemId]['name'] = $name;
            if ($out[$itemId]['unit'] === '' && $unit !== '') $out[$itemId]['unit'] = $unit;
            if (($out[$itemId]['unit_price'] ?? null) === null && $up !== null) {
                $out[$itemId]['unit_price'] = $up;
            }
        }
        return array_values($out);
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     * @return array<int, array{board_code:string,board_name:string,piece_type:string,width_mm:int,height_mm:int,qty:int,display_width_mm:int,display_height_mm:int,display_qty:float,unit_price:?float,total_price:?float}>
     */
    private static function aggregateConsumedHpl(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ((string)($r['status'] ?? '') !== 'CONSUMED') continue;
            $pt = (string)($r['consumed_piece_type'] ?? '');
            $pw = (int)($r['consumed_piece_width_mm'] ?? 0);
            $ph = (int)($r['consumed_piece_height_mm'] ?? 0);
            $qty = (int)($r['consumed_piece_qty'] ?? 0);
            $usedFallback = false;
            if ($pt === '' && $pw === 0 && $ph === 0) {
                $usedFallback = true;
                $pt = (string)($r['piece_type'] ?? '');
                $pw = (int)($r['piece_width_mm'] ?? 0);
                $ph = (int)($r['piece_height_mm'] ?? 0);
                $qty = (int)($r['piece_qty'] ?? 0);
            }
            if ($usedFallback) {
                $pieceStatus = (string)($r['piece_status'] ?? '');
                if ($pieceStatus !== 'CONSUMED') {
                    $qty = 1;
                }
            }
            if ($qty <= 0) $qty = 1;
            $bcode = trim((string)($r['board_code'] ?? ''));
            $bname = trim((string)($r['board_name'] ?? ''));
            $boardSale = (isset($r['board_sale_price']) && $r['board_sale_price'] !== null && $r['board_sale_price'] !== '' && is_numeric($r['board_sale_price']))
                ? (float)$r['board_sale_price']
                : null;
            $stdW = (int)($r['board_std_width_mm'] ?? 0);
            $stdH = (int)($r['board_std_height_mm'] ?? 0);
            $boardArea = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
            $pieceArea = ($pw > 0 && $ph > 0) ? (($pw * $ph) / 1000000.0) : 0.0;
            $consumeMode = strtoupper((string)($r['consume_mode'] ?? ''));
            $isHalf = $consumeMode === 'HALF';
            if (!$isHalf && $boardArea > 0.0 && $pieceArea > 0.0) {
                $isHalf = abs(($pieceArea * 2.0) - $boardArea) < 0.0001;
            }
            $displayW = $pw;
            $displayH = $ph;
            $displayQty = (float)$qty;
            if ($isHalf && $stdW > 0 && $stdH > 0) {
                $displayW = $stdW;
                $displayH = $stdH;
                $displayQty = 0.5 * (float)$qty;
            }

            $unitPrice = null;
            if ($boardSale !== null && $boardArea > 0.0 && $pieceArea > 0.0) {
                $unitPrice = $isHalf ? $boardSale : (($boardSale / $boardArea) * $pieceArea);
            }
            $key = $bcode . '|' . $bname . '|' . $pt . '|' . $pw . '|' . $ph;
            if (!isset($out[$key])) {
                $out[$key] = [
                    'board_code' => $bcode,
                    'board_name' => $bname,
                    'piece_type' => $pt,
                    'width_mm' => $pw,
                    'height_mm' => $ph,
                    'qty' => 0,
                    'display_width_mm' => $displayW,
                    'display_height_mm' => $displayH,
                    'display_qty' => 0.0,
                    'unit_price' => $unitPrice,
                    'total_price' => null,
                ];
            }
            $out[$key]['qty'] += $qty;
            $out[$key]['display_qty'] += $displayQty;
            if ($out[$key]['unit_price'] === null && $unitPrice !== null) {
                $out[$key]['unit_price'] = $unitPrice;
            }
        }
        foreach ($out as $k => $row) {
            $up = $row['unit_price'];
            if ($up !== null) {
                $out[$k]['total_price'] = (float)$up * (float)($row['display_qty'] ?? (int)($row['qty'] ?? 0));
            }
        }
        return array_values($out);
    }

    /**
     * @return array{stored_name:string,size_bytes:int,mime:string}
     */
    private static function saveHtmlDocument(string $storedName, string $html): array
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]+/', '_', $storedName) ?? 'document.html';
        if (!str_ends_with(strtolower($safe), '.html')) {
            $safe .= '.html';
        }
        $dir = dirname(__DIR__, 2) . '/storage/uploads/files';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Nu pot crea directorul de documente.');
        }
        $path = $dir . '/' . $safe;
        $suffix = 0;
        while (is_file($path)) {
            $suffix++;
            $path = $dir . '/' . preg_replace('/\.html$/', '', $safe) . '-' . $suffix . '.html';
        }
        if (file_put_contents($path, $html) === false) {
            throw new \RuntimeException('Nu pot salva documentul pe server.');
        }
        $size = filesize($path);
        return [
            'stored_name' => basename($path),
            'size_bytes' => $size !== false ? (int)$size : 0,
            'mime' => 'text/html',
        ];
    }

    /** @param array<string,mixed> $ctx */
    private static function renderDevizHtml(array $ctx): string
    {
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $company = $ctx['company'] ?? [];
        $client = $ctx['client'] ?? [];
        $delivery = $ctx['delivery'] ?? [];
        $product = $ctx['product'] ?? [];
        $accessories = is_array($ctx['accessories'] ?? null) ? $ctx['accessories'] : [];
        $docNumber = (string)($ctx['doc_number'] ?? '');
        $docDate = (string)($ctx['doc_date'] ?? '');
        $projectLabel = (string)($ctx['project_label'] ?? '');
        $avizNumber = trim((string)($ctx['aviz_number'] ?? ''));
        $avizDate = trim((string)($ctx['aviz_date'] ?? ''));
        $qty = (float)($product['qty'] ?? 0.0);
        $unit = (string)($product['unit'] ?? '');
        $accLines = [];
        foreach ($accessories as $a) {
            $name = trim((string)($a['name'] ?? ''));
            $code = trim((string)($a['code'] ?? ''));
            $qtyA = isset($a['qty']) ? (float)($a['qty'] ?? 0) : 0.0;
            if ($qtyA <= 0) continue;
            $unitA = trim((string)($a['unit'] ?? ''));
            $label = $code !== '' ? ($code . ' · ' . ($name !== '' ? $name : 'Accesoriu')) : ($name !== '' ? $name : 'Accesoriu');
            $accLines[] = $label . ' — ' . self::fmtQty($qtyA) . ($unitA !== '' ? (' ' . $unitA) : '');
        }
        $logo = (string)($company['logo_url'] ?? '');
        if ($logo === '' && isset($company['logo_thumb'])) $logo = (string)$company['logo_thumb'];

        ob_start();
        ?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Deviz <?= $esc($docNumber) ?></title>
  <style>
    body { font-family: Arial, sans-serif; color:#111; font-size:13px; margin:24px; }
    h1 { font-size:20px; margin:0 0 6px; }
    .muted { color:#666; }
    .header { display:flex; justify-content:space-between; gap:24px; }
    .company-name { font-weight:700; font-size:16px; }
    .doc-title { font-size:18px; font-weight:700; }
    .box { border:1px solid #ddd; padding:10px 12px; border-radius:6px; }
    .grid { display:flex; gap:16px; margin-top:14px; }
    .grid > div { flex:1; }
    table { width:100%; border-collapse:collapse; margin-top:16px; }
    th, td { border:1px solid #ddd; padding:8px; vertical-align:top; }
    th { background:#f6f7f8; text-align:left; font-size:12px; }
    .small { font-size:12px; }
    .title { font-weight:700; }
    .total { margin-top:12px; text-align:right; font-weight:700; font-size:14px; }
    .logo { max-height:50px; margin-bottom:8px; }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <?php if ($logo !== ''): ?>
        <img src="<?= $esc($logo) ?>" class="logo" alt="Logo">
      <?php endif; ?>
      <div class="company-name"><?= $esc($company['name'] ?? '') ?></div>
      <div class="muted small">
        <?php if (!empty($company['cui'])): ?>CUI: <?= $esc($company['cui']) ?><?php endif; ?>
        <?php if (!empty($company['reg'])): ?> · Reg. Com.: <?= $esc($company['reg']) ?><?php endif; ?>
      </div>
      <?php if (!empty($company['address'])): ?>
        <div class="small"><?= $esc($company['address']) ?></div>
      <?php endif; ?>
      <div class="small">
        <?php if (!empty($company['phone'])): ?>Tel: <?= $esc($company['phone']) ?><?php endif; ?>
        <?php if (!empty($company['email'])): ?> · Email: <?= $esc($company['email']) ?><?php endif; ?>
      </div>
      <?php if (!empty($company['contact_name']) || !empty($company['contact_phone']) || !empty($company['contact_email'])): ?>
        <div class="small muted">
          Contact: <?= $esc(trim((string)($company['contact_name'] ?? '') . (isset($company['contact_position']) && $company['contact_position'] !== '' ? (' · ' . $company['contact_position']) : ''))) ?>
          <?php if (!empty($company['contact_phone'])): ?> · <?= $esc($company['contact_phone']) ?><?php endif; ?>
          <?php if (!empty($company['contact_email'])): ?> · <?= $esc($company['contact_email']) ?><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="box">
      <div class="doc-title">Deviz nr. <?= $esc($docNumber) ?></div>
      <div class="small">Data: <?= $esc($docDate) ?></div>
      <?php if ($projectLabel !== ''): ?>
        <div class="small">Proiect: <?= $esc($projectLabel) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <div class="title">Facturare</div>
      <div><?= $esc($client['name'] ?? '') ?></div>
      <?php if (!empty($client['cui'])): ?><div class="small">CUI: <?= $esc($client['cui']) ?></div><?php endif; ?>
      <?php if (!empty($client['address'])): ?><div class="small"><?= $esc($client['address']) ?></div><?php endif; ?>
      <?php if (!empty($client['contact_person'])): ?><div class="small">Contact: <?= $esc($client['contact_person']) ?></div><?php endif; ?>
      <div class="small">
        <?php if (!empty($client['phone'])): ?>Tel: <?= $esc($client['phone']) ?><?php endif; ?>
        <?php if (!empty($client['email'])): ?> · Email: <?= $esc($client['email']) ?><?php endif; ?>
      </div>
    </div>
    <div class="box">
      <div class="title">Livrare</div>
      <div><?= $esc($delivery['label'] ?? '') ?></div>
      <?php if (!empty($delivery['address'])): ?><div class="small"><?= $esc($delivery['address']) ?></div><?php endif; ?>
      <?php if (!empty($delivery['notes'])): ?><div class="small muted"><?= $esc($delivery['notes']) ?></div><?php endif; ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Produs / descriere</th>
        <th style="width:140px">Cantitate</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <div class="title"><?= $esc($product['name'] ?? '') ?></div>
          <?php if (!empty($product['description'])): ?>
            <div class="small"><?= $esc($product['description']) ?></div>
          <?php endif; ?>
          <?php if ($accLines): ?>
            <div class="small muted" style="margin-top:6px;">Accesorii incluse:</div>
            <div class="small">
              <?php foreach ($accLines as $line): ?>
                <div><?= $esc($line) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </td>
        <td><?= $esc(self::fmtQty($qty)) ?><?= $unit !== '' ? (' ' . $esc($unit)) : '' ?></td>
      </tr>
    </tbody>
  </table>
  <?php if ($avizNumber !== '' || $avizDate !== ''): ?>
    <div class="small muted" style="margin-top:6px;">
      <?php if ($avizNumber !== ''): ?>Număr aviz: <?= $esc($avizNumber) ?><?php endif; ?>
      <?php if ($avizDate !== ''): ?><?= $avizNumber !== '' ? ' · ' : '' ?>Data aviz: <?= $esc($avizDate) ?><?php endif; ?>
    </div>
  <?php endif; ?>
</body>
</html>
        <?php
        return (string)ob_get_clean();
    }

    /** @param array<string,mixed> $ctx */
    private static function renderBonConsumHtml(array $ctx): string
    {
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $company = $ctx['company'] ?? [];
        $product = $ctx['product'] ?? [];
        $docNumber = (string)($ctx['doc_number'] ?? '');
        $docDate = (string)($ctx['doc_date'] ?? '');
        $projectLabel = (string)($ctx['project_label'] ?? '');
        $avizNumber = trim((string)($ctx['aviz_number'] ?? ''));
        $hplRows = is_array($ctx['hpl_rows'] ?? null) ? $ctx['hpl_rows'] : [];
        $accRows = is_array($ctx['acc_rows'] ?? null) ? $ctx['acc_rows'] : [];
        $labor = is_array($ctx['labor'] ?? null) ? $ctx['labor'] : [];
        $qty = isset($product['qty']) ? (float)($product['qty'] ?? 0) : 0.0;
        $unit = (string)($product['unit'] ?? '');
        $salePrice = (isset($product['sale_price']) && $product['sale_price'] !== null && $product['sale_price'] !== '' && is_numeric($product['sale_price']))
            ? (float)$product['sale_price']
            : 0.0;
        $saleTotal = ($salePrice > 0 && $qty > 0) ? ($salePrice * $qty) : null;
        $cncHours = (float)($labor['cnc_hours'] ?? 0.0);
        $cncCost = (float)($labor['cnc_cost'] ?? 0.0);
        $atelierHours = (float)($labor['atelier_hours'] ?? 0.0);
        $atelierCost = (float)($labor['atelier_cost'] ?? 0.0);
        $logo = (string)($company['logo_url'] ?? '');
        if ($logo === '' && isset($company['logo_thumb'])) $logo = (string)$company['logo_thumb'];

        ob_start();
        ?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Bon de consum <?= $esc($docNumber) ?></title>
  <style>
    body { font-family: Arial, sans-serif; color:#111; font-size:13px; margin:24px; }
    .header { display:flex; justify-content:space-between; gap:24px; }
    .company-name { font-weight:700; font-size:16px; }
    .doc-title { font-size:18px; font-weight:700; }
    .box { border:1px solid #ddd; padding:10px 12px; border-radius:6px; }
    .section { margin-top:16px; }
    table { width:100%; border-collapse:collapse; margin-top:8px; }
    th, td { border:1px solid #ddd; padding:7px; vertical-align:top; }
    th { background:#f6f7f8; text-align:left; font-size:12px; }
    .muted { color:#666; }
    .logo { max-height:50px; margin-bottom:8px; }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <?php if ($logo !== ''): ?>
        <img src="<?= $esc($logo) ?>" class="logo" alt="Logo">
      <?php endif; ?>
      <div class="company-name"><?= $esc($company['name'] ?? '') ?></div>
      <?php if (!empty($company['address'])): ?>
        <div class="muted"><?= $esc($company['address']) ?></div>
      <?php endif; ?>
      <div class="muted">
        <?php if (!empty($company['phone'])): ?>Tel: <?= $esc($company['phone']) ?><?php endif; ?>
        <?php if (!empty($company['email'])): ?> · Email: <?= $esc($company['email']) ?><?php endif; ?>
      </div>
    </div>
    <div class="box">
      <div class="doc-title">Bon de consum nr. <?= $esc($docNumber) ?></div>
      <div>Data: <?= $esc($docDate) ?></div>
      <?php if ($projectLabel !== ''): ?>
        <div>Proiect: <?= $esc($projectLabel) ?></div>
      <?php endif; ?>
      <div>Produs: <?= $esc($product['code'] ?? '') ?><?= (!empty($product['code']) && !empty($product['name'])) ? ' · ' : '' ?><?= $esc($product['name'] ?? '') ?></div>
    </div>
  </div>

  <div class="section">
    <div class="title">Consum HPL</div>
    <?php if (!$hplRows): ?>
      <div class="muted">Nu există consum HPL.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Cod placă</th>
            <th>Denumire</th>
            <th style="width:160px">Dimensiuni</th>
            <th style="width:80px">Cant.</th>
            <th style="width:110px">Preț/buc</th>
            <th style="width:110px">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hplRows as $r): ?>
            <?php
              $dispH = (int)($r['display_height_mm'] ?? ($r['height_mm'] ?? 0));
              $dispW = (int)($r['display_width_mm'] ?? ($r['width_mm'] ?? 0));
              $dispQty = isset($r['display_qty']) ? (float)$r['display_qty'] : (float)($r['qty'] ?? 0);
              $dim = ($dispW > 0 && $dispH > 0)
                ? ($dispH . ' × ' . $dispW . ' mm')
                : '—';
            ?>
            <?php
              $hplUp = isset($r['unit_price']) && $r['unit_price'] !== null ? (float)$r['unit_price'] : null;
              $hplTot = isset($r['total_price']) && $r['total_price'] !== null ? (float)$r['total_price'] : null;
            ?>
            <tr>
              <td><?= $esc($r['board_code'] ?? '') ?></td>
              <td><?= $esc($r['board_name'] ?? '') ?></td>
              <td><?= $esc($dim) ?></td>
              <td><?= $esc(self::fmtQty($dispQty)) ?></td>
              <td><?= $hplUp !== null ? $esc(self::fmtMoney($hplUp)) : '—' ?></td>
              <td><?= $hplTot !== null ? $esc(self::fmtMoney($hplTot)) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="section">
    <div class="title">Consum accesorii</div>
    <?php if (!$accRows): ?>
      <div class="muted">Nu există consum accesorii.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Cod</th>
            <th>Denumire</th>
            <th style="width:90px">Cantitate</th>
            <th style="width:110px">Preț/buc</th>
            <th style="width:110px">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accRows as $r): ?>
            <?php
              $accUp = (isset($r['unit_price']) && $r['unit_price'] !== null && $r['unit_price'] !== '' && is_numeric($r['unit_price']))
                ? (float)$r['unit_price']
                : null;
              $accTot = $accUp !== null ? ($accUp * (float)($r['qty'] ?? 0)) : null;
            ?>
            <tr>
              <td><?= $esc($r['code'] ?? '') ?></td>
              <td><?= $esc($r['name'] ?? '') ?></td>
              <td><?= $esc(self::fmtQty((float)($r['qty'] ?? 0))) ?><?= !empty($r['unit']) ? (' ' . $esc($r['unit'])) : '' ?></td>
              <td><?= $accUp !== null ? $esc(self::fmtMoney($accUp)) : '—' ?></td>
              <td><?= $accTot !== null ? $esc(self::fmtMoney($accTot)) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="section">
    <div class="title">Manoperă</div>
    <?php if ($cncHours <= 0 && $atelierHours <= 0): ?>
      <div class="muted">Nu există manoperă.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Tip</th>
            <th style="width:110px">Ore</th>
            <th style="width:140px">Cost</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($cncHours > 0 || $cncCost > 0): ?>
            <tr>
              <td>CNC</td>
              <td><?= $esc(self::fmtQty($cncHours, 2)) ?></td>
              <td><?= $esc(self::fmtMoney($cncCost)) ?></td>
            </tr>
          <?php endif; ?>
          <?php if ($atelierHours > 0 || $atelierCost > 0): ?>
            <tr>
              <td>Atelier</td>
              <td><?= $esc(self::fmtQty($atelierHours, 2)) ?></td>
              <td><?= $esc(self::fmtMoney($atelierCost)) ?></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php
    $totalHpl = 0.0;
    foreach ($hplRows as $r) {
      if (isset($r['total_price']) && $r['total_price'] !== null) $totalHpl += (float)$r['total_price'];
    }
    $totalAcc = 0.0;
    foreach ($accRows as $r) {
      if (isset($r['unit_price']) && $r['unit_price'] !== null && is_numeric($r['unit_price'])) {
        $totalAcc += (float)$r['unit_price'] * (float)($r['qty'] ?? 0);
      }
    }
    $totalLabor = $cncCost + $atelierCost;
    $totalCosts = $totalHpl + $totalAcc + $totalLabor;
  ?>
  <div class="section">
    <div class="title">Total costuri: <?= $esc(self::fmtMoney($totalCosts)) ?></div>
    <?php if ($saleTotal !== null): ?>
      <div class="muted">Preț vânzare produs: <?= $esc(self::fmtMoney($saleTotal)) ?></div>
    <?php elseif ($salePrice > 0): ?>
      <div class="muted">Preț vânzare produs: <?= $esc(self::fmtMoney($salePrice)) ?></div>
    <?php endif; ?>
  </div>
  <?php if ($avizNumber !== '' || $avizDate !== ''): ?>
    <div class="muted" style="margin-top:12px;">
      <?php if ($avizNumber !== ''): ?>Număr aviz: <?= $esc($avizNumber) ?><?php endif; ?>
      <?php if ($avizDate !== ''): ?><?= $avizNumber !== '' ? ' · ' : '' ?>Data aviz: <?= $esc($avizDate) ?><?php endif; ?>
    </div>
  <?php endif; ?>
</body>
</html>
        <?php
        return (string)ob_get_clean();
    }

    private static function renderBonConsumGeneralHtml(array $ctx): string
    {
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $company = $ctx['company'] ?? [];
        $docDate = (string)($ctx['doc_date'] ?? '');
        $projectLabel = (string)($ctx['project_label'] ?? '');
        $productRows = is_array($ctx['product_rows'] ?? null) ? $ctx['product_rows'] : [];
        $hplRows = is_array($ctx['hpl_rows'] ?? null) ? $ctx['hpl_rows'] : [];
        $accRows = is_array($ctx['acc_rows'] ?? null) ? $ctx['acc_rows'] : [];
        $labor = is_array($ctx['labor'] ?? null) ? $ctx['labor'] : [];
        $totalSale = isset($ctx['total_sale']) ? (float)($ctx['total_sale'] ?? 0.0) : 0.0;
        $cncHours = (float)($labor['cnc_hours'] ?? 0.0);
        $cncCost = (float)($labor['cnc_cost'] ?? 0.0);
        $atelierHours = (float)($labor['atelier_hours'] ?? 0.0);
        $atelierCost = (float)($labor['atelier_cost'] ?? 0.0);
        $logo = (string)($company['logo_url'] ?? '');
        if ($logo === '' && isset($company['logo_thumb'])) $logo = (string)$company['logo_thumb'];

        ob_start();
        ?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Bon de consum general</title>
  <style>
    body { font-family: Arial, sans-serif; color:#111; font-size:13px; margin:24px; }
    .header { display:flex; justify-content:space-between; gap:24px; }
    .company-name { font-weight:700; font-size:16px; }
    .doc-title { font-size:18px; font-weight:700; }
    .box { border:1px solid #ddd; padding:10px 12px; border-radius:6px; }
    .section { margin-top:16px; }
    table { width:100%; border-collapse:collapse; margin-top:8px; }
    th, td { border:1px solid #ddd; padding:7px; vertical-align:top; }
    th { background:#f6f7f8; text-align:left; font-size:12px; }
    .muted { color:#666; }
    .logo { max-height:50px; margin-bottom:8px; }
    .list { margin:6px 0 0 18px; padding:0; }
    .list li { margin-bottom:3px; }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <?php if ($logo !== ''): ?>
        <img src="<?= $esc($logo) ?>" class="logo" alt="Logo">
      <?php endif; ?>
      <div class="company-name"><?= $esc($company['name'] ?? '') ?></div>
      <?php if (!empty($company['address'])): ?>
        <div class="muted"><?= $esc($company['address']) ?></div>
      <?php endif; ?>
      <div class="muted">
        <?php if (!empty($company['phone'])): ?>Tel: <?= $esc($company['phone']) ?><?php endif; ?>
        <?php if (!empty($company['email'])): ?> · Email: <?= $esc($company['email']) ?><?php endif; ?>
      </div>
    </div>
    <div class="box">
      <div class="doc-title">Bon de consum general</div>
      <div>Data: <?= $esc($docDate) ?></div>
      <?php if ($projectLabel !== ''): ?>
        <div>Proiect: <?= $esc($projectLabel) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="section">
    <div class="title">Produse incluse</div>
    <?php if (!$productRows): ?>
      <div class="muted">Nu există produse cu status Avizare/Livrat.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Produs</th>
            <th style="width:140px">Cost</th>
            <th style="width:160px">Preț vânzare</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($productRows as $pr): ?>
            <tr>
              <td><?= $esc($pr['label'] ?? '') ?></td>
              <td><?= $esc(self::fmtMoney((float)($pr['cost_total'] ?? 0.0))) ?></td>
              <td><?= $esc(self::fmtMoney((float)($pr['sale_total'] ?? 0.0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="section">
    <div class="title">Consum HPL</div>
    <?php if (!$hplRows): ?>
      <div class="muted">Nu există consum HPL.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Cod placă</th>
            <th>Denumire</th>
            <th style="width:160px">Dimensiuni</th>
            <th style="width:80px">Cant.</th>
            <th style="width:110px">Preț/buc</th>
            <th style="width:110px">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hplRows as $r): ?>
            <?php
              $dispH = (int)($r['display_height_mm'] ?? ($r['height_mm'] ?? 0));
              $dispW = (int)($r['display_width_mm'] ?? ($r['width_mm'] ?? 0));
              $dispQty = isset($r['display_qty']) ? (float)$r['display_qty'] : (float)($r['qty'] ?? 0);
              $dim = ($dispW > 0 && $dispH > 0)
                ? ($dispH . ' × ' . $dispW . ' mm')
                : '—';
            ?>
            <?php
              $hplUp = isset($r['unit_price']) && $r['unit_price'] !== null ? (float)$r['unit_price'] : null;
              $hplTot = isset($r['total_price']) && $r['total_price'] !== null ? (float)$r['total_price'] : null;
            ?>
            <tr>
              <td><?= $esc($r['board_code'] ?? '') ?></td>
              <td><?= $esc($r['board_name'] ?? '') ?></td>
              <td><?= $esc($dim) ?></td>
              <td><?= $esc(self::fmtQty($dispQty)) ?></td>
              <td><?= $hplUp !== null ? $esc(self::fmtMoney($hplUp)) : '—' ?></td>
              <td><?= $hplTot !== null ? $esc(self::fmtMoney($hplTot)) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="section">
    <div class="title">Consum accesorii</div>
    <?php if (!$accRows): ?>
      <div class="muted">Nu există consum accesorii.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Cod</th>
            <th>Denumire</th>
            <th style="width:90px">Cantitate</th>
            <th style="width:110px">Preț/buc</th>
            <th style="width:110px">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accRows as $r): ?>
            <?php
              $accUp = (isset($r['unit_price']) && $r['unit_price'] !== null && $r['unit_price'] !== '' && is_numeric($r['unit_price']))
                ? (float)$r['unit_price']
                : null;
              $accTot = $accUp !== null ? ($accUp * (float)($r['qty'] ?? 0)) : null;
            ?>
            <tr>
              <td><?= $esc($r['code'] ?? '') ?></td>
              <td><?= $esc($r['name'] ?? '') ?></td>
              <td><?= $esc(self::fmtQty((float)($r['qty'] ?? 0))) ?><?= !empty($r['unit']) ? (' ' . $esc($r['unit'])) : '' ?></td>
              <td><?= $accUp !== null ? $esc(self::fmtMoney($accUp)) : '—' ?></td>
              <td><?= $accTot !== null ? $esc(self::fmtMoney($accTot)) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="section">
    <div class="title">Manoperă</div>
    <?php if ($cncHours <= 0 && $atelierHours <= 0): ?>
      <div class="muted">Nu există manoperă.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Tip</th>
            <th style="width:110px">Ore</th>
            <th style="width:140px">Cost</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($cncHours > 0 || $cncCost > 0): ?>
            <tr>
              <td>CNC</td>
              <td><?= $esc(self::fmtQty($cncHours, 2)) ?></td>
              <td><?= $esc(self::fmtMoney($cncCost)) ?></td>
            </tr>
          <?php endif; ?>
          <?php if ($atelierHours > 0 || $atelierCost > 0): ?>
            <tr>
              <td>Atelier</td>
              <td><?= $esc(self::fmtQty($atelierHours, 2)) ?></td>
              <td><?= $esc(self::fmtMoney($atelierCost)) ?></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php
    $totalHpl = 0.0;
    foreach ($hplRows as $r) {
      if (isset($r['total_price']) && $r['total_price'] !== null) $totalHpl += (float)$r['total_price'];
    }
    $totalAcc = 0.0;
    foreach ($accRows as $r) {
      if (isset($r['unit_price']) && $r['unit_price'] !== null && is_numeric($r['unit_price'])) {
        $totalAcc += (float)$r['unit_price'] * (float)($r['qty'] ?? 0);
      }
    }
    $totalLabor = $cncCost + $atelierCost;
    $totalCosts = $totalHpl + $totalAcc + $totalLabor;
  ?>
  <div class="section">
    <div class="title">Total costuri: <?= $esc(self::fmtMoney($totalCosts)) ?></div>
    <div class="muted">Preț vânzare total: <?= $esc(self::fmtMoney($totalSale)) ?></div>
  </div>
</body>
</html>
        <?php
        return (string)ob_get_clean();
    }

    /**
     * @return array{deviz_number:int,bon_number:int}
     */
    private static function generateDocumentsForAvizare(int $projectId, int $ppId, array $before, string $avizNumber, string $avizDate = ''): array
    {
        $project = Project::find($projectId);
        if (!$project) {
            throw new \RuntimeException('Proiect invalid pentru deviz.');
        }

        $projectProducts = ProjectProduct::forProject($projectId);
        $ppRow = null;
        foreach ($projectProducts as $pp) {
            if ((int)($pp['id'] ?? 0) === $ppId) {
                $ppRow = $pp;
                break;
            }
        }
        if (!$ppRow) {
            throw new \RuntimeException('Produs proiect invalid pentru deviz.');
        }

        $invoiceClientId = isset($before['invoice_client_id']) ? (int)$before['invoice_client_id'] : 0;
        $deliveryAddressId = isset($before['delivery_address_id']) ? (int)$before['delivery_address_id'] : 0;
        $client = $invoiceClientId > 0 ? Client::find($invoiceClientId) : null;
        $delivery = $deliveryAddressId > 0 ? ClientAddress::find($deliveryAddressId) : null;
        if (!$client || !$delivery) {
            throw new \RuntimeException('Date de facturare/livrare invalide pentru deviz.');
        }

        $magConsum = ProjectMagazieConsumption::forProject($projectId);
        $accBy = self::accessoriesByProductForDisplay($projectProducts, $magConsum);
        $accRowsAll = $accBy[$ppId] ?? [];
        $accRowsDeviz = array_values(array_filter($accRowsAll, function (array $r): bool {
            return (int)($r['include_in_deviz'] ?? 1) === 1;
        }));

        $company = self::companySettingsForDocs();
        $projectLabel = self::projectLabel($project);
        $productName = trim((string)($ppRow['product_name'] ?? ''));
        $productCode = trim((string)($ppRow['product_code'] ?? ''));
        $productNotes = trim((string)($ppRow['product_notes'] ?? ''));
        $ppNotes = trim((string)($ppRow['notes'] ?? ''));
        $description = $productNotes !== '' ? $productNotes : $ppNotes;
        $qty = (float)($ppRow['qty'] ?? 0);
        $unit = (string)($ppRow['unit'] ?? '');
        $unitPrice = isset($ppRow['product_sale_price']) && $ppRow['product_sale_price'] !== null && $ppRow['product_sale_price'] !== '' && is_numeric($ppRow['product_sale_price'])
            ? (float)$ppRow['product_sale_price']
            : 0.0;

        try {
            $devizNumber = self::nextDocNumber('deviz_last_number', 10000);
        } catch (\Throwable $e) {
            try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
            $devizNumber = self::nextDocNumber('deviz_last_number', 10000);
        }
        try {
            $bonNumber = self::nextDocNumber('bon_consum_last_number', 10000);
        } catch (\Throwable $e) {
            try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
            $bonNumber = self::nextDocNumber('bon_consum_last_number', 10000);
        }

        $docDate = date('Y-m-d');

        $projectNameForDoc = trim((string)($project['name'] ?? ''));
        if ($projectNameForDoc === '') $projectNameForDoc = trim((string)($project['code'] ?? ''));
        if ($projectNameForDoc === '') $projectNameForDoc = 'Proiect';
        $productNameForDoc = $productName !== '' ? $productName : 'Produs';
        $devizLabel = 'DEVIZ #' . $devizNumber . ' - ' . $projectNameForDoc . ' - ' . $productNameForDoc . ' - ' . $docDate;
        $bonLabel = 'BON CONSUM #' . $bonNumber . ' - ' . $projectNameForDoc . ' - ' . $productNameForDoc . ' - ' . $docDate;

        $devizHtml = self::renderDevizHtml([
            'company' => $company,
            'client' => $client,
            'delivery' => $delivery,
            'product' => [
                'name' => $productName !== '' ? $productName : 'Produs',
                'description' => $description,
                'qty' => $qty,
                'unit' => $unit,
                'unit_price' => $unitPrice,
            ],
            'accessories' => self::aggregateAccessories($accRowsDeviz),
            'doc_number' => $devizNumber,
            'doc_date' => $docDate,
            'project_label' => $projectLabel,
            'aviz_number' => $avizNumber,
            'aviz_date' => $avizDate,
        ]);
        $devizFile = self::saveHtmlDocument(
            'deviz-' . $devizNumber . '-pp' . $ppId . '-' . $projectNameForDoc . '-' . $productNameForDoc . '-' . $docDate . '.html',
            $devizHtml
        );
        $devizId = EntityFile::create([
            'entity_type' => 'projects',
            'entity_id' => $projectId,
            'category' => $devizLabel,
            'original_name' => $devizLabel . '.html',
            'stored_name' => $devizFile['stored_name'],
            'mime' => $devizFile['mime'],
            'size_bytes' => $devizFile['size_bytes'],
            'uploaded_by' => Auth::id(),
        ]);
        Audit::log('DEVIZ_GENERATED', 'entity_files', $devizId, null, null, [
            'project_id' => $projectId,
            'project_product_id' => $ppId,
            'number' => $devizNumber,
        ]);

        $hplRows = [];
        try {
            $hplRows = ProjectProductHplConsumption::forProjectProduct($ppId);
        } catch (\Throwable $e) {
            try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
            try { $hplRows = ProjectProductHplConsumption::forProjectProduct($ppId); } catch (\Throwable $e3) { $hplRows = []; }
        }
        $bonAccRows = self::aggregateAccessories($accRowsAll, 'CONSUMED');
        $workLogs = [];
        try {
            $workLogs = ProjectWorkLog::forProject($projectId);
        } catch (\Throwable $e) {
            $workLogs = [];
        }
        $laborByProduct = self::laborEstimateByProduct($projectProducts, $workLogs);
        $labor = $laborByProduct[$ppId] ?? [
            'cnc_hours' => 0.0,
            'cnc_cost' => 0.0,
            'atelier_hours' => 0.0,
            'atelier_cost' => 0.0,
        ];

        $bonHtml = self::renderBonConsumHtml([
            'company' => $company,
            'product' => [
                'name' => $productName !== '' ? $productName : 'Produs',
                'code' => $productCode,
                'qty' => $qty,
                'unit' => $unit,
                'sale_price' => $unitPrice,
            ],
            'hpl_rows' => self::aggregateConsumedHpl($hplRows),
            'acc_rows' => $bonAccRows,
            'labor' => $labor,
            'doc_number' => $bonNumber,
            'doc_date' => $docDate,
            'project_label' => $projectLabel,
            'aviz_number' => $avizNumber,
            'aviz_date' => $avizDate,
        ]);
        $bonFile = self::saveHtmlDocument(
            'bon-consum-' . $bonNumber . '-pp' . $ppId . '-' . $projectNameForDoc . '-' . $productNameForDoc . '-' . $docDate . '.html',
            $bonHtml
        );
        $bonId = EntityFile::create([
            'entity_type' => 'projects',
            'entity_id' => $projectId,
            'category' => $bonLabel,
            'original_name' => $bonLabel . '.html',
            'stored_name' => $bonFile['stored_name'],
            'mime' => $bonFile['mime'],
            'size_bytes' => $bonFile['size_bytes'],
            'uploaded_by' => Auth::id(),
        ]);
        Audit::log('BON_CONSUM_GENERATED', 'entity_files', $bonId, null, null, [
            'project_id' => $projectId,
            'project_product_id' => $ppId,
            'number' => $bonNumber,
        ]);

        return ['deviz_number' => $devizNumber, 'bon_number' => $bonNumber];
    }

    public static function bonConsumGeneral(array $params): void
    {
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $projectProducts = ProjectProduct::forProject($projectId);
        $eligible = [];
        $eligibleIds = [];
        foreach ($projectProducts as $pp) {
            $ppId = (int)($pp['id'] ?? 0);
            if ($ppId <= 0) continue;
            $st = strtoupper((string)($pp['production_status'] ?? ''));
            if (in_array($st, ['AVIZAT', 'LIVRAT'], true)) {
                $eligible[] = $pp;
                $eligibleIds[] = $ppId;
            }
        }

        $workLogs = [];
        try {
            $workLogs = ProjectWorkLog::forProject($projectId);
        } catch (\Throwable $e) {
            $workLogs = [];
        }
        $laborByProduct = self::laborEstimateByProduct($projectProducts, $workLogs);

        $magConsum = [];
        try {
            $magConsum = ProjectMagazieConsumption::forProject($projectId);
        } catch (\Throwable $e) {
            try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
            try { $magConsum = ProjectMagazieConsumption::forProject($projectId); } catch (\Throwable $e3) { $magConsum = []; }
        }
        $accBy = self::accessoriesByProductForDisplay($projectProducts, $magConsum);
        $productRows = [];
        $totalSale = 0.0;
        $hplRows = [];
        $accRows = [];
        $labor = [
            'cnc_hours' => 0.0,
            'cnc_cost' => 0.0,
            'atelier_hours' => 0.0,
            'atelier_cost' => 0.0,
        ];
        foreach ($eligible as $pp) {
            $ppId = (int)($pp['id'] ?? 0);
            if ($ppId <= 0) continue;

            $label = self::productLabelFromProjectProduct($pp);
            $qty = (float)($pp['qty'] ?? 0);
            $unit = (string)($pp['unit'] ?? '');
            if ($qty > 0) {
                $label .= ' · ' . self::fmtQty($qty) . ($unit !== '' ? (' ' . $unit) : '');
            }
            $unitSale = (isset($pp['product_sale_price']) && $pp['product_sale_price'] !== null && $pp['product_sale_price'] !== '' && is_numeric($pp['product_sale_price']))
                ? (float)$pp['product_sale_price']
                : 0.0;
            $saleTotal = ($qty > 0) ? ($unitSale * $qty) : $unitSale;
            $totalSale += $saleTotal;

            $lab = $laborByProduct[$ppId] ?? null;
            if (is_array($lab)) {
                $labor['cnc_hours'] += (float)($lab['cnc_hours'] ?? 0.0);
                $labor['cnc_cost'] += (float)($lab['cnc_cost'] ?? 0.0);
                $labor['atelier_hours'] += (float)($lab['atelier_hours'] ?? 0.0);
                $labor['atelier_cost'] += (float)($lab['atelier_cost'] ?? 0.0);
            }
            $laborCost = is_array($lab) ? (float)($lab['total_cost'] ?? 0.0) : 0.0;

            $accRowsProd = $accBy[$ppId] ?? [];
            $accRows = array_merge($accRows, $accRowsProd);
            $accAgg = self::aggregateAccessories($accRowsProd, 'CONSUMED');
            $accCost = 0.0;
            foreach ($accAgg as $a) {
                $up = isset($a['unit_price']) && $a['unit_price'] !== null && is_numeric($a['unit_price'])
                    ? (float)$a['unit_price']
                    : 0.0;
                $accCost += $up * (float)($a['qty'] ?? 0);
            }

            $hplRowsProd = [];
            try {
                $hplRowsProd = ProjectProductHplConsumption::forProjectProduct($ppId);
            } catch (\Throwable $e) {
                try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
                try { $hplRowsProd = ProjectProductHplConsumption::forProjectProduct($ppId); } catch (\Throwable $e3) { $hplRowsProd = []; }
            }
            $hplRows = array_merge($hplRows, $hplRowsProd);
            $hplAgg = self::aggregateConsumedHpl($hplRowsProd);
            $hplCost = 0.0;
            foreach ($hplAgg as $h) {
                if (isset($h['total_price']) && $h['total_price'] !== null) {
                    $hplCost += (float)$h['total_price'];
                }
            }

            $productRows[] = [
                'label' => $label,
                'cost_total' => $laborCost + $accCost + $hplCost,
                'sale_total' => $saleTotal,
            ];
        }

        $company = self::companySettingsForDocs();
        $projectLabel = self::projectLabel($project);
        $docDate = date('Y-m-d');

        $html = self::renderBonConsumGeneralHtml([
            'company' => $company,
            'project_label' => $projectLabel,
            'doc_date' => $docDate,
            'product_rows' => $productRows,
            'hpl_rows' => self::aggregateConsumedHpl($hplRows),
            'acc_rows' => self::aggregateAccessories($accRows, 'CONSUMED'),
            'labor' => $labor,
            'total_sale' => $totalSale,
        ]);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
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
     * Calculează costul HPL din piesele din stocul proiectului (rezervat/consumat).
     *
     * @param array<int, array<string,mixed>> $projectHplPieces
     * @return array<string,mixed>
     */
    private static function hplTotalsFromProjectPieces(array $projectHplPieces): array
    {
        $priceCache = [];
        $resM2 = 0.0;
        $resCost = 0.0;
        $conM2 = 0.0;
        $conCost = 0.0;
        $totM2 = 0.0;
        $totCost = 0.0;
        $hasRows = false;

        foreach ($projectHplPieces as $p) {
            $status = (string)($p['status'] ?? '');
            if ($status !== 'RESERVED' && $status !== 'CONSUMED') continue;
            $boardId = (int)($p['board_id'] ?? 0);
            if ($boardId <= 0) continue;

            $m2 = 0.0;
            if (isset($p['area_total_m2']) && is_numeric($p['area_total_m2'])) {
                $m2 = (float)$p['area_total_m2'];
            }
            if ($m2 <= 0) {
                $w = (int)($p['width_mm'] ?? 0);
                $h = (int)($p['height_mm'] ?? 0);
                $qty = (int)($p['qty'] ?? 0);
                if ($qty <= 0) $qty = 1;
                if ($w > 0 && $h > 0) {
                    $m2 = (($w * $h) / 1000000.0) * $qty;
                }
            }
            if ($m2 <= 0) continue;

            $ppm = self::hplPricePerM2ForBoard($boardId, $p, $priceCache);
            $cost = ($ppm > 0) ? ($m2 * $ppm) : 0.0;
            if ($ppm > 0) {
                $totM2 += $m2;
                $totCost += $cost;
            }

            if ($status === 'RESERVED') {
                $resM2 += $m2;
                $resCost += $cost;
            } else {
                $conM2 += $m2;
                $conCost += $cost;
            }
            $hasRows = true;
        }

        $avgPpm = ($totM2 > 0) ? ($totCost / $totM2) : 0.0;
        return [
            'has_rows' => $hasRows,
            'reserved_m2' => $resM2,
            'reserved_cost' => $resCost,
            'consumed_m2' => $conM2,
            'consumed_cost' => $conCost,
            'total_m2' => $totM2,
            'total_cost' => $totCost,
            'avg_ppm' => $avgPpm,
        ];
    }

    /**
     * Calculează prețul lei/mp pentru o placă (cu cache).
     *
     * @param array<int, float> $cache
     */
    private static function hplPricePerM2ForBoard(int $boardId, array $row, array &$cache): float
    {
        if ($boardId <= 0) return 0.0;
        if (isset($cache[$boardId])) return (float)$cache[$boardId];

        $ppm = 0.0;
        if (isset($row['board_sale_price_per_m2']) && $row['board_sale_price_per_m2'] !== null && $row['board_sale_price_per_m2'] !== '' && is_numeric($row['board_sale_price_per_m2'])) {
            $ppm = (float)$row['board_sale_price_per_m2'];
        }
        if ($ppm <= 0) {
            $sale = (isset($row['board_sale_price']) && $row['board_sale_price'] !== null && $row['board_sale_price'] !== '' && is_numeric($row['board_sale_price']))
                ? (float)$row['board_sale_price']
                : null;
            $wmm = (int)($row['board_std_width_mm'] ?? $row['std_width_mm'] ?? 0);
            $hmm = (int)($row['board_std_height_mm'] ?? $row['std_height_mm'] ?? 0);
            $area = ($wmm > 0 && $hmm > 0) ? (($wmm * $hmm) / 1000000.0) : 0.0;
            if ($sale !== null && $sale >= 0 && $area > 0) {
                $ppm = $sale / $area;
            }
        }
        if ($ppm <= 0) {
            try {
                $b = HplBoard::find($boardId);
                if ($b) {
                    $ppm = (isset($b['sale_price_per_m2']) && $b['sale_price_per_m2'] !== null && $b['sale_price_per_m2'] !== '' && is_numeric($b['sale_price_per_m2']))
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
        }
        $cache[$boardId] = $ppm;
        return $ppm;
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
        array $hplConsum,
        array $projectHplPieces = []
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
        $hplAvgPpm = 0.0;

        $hplFromPieces = self::hplTotalsFromProjectPieces($projectHplPieces);
        if ($hplFromPieces['has_rows'] ?? false) {
            $hplResM2 = (float)($hplFromPieces['reserved_m2'] ?? 0.0);
            $hplResCost = (float)($hplFromPieces['reserved_cost'] ?? 0.0);
            $hplConM2 = (float)($hplFromPieces['consumed_m2'] ?? 0.0);
            $hplConCost = (float)($hplFromPieces['consumed_cost'] ?? 0.0);
            $hplTotM2 = (float)($hplFromPieces['total_m2'] ?? 0.0);
            $hplTotCost = (float)($hplFromPieces['total_cost'] ?? 0.0);
            $hplAvgPpm = (float)($hplFromPieces['avg_ppm'] ?? 0.0);
        } else {
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
        }

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

        // Cerință: în sumarul de cost folosim doar HPL efectiv consumat.
        if ($hplConCost > 0) {
            $hplCost = $hplConCost;
        }
        $totalCost = $laborCost + $magCost + $hplCost;

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

    private static function countOffcutAvailableByDim(int $boardId, int $widthMm, int $heightMm): int
    {
        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        try {
            $st = $pdo->prepare("
                SELECT COALESCE(SUM(qty),0) AS c
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'OFFCUT'
                  AND status = 'AVAILABLE'
                  AND width_mm = ?
                  AND height_mm = ?
                  AND (is_accounting = 1 OR is_accounting IS NULL)
            ");
            $st->execute([(int)$boardId, (int)$widthMm, (int)$heightMm]);
            $r = $st->fetch();
            return (int)($r['c'] ?? 0);
        } catch (\Throwable $e) {
            // Compat: vechi schema fără is_accounting
            $st = $pdo->prepare("
                SELECT COALESCE(SUM(qty),0) AS c
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'OFFCUT'
                  AND status = 'AVAILABLE'
                  AND width_mm = ?
                  AND height_mm = ?
            ");
            $st->execute([(int)$boardId, (int)$widthMm, (int)$heightMm]);
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
            $carryNotes = !($fromStatus === 'AVAILABLE' || $toStatus === 'AVAILABLE');

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
                    if ($toStatus === 'AVAILABLE') {
                        $destNote = trim((string)($ident['notes'] ?? ''));
                        $noteForMatch = trim((string)($noteAppend ?? ''));
                        if ($noteForMatch === '') {
                            if ($destNote !== '') $ident = null;
                        } elseif ($destNote !== $noteForMatch) {
                            $ident = null;
                        }
                    }
                    $destId = (int)($ident['id'] ?? 0);
                    if ($destId > 0) {
                        HplStockPiece::incrementQty($destId, $take);
                        if ($noteAppend) {
                            if ($carryNotes) {
                                HplStockPiece::appendNote($destId, $noteAppend);
                            } else {
                                try { HplStockPiece::updateFields($destId, ['notes' => $noteAppend]); } catch (\Throwable $e) {}
                            }
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
                        $data = ['status' => $toStatus, 'project_id' => ($toStatus === 'AVAILABLE' ? null : $projectId), 'location' => $destLocation];
                        if ($noteAppend && !$carryNotes) $data['notes'] = $noteAppend;
                        HplStockPiece::updateFields($id, $data);
                        if ($noteAppend && $carryNotes) HplStockPiece::appendNote($id, $noteAppend);
                    }
                } else {
                    $data = ['status' => $toStatus, 'project_id' => ($toStatus === 'AVAILABLE' ? null : $projectId), 'location' => $destLocation];
                    if ($noteAppend && !$carryNotes) $data['notes'] = $noteAppend;
                    HplStockPiece::updateFields($id, $data);
                    if ($noteAppend && $carryNotes) HplStockPiece::appendNote($id, $noteAppend);
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
                    if ($toStatus === 'AVAILABLE') {
                        $destNote = trim((string)($ident['notes'] ?? ''));
                        $noteForMatch = trim((string)($noteAppend ?? ''));
                        if ($noteForMatch === '') {
                            if ($destNote !== '') $ident = null;
                        } elseif ($destNote !== $noteForMatch) {
                            $ident = null;
                        }
                    }
                    HplStockPiece::incrementQty((int)$ident['id'], $take);
                    if ($noteAppend) {
                        if ($carryNotes) {
                            HplStockPiece::appendNote((int)$ident['id'], $noteAppend);
                        } else {
                            try { HplStockPiece::updateFields((int)$ident['id'], ['notes' => $noteAppend]); } catch (\Throwable $e) {}
                        }
                    }
                } else {
                    $newNotes = $carryNotes ? trim($notes) : '';
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

    private static function moveOffcutPieces(
        int $boardId,
        int $widthMm,
        int $heightMm,
        int $qty,
        string $fromStatus,
        string $toStatus,
        ?string $noteAppend = null,
        ?int $projectId = null,
        ?string $fromLocation = null,
        ?string $toLocation = null
    ): void {
        $qty = (int)$qty;
        if ($qty <= 0 || $boardId <= 0 || $widthMm <= 0 || $heightMm <= 0) return;

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();

        $fromLocation = $fromLocation !== null ? trim((string)$fromLocation) : null;
        $toLocation = $toLocation !== null ? trim((string)$toLocation) : null;

        $rows = [];
        try {
            $st = $pdo->prepare("
                SELECT *
                FROM hpl_stock_pieces
                WHERE board_id = ?
                  AND piece_type = 'OFFCUT'
                  AND status = ?
                  AND width_mm = ?
                  AND height_mm = ?
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
                  AND (is_accounting = 1 OR is_accounting IS NULL)
                ORDER BY (location = 'Producție') DESC, created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([
                (int)$boardId,
                $fromStatus,
                (int)$widthMm,
                (int)$heightMm,
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
                  AND piece_type = 'OFFCUT'
                  AND status = ?
                  AND width_mm = ?
                  AND height_mm = ?
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
                (int)$widthMm,
                (int)$heightMm,
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

            $location = (string)($r['location'] ?? '');
            $destLocation = ($toLocation !== null && $toLocation !== '') ? $toLocation : $location;
            $isAcc = (int)($r['is_accounting'] ?? 1);
            $notes = (string)($r['notes'] ?? '');
            $carryNotes = !($fromStatus === 'AVAILABLE' || $toStatus === 'AVAILABLE');

            if ($take === $rowQty) {
                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, 'OFFCUT', $toStatus, $widthMm, $heightMm, $destLocation, $isAcc, $toStatus === 'AVAILABLE' ? null : $projectId, $id);
                } catch (\Throwable $e) {
                    $ident = null;
                }
                if ($ident) {
                    if ($toStatus === 'AVAILABLE') {
                        $destNote = trim((string)($ident['notes'] ?? ''));
                        $noteForMatch = trim((string)($noteAppend ?? ''));
                        if ($noteForMatch === '') {
                            if ($destNote !== '') $ident = null;
                        } elseif ($destNote !== $noteForMatch) {
                            $ident = null;
                        }
                    }
                    $destId = (int)($ident['id'] ?? 0);
                    if ($destId > 0) {
                        HplStockPiece::incrementQty($destId, $take);
                        if ($noteAppend) {
                            if ($carryNotes) {
                                HplStockPiece::appendNote($destId, $noteAppend);
                            } else {
                                try { HplStockPiece::updateFields($destId, ['notes' => $noteAppend]); } catch (\Throwable $e) {}
                            }
                        }
                        try {
                            if ($toStatus === 'AVAILABLE') {
                                HplStockPiece::updateFields($destId, ['project_id' => null]);
                            } elseif ($projectId !== null && $projectId > 0) {
                                HplStockPiece::updateFields($destId, ['project_id' => $projectId]);
                            }
                        } catch (\Throwable $e) {}
                        HplStockPiece::delete($id);
                    } else {
                        $data = ['status' => $toStatus, 'project_id' => ($toStatus === 'AVAILABLE' ? null : $projectId), 'location' => $destLocation];
                        if ($noteAppend && !$carryNotes) $data['notes'] = $noteAppend;
                        HplStockPiece::updateFields($id, $data);
                        if ($noteAppend && $carryNotes) HplStockPiece::appendNote($id, $noteAppend);
                    }
                } else {
                    $data = ['status' => $toStatus, 'project_id' => ($toStatus === 'AVAILABLE' ? null : $projectId), 'location' => $destLocation];
                    if ($noteAppend && !$carryNotes) $data['notes'] = $noteAppend;
                    HplStockPiece::updateFields($id, $data);
                    if ($noteAppend && $carryNotes) HplStockPiece::appendNote($id, $noteAppend);
                }
            } else {
                HplStockPiece::updateQty($id, $rowQty - $take);

                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, 'OFFCUT', $toStatus, $widthMm, $heightMm, $destLocation, $isAcc, $toStatus === 'AVAILABLE' ? null : $projectId);
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
                        'piece_type' => 'OFFCUT',
                        'status' => $toStatus,
                        'width_mm' => $widthMm,
                        'height_mm' => $heightMm,
                        'qty' => $take,
                        'location' => $destLocation,
                        'notes' => $newNotes !== '' ? $newNotes : null,
                    ]);
                }
            }

            $need -= $take;
        }

        if ($need > 0) {
            throw new \RuntimeException('Stoc insuficient (resturi).');
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
            $projectMeta = [];
            $projectIds = [];
            foreach ($rows as $r) {
                $pid = (int)($r['id'] ?? 0);
                if ($pid <= 0) continue;
                $projectIds[] = $pid;
                $projectMeta[$pid] = [
                    'products_count' => 0,
                    'all_delivered' => false,
                    'reserved_any' => false,
                ];
            }
            $projectIds = array_values(array_unique($projectIds));
            if ($projectIds) {
                $pdo = \App\Core\DB::pdo();
                $in = implode(',', array_fill(0, count($projectIds), '?'));
                try {
                    $st = $pdo->prepare('
                        SELECT project_id,
                               COUNT(*) AS cnt,
                               SUM(CASE
                                     WHEN production_status = "LIVRAT"
                                       OR qty <= delivered_qty + 1e-9
                                     THEN 1 ELSE 0 END) AS delivered_cnt
                        FROM project_products
                        WHERE project_id IN (' . $in . ')
                        GROUP BY project_id
                    ');
                    $st->execute($projectIds);
                    foreach ($st->fetchAll() as $row) {
                        $pid = (int)($row['project_id'] ?? 0);
                        if (!isset($projectMeta[$pid])) continue;
                        $cnt = (int)($row['cnt'] ?? 0);
                        $delCnt = (int)($row['delivered_cnt'] ?? 0);
                        $projectMeta[$pid]['products_count'] = $cnt;
                        $projectMeta[$pid]['all_delivered'] = ($cnt > 0 && $delCnt >= $cnt);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $st = $pdo->prepare('
                        SELECT project_id
                        FROM project_magazie_consumptions
                        WHERE project_id IN (' . $in . ')
                          AND mode = "RESERVED"
                          AND qty > 0
                        GROUP BY project_id
                    ');
                    $st->execute($projectIds);
                    foreach ($st->fetchAll() as $row) {
                        $pid = (int)($row['project_id'] ?? 0);
                        if (isset($projectMeta[$pid])) $projectMeta[$pid]['reserved_any'] = true;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $st = $pdo->prepare('
                        SELECT project_id
                        FROM project_product_hpl_consumptions
                        WHERE project_id IN (' . $in . ')
                          AND status = "RESERVED"
                        GROUP BY project_id
                    ');
                    $st->execute($projectIds);
                    foreach ($st->fetchAll() as $row) {
                        $pid = (int)($row['project_id'] ?? 0);
                        if (isset($projectMeta[$pid])) $projectMeta[$pid]['reserved_any'] = true;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $st = $pdo->prepare('
                        SELECT project_id
                        FROM hpl_stock_pieces
                        WHERE project_id IN (' . $in . ')
                          AND status = "RESERVED"
                          AND qty > 0
                        GROUP BY project_id
                    ');
                    $st->execute($projectIds);
                    foreach ($st->fetchAll() as $row) {
                        $pid = (int)($row['project_id'] ?? 0);
                        if (isset($projectMeta[$pid])) $projectMeta[$pid]['reserved_any'] = true;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $st = $pdo->prepare('
                        SELECT project_id
                        FROM project_hpl_consumptions
                        WHERE project_id IN (' . $in . ')
                          AND mode = "RESERVED"
                        GROUP BY project_id
                    ');
                    $st->execute($projectIds);
                    foreach ($st->fetchAll() as $row) {
                        $pid = (int)($row['project_id'] ?? 0);
                        if (isset($projectMeta[$pid])) $projectMeta[$pid]['reserved_any'] = true;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            echo View::render('projects/index', [
                'title' => 'Proiecte',
                'rows' => $rows,
                'q' => $q,
                'status' => $status,
                'statuses' => self::statuses(),
                'projectMeta' => $projectMeta,
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
        $nextCode = '';
        try { $nextCode = Project::nextAutoCode(1000, false); } catch (\Throwable $e) { $nextCode = ''; }
        $labelsAll = [];
        try { $labelsAll = Label::all(); } catch (\Throwable $e) { $labelsAll = []; }

        echo View::render('projects/form', [
            'title' => 'Proiect nou',
            'mode' => 'create',
            'row' => [
                'code' => $nextCode,
                'status' => 'DRAFT',
                'priority' => 0,
            ],
            'errors' => [],
            'statuses' => self::statuses(),
            'clients' => Client::allWithProjects(), // reuse list (name/type)
            'groups' => ClientGroup::forSelect(),
            'labelsAll' => $labelsAll,
            'labelsSelected' => [],
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
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
            $nextCode = '';
            try { $nextCode = Project::nextAutoCode(1000, false); } catch (\Throwable $e) { $nextCode = ''; }
            $labelsAll = [];
            try { $labelsAll = Label::all(); } catch (\Throwable $e) { $labelsAll = []; }

            echo View::render('projects/form', [
                'title' => 'Proiect nou',
                'mode' => 'create',
                'row' => array_merge($_POST, ['code' => $nextCode]),
                'errors' => $errors,
                'statuses' => self::statuses(),
                'clients' => Client::allWithProjects(),
                'groups' => ClientGroup::forSelect(),
                'labelsAll' => $labelsAll,
                'labelsSelected' => [],
            ]);
            return;
        }

        $data = [
            'name' => trim((string)$_POST['name']),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'category' => trim((string)($_POST['category'] ?? '')) ?: null,
            'status' => $status ?: 'DRAFT',
            'priority' => $priority,
            'due_date' => trim((string)($_POST['due_date'] ?? '')) ?: null,
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
            'technical_notes' => trim((string)($_POST['technical_notes'] ?? '')) ?: null,
            'tags' => null,
            'client_id' => $clientId,
            'client_group_id' => $groupId,
            'created_by' => Auth::id(),
        ];

        try {
            /** @var \PDO $pdo */
            $pdo = \App\Core\DB::pdo();
            $pdo->beginTransaction();

            // Cod incremental (numeric), începând de la 1000
            $data['code'] = Project::nextAutoCode(1000, true);
            $id = Project::create($data);

            // Etichete (labels) la creare
            $labelsRaw = trim((string)($_POST['labels'] ?? ''));
            $labelNames = array_values(array_unique(array_filter(array_map(fn($s) => trim((string)$s), preg_split('/[,\n]+/', $labelsRaw) ?: []), fn($s) => $s !== '')));
            foreach ($labelNames as $ln) {
                $lid = Label::upsert($ln);
                if ($lid > 0) {
                    EntityLabel::attach('projects', $id, $lid, 'DIRECT', Auth::id());
                }
            }

            Audit::log('PROJECT_CREATE', 'projects', $id, null, $data, [
                'message' => 'A creat proiect: ' . $data['code'] . ' · ' . $data['name'],
                'labels' => $labelNames ? implode(', ', $labelNames) : null,
            ]);
            $pdo->commit();
            Session::flash('toast_success', 'Proiect creat.');
            Response::redirect('/projects/' . $id);
        } catch (\Throwable $e) {
            try { /** @var \PDO $pdo */ $pdo = \App\Core\DB::pdo(); if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
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
                if (!empty($project['deleted_at'])) {
                    Session::flash('toast_error', 'Acest proiect a fost șters.');
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
                $productComments = [];
                $docsByPp = [];
                if ($tab === 'products') {
                    try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
                    try { $workLogs = ProjectWorkLog::forProject($id); } catch (\Throwable $e) { $workLogs = []; }
                    $laborByProduct = self::laborEstimateByProduct($projectProducts, $workLogs);
                    try { $magazieConsum = ProjectMagazieConsumption::forProject($id); } catch (\Throwable $e) { $magazieConsum = []; }
                    try { $hplConsum = ProjectHplConsumption::forProject($id); } catch (\Throwable $e) { $hplConsum = []; }
                    $projectHplPieces = [];
                    try { $projectHplPieces = HplStockPiece::forProject($id); } catch (\Throwable $e) { $projectHplPieces = []; }
                    $billingClients = [];
                    $billingAddresses = [];
                    $billingClientIds = [];
                    $projectClientId = (int)($project['client_id'] ?? 0);
                    $projectGroupId = (int)($project['client_group_id'] ?? 0);
                    $addBillingClient = function (?array $c) use (&$billingClients, &$billingClientIds): void {
                        $cid = $c ? (int)($c['id'] ?? 0) : 0;
                        if ($cid <= 0 || isset($billingClientIds[$cid])) return;
                        $billingClientIds[$cid] = true;
                        $billingClients[] = [
                            'id' => $cid,
                            'name' => (string)($c['name'] ?? ''),
                            'type' => (string)($c['type'] ?? ''),
                        ];
                    };
                    if ($projectClientId > 0) {
                        try { $addBillingClient(Client::find($projectClientId)); } catch (\Throwable $e) {}
                    }
                    if ($projectGroupId > 0) {
                        try {
                            $others = Client::othersInGroup($projectClientId, $projectGroupId);
                            foreach ($others as $oc) $addBillingClient($oc);
                        } catch (\Throwable $e) {}
                    }
                    foreach ($projectProducts as $ppRow) {
                        $cid = (int)($ppRow['invoice_client_id'] ?? 0);
                        if ($cid <= 0) continue;
                        try { $addBillingClient(Client::find($cid)); } catch (\Throwable $e) {}
                    }
                    if (!$billingClients) {
                        try {
                            $all = Client::allWithProjects();
                            foreach ($all as $c) $addBillingClient($c);
                        } catch (\Throwable $e) {}
                    }
                    foreach (array_keys($billingClientIds) as $cid) {
                        try {
                            $billingAddresses[$cid] = ClientAddress::forClient($cid);
                        } catch (\Throwable $e) {
                            $billingAddresses[$cid] = [];
                        }
                    }
                    $ppHplByProduct = [];
                    foreach ($projectProducts as $ppRow) {
                        $ppId = (int)($ppRow['id'] ?? 0);
                        if ($ppId <= 0) continue;
                        try {
                            $ppHplByProduct[$ppId] = ProjectProductHplConsumption::forProjectProduct($ppId);
                        } catch (\Throwable $e) {
                            // dacă tabela nu există încă (deploy vechi), încercăm migrările și reîncercăm o singură dată.
                            try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
                            try { $ppHplByProduct[$ppId] = ProjectProductHplConsumption::forProjectProduct($ppId); } catch (\Throwable $e3) { $ppHplByProduct[$ppId] = []; }
                        }
                    }
                    foreach ($projectProducts as $ppRow) {
                        $ppId = (int)($ppRow['id'] ?? 0);
                        if ($ppId <= 0) continue;
                        try {
                            $productComments[$ppId] = EntityComment::forEntity('project_products', $ppId, 200);
                        } catch (\Throwable $e) {
                            $productComments[$ppId] = [];
                        }
                    }
                    $ppIds = [];
                    foreach ($projectProducts as $ppRow) {
                        $ppId = (int)($ppRow['id'] ?? 0);
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
                               OR (entity_type = "projects" AND entity_id = ? AND (
                                    stored_name LIKE "deviz-%-pp%.html"
                                    OR stored_name LIKE "bon-consum-%-pp%.html"
                                  ))
                            ORDER BY created_at DESC, id DESC
                        ';
                        try {
                            $stFiles = \App\Core\DB::pdo()->prepare($sqlFiles);
                            $stFiles->execute(array_merge($ppIds, [$id]));
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
                                    $stA = \App\Core\DB::pdo()->prepare("
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
                            $appendNum = true;
                            if (preg_match('/^(DEVIZ|BON\s+CONSUM)\s*#\d+\s*-/i', $category)) {
                                $label = $category;
                                $appendNum = false;
                            }
                            $num = '';
                            if (preg_match('/nr\.?\s*([0-9]+)/i', $category, $m)) {
                                $num = (string)$m[1];
                            } elseif (preg_match('/^(deviz|bon-consum)-(\d+)/', $stored, $m)) {
                                $num = (string)$m[2];
                            }
                            if ($appendNum && $num !== '') $label .= ' ' . $num;

                            $docsByPp[$ppId][$type] = [
                                'stored_name' => $stored,
                                'label' => $label,
                            ];
                        }
                    }
                    $magBy = self::magazieCostByProduct($projectProducts, $magazieConsum);
        $hplBy = self::hplCostByProduct($projectProducts);
                    $accBy = self::accessoriesByProductForDisplay($projectProducts, $magazieConsum);
                    foreach ($projectProducts as $pp) {
                        $ppId = (int)($pp['id'] ?? 0);
                        if ($ppId <= 0) continue;
                        $materialsByProduct[$ppId] = [
                            'mag_cost' => (float)($magBy[$ppId]['mag_cost'] ?? 0.0),
                            'hpl_cost' => (float)($hplBy[$ppId]['hpl_cost'] ?? 0.0),
                            'acc_rows' => $accBy[$ppId] ?? [],
                            'hpl_rows' => $ppHplByProduct[$ppId] ?? [],
                        ];
                    }
                    $projectCostSummary = self::projectSummaryFromProducts($projectProducts, $laborByProduct, $materialsByProduct, $magazieConsum, $hplConsum, $projectHplPieces);
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
                $projectProductLabels = [];
                if ($tab === 'history') {
                    try { $history = AuditLog::forProject($id, 300); } catch (\Throwable $e) { $history = []; }
                    try {
                        $ppRows = ProjectProduct::forProject($id);
                        foreach ($ppRows as $pp) {
                            $ppId = (int)($pp['id'] ?? 0);
                            if ($ppId <= 0) continue;
                            $name = trim((string)($pp['product_name'] ?? ''));
                            $code = trim((string)($pp['product_code'] ?? ''));
                            $label = $code !== '' ? ($code . ' · ' . $name) : ($name !== '' ? $name : ('#' . $ppId));
                            $projectProductLabels[$ppId] = $label;
                        }
                    } catch (\Throwable $e) {
                        $projectProductLabels = [];
                    }
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
                    'billingClients' => $billingClients ?? [],
                    'billingAddresses' => $billingAddresses ?? [],
                    'discussions' => $discussions,
                    'productComments' => $productComments ?? [],
                    'docsByPp' => $docsByPp ?? [],
                    'costSettings' => [
                        'labor' => (function () { try { return AppSetting::getFloat(AppSetting::KEY_COST_LABOR_PER_HOUR); } catch (\Throwable $e) { return null; } })(),
                        'cnc' => (function () { try { return AppSetting::getFloat(AppSetting::KEY_COST_CNC_PER_HOUR); } catch (\Throwable $e) { return null; } })(),
                    ],
                    'history' => $history,
                    'projectProductLabels' => $projectProductLabels,
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
            // cod auto - nu se editează manual
            'code' => (string)($before['code'] ?? ''),
            'name' => trim((string)$_POST['name']),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'category' => trim((string)($_POST['category'] ?? '')) ?: null,
            'status' => $status ?: 'DRAFT',
            'priority' => $priority,
            'due_date' => trim((string)($_POST['due_date'] ?? '')) ?: null,
            'completed_at' => $before['completed_at'] ?? null,
            'cancelled_at' => $before['cancelled_at'] ?? null,
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
            'technical_notes' => trim((string)($_POST['technical_notes'] ?? '')) ?: null,
            'tags' => $before['tags'] ?? null,
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
        $includeInDeviz = isset($_POST['include_in_deviz'])
            ? (int)$_POST['include_in_deviz']
            : (int)($before['include_in_deviz'] ?? 1);
        $includeInDeviz = isset($_POST['include_in_deviz']) ? (int)$_POST['include_in_deviz'] : 1;
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
        return $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
    }

    public static function canDelete(): bool
    {
        $u = Auth::user();
        return $u && (string)($u['role'] ?? '') === Auth::ROLE_ADMIN;
    }

    public static function delete(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('toast_error', 'Proiect invalid.');
            Response::redirect('/projects');
        }

        $before = Project::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        if (!self::canDelete()) {
            Session::flash('toast_error', 'Nu ai drepturi să ștergi proiecte.');
            Response::redirect('/projects/' . $id);
        }

        $now = date('Y-m-d H:i:s');
        $after = $before;
        $after['deleted_at'] = $now;
        $after['deleted_by'] = Auth::id();

        try {
            $pdo = \App\Core\DB::pdo();
            $pdo->beginTransaction();

            // Soft delete (best-effort compat)
            try {
                $st = $pdo->prepare('UPDATE projects SET deleted_at = :dt, deleted_by = :by WHERE id = :id AND (deleted_at IS NULL OR deleted_at = \'\')');
                $st->execute([':dt' => $now, ':by' => Auth::id(), ':id' => $id]);
            } catch (\Throwable $e) {
                // dacă coloanele nu există încă, încercăm migrările și reîncercăm
                try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
                $st = $pdo->prepare('UPDATE projects SET deleted_at = :dt, deleted_by = :by WHERE id = :id');
                $st->execute([':dt' => $now, ':by' => Auth::id(), ':id' => $id]);
            }

            Audit::log('PROJECT_DELETE', 'projects', $id, $before, $after, [
                'message' => 'A șters proiect: ' . (string)($before['code'] ?? '') . ' · ' . (string)($before['name'] ?? ''),
            ]);

            $pdo->commit();
            Session::flash('toast_success', 'Proiect șters.');
        } catch (\Throwable $e) {
            try { $pdo = \App\Core\DB::pdo(); if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot șterge proiectul: ' . $e->getMessage());
        }

        Response::redirect('/projects');
    }

    public static function canEditProjectProducts(): bool
    {
        $u = Auth::user();
        return $u && in_array((string)($u['role'] ?? ''), [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true);
    }

    /** Operator-ul nu mai poate edita după Gata de livrare (inclusiv). */
    public static function canOperatorEditProjectProduct(array $ppRow): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        $role = (string)($u['role'] ?? '');
        if ($role !== Auth::ROLE_OPERATOR) return true;
        $st = (string)($ppRow['production_status'] ?? 'CREAT');
        return !self::isFinalProductStatus($st);
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
            ['value' => 'AVIZAT', 'label' => 'Avizare'],
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
        $projLabel = self::projectLabel($project);
        $projLabel = self::projectLabel($project);

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
        ]);
        $errors = $check['errors'];
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
        $salePrice = $salePriceRaw !== '' ? (Validator::dec($salePriceRaw) ?? null) : null;
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $hplBoardId = null; // HPL se gestionează prin "Consum HPL"
        // Suprafață nu mai este obligatorie la creare (se poate completa ulterior din edit).
        $surfaceType = null;
        $surfaceValue = null;
        $m2 = null;

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
                'hpl_board_id' => null,
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
                'hpl_board_id' => null,
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

        // Cerință: după Gata de livrare, OPERATOR nu mai poate edita nimic.
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($before['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate edita.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
        $salePrice = $salePriceRaw !== '' ? (Validator::dec($salePriceRaw) ?? null) : null;
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $hplBoardId = null; // HPL se gestionează prin "Consum HPL"
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
            'hpl_board_id' => null,
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

    public static function updateProjectProductBilling(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);

        $before = ProjectProduct::find($ppId);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Produs proiect invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        // Cerință: după Gata de livrare, OPERATOR nu mai poate edita nimic.
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($before['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate edita.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        $invRaw = trim((string)($_POST['invoice_client_id'] ?? ''));
        $addrRaw = trim((string)($_POST['delivery_address_id'] ?? ''));
        $invoiceClientId = $invRaw !== '' ? (Validator::int($invRaw, 1) ?? null) : null;
        $deliveryAddressId = $addrRaw !== '' ? (Validator::int($addrRaw, 1) ?? null) : null;

        $errors = [];
        if ($invoiceClientId !== null) {
            $client = null;
            try { $client = Client::find($invoiceClientId); } catch (\Throwable $e) {}
            if (!$client) $errors['invoice_client_id'] = 'Firmă invalidă.';
        }
        if ($deliveryAddressId !== null) {
            if ($invoiceClientId === null) {
                $errors['delivery_address_id'] = 'Alege firma înainte de adresă.';
            } else {
                $addr = null;
                try { $addr = ClientAddress::find($deliveryAddressId); } catch (\Throwable $e) {}
                if (!$addr || (int)($addr['client_id'] ?? 0) !== (int)$invoiceClientId) {
                    $errors['delivery_address_id'] = 'Adresă invalidă pentru firma selectată.';
                }
            }
        }
        if ($errors) {
            Session::flash('toast_error', implode(' ', array_values($errors)));
            Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
        }

        try {
            ProjectProduct::updateBilling($ppId, $invoiceClientId, $deliveryAddressId);
            Audit::log('PROJECT_PRODUCT_BILLING_UPDATE', 'project_products', $ppId, $before, null, [
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'invoice_client_id' => $invoiceClientId,
                'delivery_address_id' => $deliveryAddressId,
            ]);
            Session::flash('toast_success', 'Datele de facturare/livrare au fost salvate.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot salva datele. Rulează Update DB dacă lipsesc coloanele.');
        }

        Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
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

        // Notă: HPL-ul pe piesă se gestionează prin "Consum HPL" (nu mai forțăm asocierea la creare/edit).

        $flow = array_map(fn($s) => (string)$s['value'], self::projectProductStatuses());
        $old = (string)($before['production_status'] ?? 'CREAT');
        $idx = array_search($old, $flow, true);
        if ($idx === false) $idx = 0;
        $next = $flow[$idx + 1] ?? null;
        if ($next === null) {
            Session::flash('toast_success', 'Produsul este deja la ultimul status.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $allowed = self::allowedProjectProductStatusesForCurrentUser();
        if (!in_array($next, $allowed, true)) {
            Session::flash('toast_error', 'Nu ai drepturi să avansezi la următorul status (Avizare/Livrat sunt doar pentru Admin/Gestionar).');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $avizNumber = '';
        $avizDateIso = null;
        $avizDateLabel = '';
        try {
            // Statusurile piesei nu mai modifică automat locația/statusul HPL-ului.
            // CNC -> Montaj: necesită manoperă CNC pe produs.
            if ($old === 'CNC' && $next === 'MONTAJ') {
                $hasCnc = false;
                try {
                    $stCnc = \App\Core\DB::pdo()->prepare("
                        SELECT id
                        FROM project_work_logs
                        WHERE project_id = ?
                          AND project_product_id = ?
                          AND work_type = 'CNC'
                          AND hours_estimated > 0
                        LIMIT 1
                    ");
                    $stCnc->execute([$projectId, $ppId]);
                    $hasCnc = (bool)$stCnc->fetch();
                } catch (\Throwable $e) {
                    $hasCnc = false;
                }
                if (!$hasCnc) {
                    $msg = 'Nu poți trece la Montaj: adaugă manoperă CNC pentru acest produs.';
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products');
                }
            }
            // CNC -> Montaj: blocăm până când toate plăcile/piesele HPL alocate pe piesă sunt "Debitat" (consumate manual).
            if ($old === 'CNC' && $next === 'MONTAJ') {
                $reserved = [];
                try {
                    $reserved = ProjectProductHplConsumption::reservedForProjectProduct($projectId, $ppId);
                } catch (\Throwable $e) {
                    try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
                    try { $reserved = ProjectProductHplConsumption::reservedForProjectProduct($projectId, $ppId); } catch (\Throwable $e3) { $reserved = []; }
                }
                if ($reserved) {
                    // mesaj explicit: arătăm ce anume blochează
                    $detailRows = [];
                    try {
                        $detailRows = ProjectProductHplConsumption::forProjectProduct($ppId);
                    } catch (\Throwable $e) {
                        try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
                        try { $detailRows = ProjectProductHplConsumption::forProjectProduct($ppId); } catch (\Throwable $e3) { $detailRows = []; }
                    }
                    $items = [];
                    foreach ($detailRows as $r) {
                        if ((string)($r['status'] ?? '') !== 'RESERVED') continue;
                        $b = trim((string)($r['board_code'] ?? '') . ' · ' . (string)($r['board_name'] ?? ''));
                        if ($b === '·' || $b === '· ') $b = trim((string)($r['board_code'] ?? ''));
                        $pt = (string)($r['piece_type'] ?? '');
                        $ph = (int)($r['piece_height_mm'] ?? 0);
                        $pw = (int)($r['piece_width_mm'] ?? 0);
                        $dim = ($ph > 0 && $pw > 0) ? ($ph . '×' . $pw . 'mm') : '';
                        $cm = (string)($r['consume_mode'] ?? '');
                        $src = (string)($r['source'] ?? '');
                        $txt = trim(($b !== '' ? $b : 'HPL') . ($pt !== '' ? (' · ' . $pt) : '') . ($dim !== '' ? (' · ' . $dim) : '') . ($cm !== '' ? (' · ' . $cm) : '') . ($src !== '' ? (' · ' . $src) : ''));
                        if ($txt !== '') $items[] = $txt;
                    }
                    $items = array_values(array_unique($items));
                    $more = count($items) > 4;
                    $items = array_slice($items, 0, 4);
                    $msg = 'Nu poți trece la Montaj: mai ai ' . count($reserved) . ' alocări HPL ne-debitate pe acest produs. '
                         . 'Debitează (sau Renunță) din tabelul HPL.'
                         . ($items ? (' ' . implode(' · ', $items) . ($more ? ' · …' : '')) : '');
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products');
                }
            }

            // Gata de livrare: necesită manoperă Atelier pe produs și fără accesorii rezervate neconsumate.
            if ($next === 'GATA_DE_LIVRARE') {
                $hasAtelier = false;
                try {
                    $stAt = \App\Core\DB::pdo()->prepare("
                        SELECT id
                        FROM project_work_logs
                        WHERE project_id = ?
                          AND project_product_id = ?
                          AND work_type = 'ATELIER'
                          AND hours_estimated > 0
                        LIMIT 1
                    ");
                    $stAt->execute([$projectId, $ppId]);
                    $hasAtelier = (bool)$stAt->fetch();
                } catch (\Throwable $e) {
                    $hasAtelier = false;
                }
                if (!$hasAtelier) {
                    $msg = 'Nu poți trece la Gata de livrare: adaugă manoperă Atelier pentru acest produs.';
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products');
                }

                $projectProducts = [];
                $magConsum = [];
                try { $projectProducts = ProjectProduct::forProject($projectId); } catch (\Throwable $e) { $projectProducts = []; }
                try {
                    $magConsum = ProjectMagazieConsumption::forProject($projectId);
                } catch (\Throwable $e) {
                    try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
                    try { $magConsum = ProjectMagazieConsumption::forProject($projectId); } catch (\Throwable $e3) { $magConsum = []; }
                }
                $accBy = self::accessoriesByProductForDisplay($projectProducts, $magConsum);
                $accRows = $accBy[$ppId] ?? [];
                $agg = [];
                foreach ($accRows as $r) {
                    if ((string)($r['mode'] ?? '') !== 'RESERVED') continue;
                    $iid = (int)($r['item_id'] ?? 0);
                    if ($iid <= 0) continue;
                    $qty = isset($r['qty']) ? (float)($r['qty'] ?? 0) : 0.0;
                    if ($qty <= 0) continue;
                    $unit = (string)($r['unit'] ?? '');
                    $code = trim((string)($r['code'] ?? ''));
                    $name = trim((string)($r['name'] ?? ''));
                    $label = trim($code . ' · ' . $name);
                    if ($label === '·' || $label === '· ') $label = $name !== '' ? $name : ($code !== '' ? $code : 'Accesoriu');
                    if (!isset($agg[$iid])) {
                        $agg[$iid] = ['label' => $label !== '' ? $label : 'Accesoriu', 'qty' => 0.0, 'unit' => $unit];
                    }
                    $agg[$iid]['qty'] += $qty;
                    if ($agg[$iid]['unit'] === '' && $unit !== '') $agg[$iid]['unit'] = $unit;
                }
                if ($agg) {
                    $items = [];
                    foreach ($agg as $a) {
                        $qtyTxt = number_format((float)($a['qty'] ?? 0), 3, '.', '');
                        $unit = (string)($a['unit'] ?? '');
                        $items[] = trim((string)($a['label'] ?? 'Accesoriu') . ' · ' . $qtyTxt . ($unit !== '' ? (' ' . $unit) : ''));
                    }
                    $more = count($items) > 4;
                    $items = array_slice($items, 0, 4);
                    $cnt = count($agg);
                    $msg = 'Nu poți trece la Gata de livrare: mai ai ' . $cnt . ' accesorii rezervate neconsumate pe acest produs. '
                         . 'Dă în consum accesoriile din secțiunea Accesorii.'
                         . ($items ? (' ' . implode(' · ', $items) . ($more ? ' · …' : '')) : '');
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products');
                }
            }

            if ($next === 'AVIZAT') {
                $invoiceClientId = isset($before['invoice_client_id']) ? (int)$before['invoice_client_id'] : 0;
                $deliveryAddressId = isset($before['delivery_address_id']) ? (int)$before['delivery_address_id'] : 0;
                if ($invoiceClientId <= 0 || $deliveryAddressId <= 0) {
                    $msg = 'Nu poți trece la Avizare: setează firma de facturare și adresa de livrare în Facturare/Livrare.';
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
                }
                $prodId = isset($before['product_id']) ? (int)$before['product_id'] : 0;
                $prod = $prodId > 0 ? Product::find($prodId) : null;
                $saleRaw = $prod && isset($prod['sale_price']) ? $prod['sale_price'] : null;
                $sale = ($saleRaw !== null && $saleRaw !== '' && is_numeric($saleRaw)) ? (float)$saleRaw : null;
                if ($sale === null || $sale <= 0) {
                    $msg = 'Nu poți trece la Avizare: completează prețul de vânzare al produsului.';
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
                }
                $avizNumber = trim((string)($_POST['aviz_number'] ?? ''));
                if ($avizNumber === '') {
                    $msg = 'Nu poți trece la Avizare: completează numărul de aviz.';
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
                }
                $avizNumber = mb_substr($avizNumber, 0, 40);
                $avizDateRaw = trim((string)($_POST['aviz_date'] ?? ''));
                if ($avizDateRaw === '') {
                    $msg = 'Nu poți trece la Avizare: completează data avizului.';
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
                }
                if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $avizDateRaw)) {
                    $msg = 'Nu poți trece la Avizare: data avizului trebuie să fie în format zz.ll.aaaa.';
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
                }
                $dt = \DateTime::createFromFormat('d.m.Y', $avizDateRaw);
                $dtErrors = \DateTime::getLastErrors();
                if (!$dt || ($dtErrors && ($dtErrors['warning_count'] ?? 0) > 0) || ($dtErrors && ($dtErrors['error_count'] ?? 0) > 0)) {
                    $msg = 'Nu poți trece la Avizare: data avizului este invalidă.';
                    Session::flash('toast_error', $msg);
                    Session::flash('pp_status_error', json_encode([
                        'id' => $ppId,
                        'message' => $msg,
                    ], JSON_UNESCAPED_UNICODE));
                    Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
                }
                $avizDateIso = $dt->format('Y-m-d');
                $avizDateLabel = $dt->format('d.m.Y');
            }

            $docInfo = null;
            if ($next === 'AVIZAT') {
                $docInfo = self::generateDocumentsForAvizare($projectId, $ppId, $before, $avizNumber, $avizDateLabel);
            }

            ProjectProduct::updateStatus($ppId, $next);
            if ($next === 'AVIZAT') {
                ProjectProduct::updateAvizData($ppId, $avizNumber, $avizDateIso);
            }
            $after = $before;
            $after['production_status'] = $next;
            Audit::log('PROJECT_PRODUCT_STATUS_CHANGE', 'project_products', $ppId, $before, $after, [
                'message' => 'Schimbare status produs: ' . $old . ' → ' . $next,
                'project_id' => $projectId,
                'old_status' => $old,
                'new_status' => $next,
            ]);
            $msg = 'Status produs actualizat.';
            if (is_array($docInfo) && isset($docInfo['deviz_number'], $docInfo['bon_number'])) {
                $msg .= ' Deviz nr. ' . $docInfo['deviz_number'] . ' și Bon consum nr. ' . $docInfo['bon_number'] . ' generate.';
            }
            Session::flash('toast_success', $msg);
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
        $ppId = (int)($ppRow['id'] ?? 0);
        if ($projectId <= 0 || $ppId <= 0) return;

        // Nou: mutăm piesele HPL rezervate pe piesă (Consum HPL) în Producție.
        try {
            $cons = ProjectProductHplConsumption::reservedForProjectProduct($projectId, $ppId);
        } catch (\Throwable $e) {
            $cons = [];
        }
        if ($cons) {
            foreach ($cons as $c) {
                $pieceId = (int)($c['stock_piece_id'] ?? 0);
                if ($pieceId <= 0) continue;
                try {
                    HplStockPiece::updateFields($pieceId, ['location' => 'Producție', 'project_id' => $projectId]);
                } catch (\Throwable $e) {}
            }
            return;
        }

        // Legacy fallback: logică veche bazată pe hpl_board_id + suprafață
        $boardId = isset($ppRow['hpl_board_id']) && $ppRow['hpl_board_id'] !== null && $ppRow['hpl_board_id'] !== '' ? (int)$ppRow['hpl_board_id'] : 0;
        if ($boardId <= 0) return;
        $stype = (string)($ppRow['surface_type'] ?? '');
        $sval = isset($ppRow['surface_value']) && $ppRow['surface_value'] !== null && $ppRow['surface_value'] !== '' ? (float)$ppRow['surface_value'] : null;
        if ($stype !== 'BOARD' || $sval === null) return;
        if (!(abs($sval - 1.0) < 1e-9 || abs($sval - 0.5) < 1e-9)) return;

        $pname = '';
        try {
            $prodId = (int)($ppRow['product_id'] ?? 0);
            if ($prodId > 0) {
                $p = Product::find($prodId);
                $pname = $p ? (string)($p['name'] ?? '') : '';
            }
        } catch (\Throwable $e) {}
        $project = null;
        try { $project = Project::find($projectId); } catch (\Throwable $e) {}
        $projLabel = self::projectLabel($project);
        $prodLabel = self::formatLabel('', $pname, 'Produs');

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            [$hmm, $wmm] = self::boardStdDimsMm($boardId);
            $halfHmm = (int)floor(((float)$hmm) / 2.0);
            $note = 'TRANSFER_CNC · Proiect: ' . $projLabel . ' · Produs: ' . $prodLabel;

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
     * Consumă accesoriile rezervate pe piesă (manual, din buton).
     * - reserved (fără scădere stoc) -> consumed (OUT din stoc + mișcare)
     * - fără notă (cerință)
     *
     * @param array<int,float> $needByItem
     */
    private static function consumeReservedMagazieForProjectProduct(int $projectId, int $projectProductId, array $needByItem): void
    {
        if ($projectId <= 0 || $projectProductId <= 0) return;
        $project = Project::find($projectId);
        if (!$project) throw new \RuntimeException('Proiect inexistent.');
        $needByItem = array_filter($needByItem, fn($v) => is_numeric($v) && (float)$v > 0.0);
        if (!$needByItem) return;

        $rows = [];
        try {
            $rows = ProjectMagazieConsumption::reservedForProjectProduct($projectId, $projectProductId);
        } catch (\Throwable $e) {
            $rows = [];
        }
        $directByItem = [];
        foreach ($rows as $r) {
            $iid = (int)($r['item_id'] ?? 0);
            $qty = isset($r['qty']) ? (float)($r['qty'] ?? 0) : 0.0;
            if ($iid <= 0 || $qty <= 0) continue;
            $directByItem[$iid] = ($directByItem[$iid] ?? 0.0) + $qty;
        }
        foreach ($directByItem as $iid => $qty) {
            if (!isset($needByItem[$iid]) || (float)$needByItem[$iid] < $qty) {
                $needByItem[$iid] = $qty;
            }
        }
        $projNeedByItem = [];
        foreach ($needByItem as $iid => $need) {
            $directQty = (float)($directByItem[$iid] ?? 0.0);
            $rem = (float)$need - $directQty;
            if ($rem > 0.000001) {
                $projNeedByItem[(int)$iid] = $rem;
            }
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            // 1) verificăm stoc suficient per item (agregat)
            $lockedItems = [];
            foreach ($needByItem as $iid => $need) {
                $beforeItem = MagazieItem::findForUpdate((int)$iid);
                if (!$beforeItem) {
                    throw new \RuntimeException('Accesoriu inexistent (id=' . (int)$iid . ').');
                }
                $stock = (float)($beforeItem['stock_qty'] ?? 0.0);
                if ((float)$need > $stock + 1e-9) {
                    $code = (string)($beforeItem['winmentor_code'] ?? '');
                    $name = (string)($beforeItem['name'] ?? '');
                    throw new \RuntimeException('Stoc insuficient pentru accesoriu: ' . trim($code . ' · ' . $name) . ' (necesar ' . number_format((float)$need, 3, '.', '') . ', stoc ' . number_format($stock, 3, '.', '') . ').');
                }
                $lockedItems[(int)$iid] = $beforeItem;
            }

            // 2) procesăm rezervările DIRECT: update mode + OUT din stoc
            foreach ($rows as $r) {
                $cid = (int)($r['id'] ?? 0);
                $iid = (int)($r['item_id'] ?? 0);
                $qty = isset($r['qty']) ? (float)($r['qty'] ?? 0) : 0.0;
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
                    'include_in_deviz' => (int)($beforeRow['include_in_deviz'] ?? 1),
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
                    'message' => 'Consum Magazie manual (buton).',
                    'project_id' => $projectId,
                    'project_product_id' => $projectProductId,
                    'item_id' => $iid,
                    'qty' => (float)$qty,
                ]);
            }

            // 3) consumăm din rezervările la nivel de proiect (PROIECT) pe baza alocării curente.
            foreach ($projNeedByItem as $iid => $need) {
                $need = (float)$need;
                if ($need <= 0) continue;
                $stProj = $pdo->prepare("
                    SELECT id, qty, unit, include_in_deviz
                    FROM project_magazie_consumptions
                    WHERE project_id = ?
                      AND (project_product_id IS NULL OR project_product_id = 0)
                      AND item_id = ?
                      AND mode = 'RESERVED'
                    ORDER BY created_at ASC, id ASC
                    FOR UPDATE
                ");
                $stProj->execute([$projectId, (int)$iid]);
                $projRows = $stProj->fetchAll();
                if (!$projRows) {
                    throw new \RuntimeException('Nu există rezervări de proiect pentru acest accesoriu.');
                }
                $remaining = $need;
                foreach ($projRows as $pr) {
                    if ($remaining <= 0.000001) break;
                    $rid = (int)($pr['id'] ?? 0);
                    $q = isset($pr['qty']) ? (float)($pr['qty'] ?? 0) : 0.0;
                    if ($rid <= 0 || $q <= 0) continue;
                    $take = min($q, $remaining);
                    $newQ = $q - $take;
                    if ($newQ <= 0.000001) {
                        $pdo->prepare("DELETE FROM project_magazie_consumptions WHERE id = ?")->execute([$rid]);
                    } else {
                        $pdo->prepare("UPDATE project_magazie_consumptions SET qty = ? WHERE id = ?")->execute([$newQ, $rid]);
                    }

                    if (!MagazieItem::adjustStock($iid, -(float)$take)) {
                        throw new \RuntimeException('Nu pot scădea stocul (concurență / stoc insuficient).');
                    }

                    $unit = (string)($pr['unit'] ?? ($lockedItems[$iid]['unit'] ?? 'buc'));
                    $cid = ProjectMagazieConsumption::create([
                        'project_id' => $projectId,
                        'project_product_id' => $projectProductId,
                        'item_id' => (int)$iid,
                        'qty' => (float)$take,
                        'unit' => $unit !== '' ? $unit : 'buc',
                        'mode' => 'CONSUMED',
                        'include_in_deviz' => (int)($pr['include_in_deviz'] ?? 1),
                        'note' => null,
                        'created_by' => Auth::id(),
                    ]);

                    $beforeItem = $lockedItems[$iid] ?? MagazieItem::findForUpdate($iid);
                    $movementId = \App\Models\MagazieMovement::create([
                        'item_id' => (int)$iid,
                        'direction' => 'OUT',
                        'qty' => (float)$take,
                        'unit_price' => ($beforeItem && isset($beforeItem['unit_price']) && $beforeItem['unit_price'] !== '' && is_numeric($beforeItem['unit_price'])) ? (float)$beforeItem['unit_price'] : null,
                        'project_id' => $projectId,
                        'project_code' => (string)($project['code'] ?? null),
                        'note' => null,
                        'created_by' => Auth::id(),
                    ]);
                    $afterItem = MagazieItem::findForUpdate((int)$iid) ?: $beforeItem;
                    if ($beforeItem) {
                        Audit::log('MAGAZIE_OUT', 'magazie_items', (int)$iid, $beforeItem, $afterItem, [
                            'movement_id' => $movementId,
                            'project_id' => $projectId,
                            'project_code' => (string)($project['code'] ?? ''),
                            'project_product_id' => $projectProductId,
                            'qty' => (float)$take,
                        ]);
                    }
                    Audit::log('PROJECT_CONSUMPTION_CREATE', 'project_magazie_consumptions', $cid, null, null, [
                        'message' => 'Consum Magazie manual (buton).',
                        'project_id' => $projectId,
                        'project_product_id' => $projectProductId,
                        'item_id' => (int)$iid,
                        'qty' => (float)$take,
                        'unit' => $unit,
                    ]);

                    $remaining -= $take;
                }
                if ($remaining > 0.000001) {
                    throw new \RuntimeException('Rezervarea de proiect este insuficientă pentru cantitatea cerută.');
                }
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
        $projLabel = self::projectLabel($project);
        $prodLabel = self::formatLabel('', $pname, 'Produs');
        $projNote = 'Proiect: ' . $projLabel;
        $prodNote = 'Produs: ' . $prodLabel;

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
                    self::moveFullBoards($boardId, 1, 'RESERVED', 'RESERVED', 'TRANSFER_MONTAJ · ' . $projNote . ' · ' . $prodNote, $projectId, 'Depozit', 'Producție');
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
                        self::HPL_NOTE_AUTO_CONSUME . ' · 1 placă · ' . $projNote . ' · ' . $prodNote,
                        $projectId,
                        'Producție',
                        'Producție'
                    );
                } catch (\Throwable $e) {
                    // fallback: dacă nu era în Producție, încercăm să transferăm și apoi să consumăm.
                    try {
                        self::moveFullBoards($boardId, 1, 'RESERVED', 'RESERVED', 'TRANSFER_MONTAJ · ' . $projNote . ' · ' . $prodNote, $projectId, 'Depozit', 'Producție');
                        self::moveFullBoards(
                            $boardId,
                            1,
                            'RESERVED',
                            'CONSUMED',
                            self::HPL_NOTE_AUTO_CONSUME . ' · 1 placă · ' . $projNote . ' · ' . $prodNote,
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
                    self::HPL_NOTE_AUTO_CONSUME . ' · 1 placă · ' . $projNote . ' · ' . $prodNote, Auth::id());
                Audit::log('HPL_STOCK_CONSUME', 'hpl_boards', $boardId, null, null, [
                        'message' => 'Consum HPL auto: 1 placă (CNC → Montaj) · produs ' . $prodLabel .
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
                    try { self::moveReservedOffcutHalfToLocation($pdo, $projectId, $boardId, $halfHmm, (int)$wmm, 1, 'Depozit', 'Producție', 'TRANSFER_MONTAJ · ' . $projNote . ' · ' . $prodNote); } catch (\Throwable $e) {}
                    // consumăm 1 buc dintr-un offcut rezervat (jumătate), dacă există
                    if (!self::consumeReservedHalfOffcut($pdo, $projectId, $boardId, $halfHmm, (int)$wmm,
                        self::HPL_NOTE_AUTO_CONSUME . ' · 1/2 placă (din rest) · ' . $projNote . ' · ' . $prodNote)) {
                        $pdo->rollBack();
                        return 'Nu pot consuma 1/2 placă din Producție. Verifică dacă restul (jumătate) a fost mutat în Producție.';
                    }
                    self::insertProjectHplConsumption($pdo, $projectId, $boardId, 0, $halfM2, 'CONSUMED',
                        self::HPL_NOTE_AUTO_CONSUME . ' · 1/2 placă (din rest) · ' . $projNote . ' · ' . $prodNote, Auth::id());
                    Audit::log('HPL_STOCK_CONSUME', 'hpl_boards', $boardId, null, null, [
                        'message' => 'Consum HPL auto: 1/2 placă (din rest) (CNC → Montaj) · produs ' . $prodLabel .
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
                    try { self::moveFullBoards($boardId, 1, 'RESERVED', 'RESERVED', 'TRANSFER_MONTAJ · ' . $projNote . ' · ' . $prodNote, $projectId, 'Depozit', 'Producție'); } catch (\Throwable $e) {}
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
                    $remNote = self::HPL_NOTE_HALF_REMAINDER . ' · 1 buc · ' . $halfHmm . '×' . (int)$wmm . ' mm · ' . $projNote . ' · ' . $prodNote;
                    $consNote = self::HPL_NOTE_AUTO_CONSUME . ' · 1 buc · ' . $halfHmm . '×' . (int)$wmm . ' mm · rest=' . $ra . ' · ' . $projNote . ' · ' . $prodNote;
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
                        'message' => 'Consum HPL auto: 1/2 placă (tăiere din FULL) (CNC → Montaj) · produs ' . $prodLabel .
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

    /**
     * Consum HPL automat când piesa trece la "Gata de livrare".
     * Consumă din piesele rezervate pe piesă (project_product_hpl_consumptions).
     */
    private static function autoConsumeHplOnReadyToDeliver(int $projectId, array $ppRow): ?string
    {
        $ppId = (int)($ppRow['id'] ?? 0);
        if ($projectId <= 0 || $ppId <= 0) return null;

        $rows = [];
        try {
            $rows = ProjectProductHplConsumption::reservedForProjectProduct($projectId, $ppId);
        } catch (\Throwable $e) {
            $rows = [];
        }
        if (!$rows) return null;

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                $cid = (int)($r['id'] ?? 0);
                $pieceId = (int)($r['stock_piece_id'] ?? 0);
                $boardId = (int)($r['board_id'] ?? 0);
                $src = (string)($r['source'] ?? 'PROJECT');
                $mode = (string)($r['consume_mode'] ?? 'FULL');
                if ($cid <= 0 || $pieceId <= 0 || $boardId <= 0) continue;

                $res = self::consumeHplPieceForProduct($pdo, $projectId, $ppId, $pieceId, $boardId, $src, $mode);
                $err = $res['error'] ?? null;
                $consumedPieceId = isset($res['consumed_piece_id']) ? (int)($res['consumed_piece_id'] ?? 0) : 0;
                if ($err !== null) {
                    $pdo->rollBack();
                    return $err;
                }
                if ($consumedPieceId > 0) {
                    try { ProjectProductHplConsumption::setConsumedPiece($cid, $consumedPieceId); } catch (\Throwable $e) {}
                }
                ProjectProductHplConsumption::markConsumed($cid);
            }
            $pdo->commit();
            return null;
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            return 'Nu pot consuma HPL: ' . $e->getMessage();
        }
    }

    private static function consumeHplPieceForProduct(
        \PDO $pdo,
        int $projectId,
        int $projectProductId,
        int $pieceId,
        int $boardId,
        string $source,
        string $consumeMode
    ): array {
        $pieceId = (int)$pieceId;
        if ($pieceId <= 0) return ['error' => 'Piesă HPL invalidă.', 'consumed_piece_id' => null];
        $consumeMode = strtoupper(trim($consumeMode));
        if ($consumeMode !== 'FULL' && $consumeMode !== 'HALF') $consumeMode = 'FULL';
        $source = strtoupper(trim($source));
        if ($source !== 'PROJECT' && $source !== 'REST') $source = 'PROJECT';
        if ($source === 'REST') $consumeMode = 'FULL';

        $project = null;
        $pp = null;
        try { $project = Project::find($projectId); } catch (\Throwable $e) {}
        try { $pp = ProjectProduct::find($projectProductId); } catch (\Throwable $e) {}
        $projLabel = self::projectLabel($project);
        $prodLabel = self::productLabelFromProjectProduct($pp);
        $projNote = 'Proiect: ' . $projLabel;
        $prodNote = 'Produs: ' . $prodLabel;

        $st = $pdo->prepare("SELECT * FROM hpl_stock_pieces WHERE id = ? FOR UPDATE");
        $st->execute([$pieceId]);
        $p = $st->fetch();
        if (!$p) return ['error' => 'Piesă HPL inexistentă.', 'consumed_piece_id' => null];

        $qty = (int)($p['qty'] ?? 0);
        if ($qty <= 0) return ['error' => 'Stoc insuficient.', 'consumed_piece_id' => null];
        $pt = (string)($p['piece_type'] ?? '');
        $status = (string)($p['status'] ?? '');
        $loc = (string)($p['location'] ?? '');
        $isAcc = (int)($p['is_accounting'] ?? 1);
        $w = (int)($p['width_mm'] ?? 0);
        $h = (int)($p['height_mm'] ?? 0);

        // mutăm în Producție înainte de consum
        if ($loc !== 'Producție') {
            try { HplStockPiece::updateFields($pieceId, ['location' => 'Producție', 'project_id' => $projectId]); } catch (\Throwable $e) {}
            $loc = 'Producție';
        }

        if ($consumeMode === 'HALF' && $pt === 'FULL') {
            if ($status !== 'RESERVED') return ['error' => 'Placa FULL trebuie să fie rezervată înainte de consum.', 'consumed_piece_id' => null];
            if ($w <= 0 || $h <= 0) return ['error' => 'Dimensiuni invalide.', 'consumed_piece_id' => null];
            $halfH = (int)floor($h / 2);
            if ($halfH <= 0) return ['error' => 'Nu pot calcula jumătate de placă.', 'consumed_piece_id' => null];

            // scoatem 1 placă FULL
            if ($qty === 1) HplStockPiece::delete($pieceId);
            else HplStockPiece::updateQty($pieceId, $qty - 1);

            $noteBase = 'Consum HPL 1/2 · ' . $projNote . ' · ' . $prodNote;
            if ($source === 'REST') $noteBase = 'Consum HPL REST 1/2 · ' . $projNote . ' · ' . $prodNote;

            // jumătatea consumată
            $consumedId = HplStockPiece::create([
                'board_id' => $boardId,
                'project_id' => $projectId,
                'is_accounting' => $isAcc,
                'piece_type' => 'OFFCUT',
                'status' => 'CONSUMED',
                'width_mm' => $w,
                'height_mm' => $halfH,
                'qty' => 1,
                'location' => $loc,
                'notes' => $noteBase,
            ]);
            // rest jumătate rămâne rezervat în proiect
            HplStockPiece::create([
                'board_id' => $boardId,
                'project_id' => $projectId,
                'is_accounting' => $isAcc,
                'piece_type' => 'OFFCUT',
                'status' => 'RESERVED',
                'width_mm' => $w,
                'height_mm' => $halfH,
                'qty' => 1,
                'location' => $loc,
                'notes' => 'Rest jumătate (din ' . $noteBase . ')',
            ]);

            Audit::log('PROJECT_PRODUCT_HPL_CONSUME', 'project_products', $projectProductId, null, null, [
                'project_id' => $projectId,
                'board_id' => $boardId,
                'consume_mode' => 'HALF',
                'source' => $source,
            ]);
            return ['error' => null, 'consumed_piece_id' => (int)$consumedId];
        }

        // FULL (sau OFFCUT)
        $take = 1;
        if ($take > $qty) return ['error' => 'Stoc insuficient (buc).', 'consumed_piece_id' => null];

        $note = 'Consum HPL · ' . $projNote . ' · ' . $prodNote;
        if ($source === 'REST') $note = 'Consum HPL REST · ' . $projNote . ' · ' . $prodNote;

        if ($qty === $take) {
            HplStockPiece::updateFields($pieceId, ['status' => 'CONSUMED', 'project_id' => $projectId, 'location' => $loc]);
            try { HplStockPiece::appendNote($pieceId, $note); } catch (\Throwable $e) {}
            $consumedId = $pieceId;
        } else {
            HplStockPiece::updateQty($pieceId, $qty - $take);
            $consumedId = HplStockPiece::create([
                'board_id' => $boardId,
                'project_id' => $projectId,
                'is_accounting' => $isAcc,
                'piece_type' => $pt !== '' ? $pt : 'FULL',
                'status' => 'CONSUMED',
                'width_mm' => $w,
                'height_mm' => $h,
                'qty' => $take,
                'location' => $loc,
                'notes' => $note,
            ]);
        }

        Audit::log('PROJECT_PRODUCT_HPL_CONSUME', 'project_products', $projectProductId, null, null, [
            'project_id' => $projectId,
            'stock_piece_id' => $pieceId,
            'board_id' => $boardId,
            'consume_mode' => 'FULL',
            'source' => $source,
        ]);
        return ['error' => null, 'consumed_piece_id' => isset($consumedId) ? (int)$consumedId : null];
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
        $consumTab = trim((string)($_POST['consum_tab'] ?? 'accesorii'));
        if (!in_array($consumTab, ['accesorii', 'hpl'], true)) $consumTab = 'accesorii';
        $consumRedirect = '/projects/' . $projectId . '?tab=consum' . ($consumTab !== '' ? ('&consum_tab=' . urlencode($consumTab)) : '');

        $itemId = Validator::int(trim((string)($_POST['item_id'] ?? '')), 1);
        $ppId = Validator::int(trim((string)($_POST['project_product_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        $includeInDeviz = self::includeInDevizFromPost(1);
        $mode = trim((string)($_POST['mode'] ?? 'CONSUMED'));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($itemId === null || $qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Consum invalid.');
            Response::redirect($consumRedirect);
        }
        if (!in_array($mode, ['RESERVED','CONSUMED'], true)) $mode = 'CONSUMED';
        if ($consumTab === 'accesorii') {
            $mode = 'RESERVED';
        }

        $item = MagazieItem::find((int)$itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect($consumRedirect);
        }
        $unit = trim((string)($item['unit'] ?? 'buc'));

        // dacă e consumat, verificăm stoc
        if ($mode === 'CONSUMED') {
            $stock = (float)($item['stock_qty'] ?? 0);
            if ($qty > $stock + 1e-9) {
                Session::flash('toast_error', 'Stoc insuficient în Magazie.');
                Response::redirect($consumRedirect);
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
                'include_in_deviz' => $includeInDeviz,
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
        Response::redirect($consumRedirect);
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
        $projLabel = self::projectLabel($project);
        $prodLabel = self::productLabelFromProjectProduct($pp);
        $projNote = 'Proiect: ' . $projLabel;
        $prodNote = 'Produs: ' . $prodLabel;
        $projLabel = self::projectLabel($project);
        $prodLabel = self::productLabelFromProjectProduct($pp);
        $projNote = 'Proiect: ' . $projLabel;
        $prodNote = 'Produs: ' . $prodLabel;
        $projLabel = self::projectLabel($project);
        $prodLabel = self::productLabelFromProjectProduct($pp);
        $projNote = 'Proiect: ' . $projLabel;
        $prodNote = 'Produs: ' . $prodLabel;
        // Cerință: după Gata de livrare, OPERATOR nu mai poate adăuga/edita consumuri pe piesă.
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($pp['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate modifica.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        $itemId = Validator::int(trim((string)($_POST['item_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        $includeInDeviz = self::includeInDevizFromPost(1);
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
                'include_in_deviz' => $includeInDeviz,
                'note' => null,
                'created_by' => Auth::id(),
            ]);
            $pdo->commit();

            Audit::log('PROJECT_CONSUMPTION_CREATE', 'project_magazie_consumptions', $cid, null, null, [
                'message' => 'Accesoriu rezervat pe produs: ' . (string)($item['winmentor_code'] ?? '') . ' · ' . (string)($item['name'] ?? ''),
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'item_id' => (int)$itemId,
                'qty' => (float)$qty,
                'unit' => $unit,
                'mode' => 'RESERVED',
            ]);
            Session::flash('toast_success', 'Accesoriu rezervat pe produs.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva accesoriul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    /**
     * Editează cantitatea de accesorii rezervate pe produs (DIRECT, mode=RESERVED).
     */
    public static function updateMagazieConsumptionForProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);
        $itemId = (int)($params['itemId'] ?? 0);
        if ($projectId <= 0 || $ppId <= 0 || $itemId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/projects');
        }
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
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($pp['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate modifica.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        $src = strtoupper(trim((string)($_POST['src'] ?? 'DIRECT')));
        if ($src !== 'DIRECT') {
            Session::flash('toast_error', 'Poți edita doar rezervările directe pe produs.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        if ($qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Cantitate invalidă.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $item = MagazieItem::find($itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("
                SELECT * FROM project_magazie_consumptions
                WHERE project_id = ?
                  AND project_product_id = ?
                  AND item_id = ?
                  AND mode = 'RESERVED'
                ORDER BY created_at ASC, id ASC
                FOR UPDATE
            ");
            $st->execute([$projectId, $ppId, $itemId]);
            $rows = $st->fetchAll();
            if (!$rows) {
                throw new \RuntimeException('Nu există rezervări directe pentru acest accesoriu.');
            }

            $beforeQty = 0.0;
            foreach ($rows as $r) {
                $beforeQty += (float)($r['qty'] ?? 0);
            }
            $first = $rows[0];
            $cid = (int)($first['id'] ?? 0);
            if ($cid <= 0) throw new \RuntimeException('Rezervare invalidă.');

            $unit = trim((string)($first['unit'] ?? (string)($item['unit'] ?? 'buc')));
            $includeInDeviz = (array_key_exists('include_in_deviz_flag', $_POST) || array_key_exists('include_in_deviz', $_POST))
                ? self::includeInDevizFromPost(1)
                : (int)($first['include_in_deviz'] ?? 1);
            ProjectMagazieConsumption::update($cid, [
                'project_product_id' => $ppId,
                'qty' => (float)$qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'mode' => 'RESERVED',
                'include_in_deviz' => $includeInDeviz,
                'note' => null,
            ]);

            if (count($rows) > 1) {
                $ids = [];
                foreach (array_slice($rows, 1) as $r) {
                    $rid = (int)($r['id'] ?? 0);
                    if ($rid > 0) $ids[] = $rid;
                }
                if ($ids) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $stDel = $pdo->prepare("DELETE FROM project_magazie_consumptions WHERE id IN ($ph)");
                    $stDel->execute($ids);
                }
            }

            $pdo->commit();
            Audit::log('PROJECT_CONSUMPTION_UPDATE', 'project_magazie_consumptions', $cid, $first, null, [
                'message' => 'Actualizare accesoriu rezervat pe produs.',
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'item_id' => $itemId,
                'qty_before' => $beforeQty,
                'qty_after' => (float)$qty,
                'unit' => $unit,
            ]);
            Session::flash('toast_success', 'Accesoriu actualizat.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot actualiza accesoriul: ' . $e->getMessage());
        }

        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    /**
     * Consumă accesoriile rezervate pe piesă (manual, din buton).
     */
    public static function consumeMagazieForProjectProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);
        if ($projectId <= 0 || $ppId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/projects');
        }
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
        // OPERATOR lock after final status
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($pp['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate modifica.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        $projectProducts = [];
        $magConsum = [];
        try { $projectProducts = ProjectProduct::forProject($projectId); } catch (\Throwable $e) { $projectProducts = []; }
        try {
            $magConsum = ProjectMagazieConsumption::forProject($projectId);
        } catch (\Throwable $e) {
            try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
            try { $magConsum = ProjectMagazieConsumption::forProject($projectId); } catch (\Throwable $e3) { $magConsum = []; }
        }
        $accBy = self::accessoriesByProductForDisplay($projectProducts, $magConsum);
        $accRows = $accBy[$ppId] ?? [];
        $needByItem = [];
        foreach ($accRows as $r) {
            if ((string)($r['mode'] ?? '') !== 'RESERVED') continue;
            $iid = (int)($r['item_id'] ?? 0);
            if ($iid <= 0) continue;
            $qty = isset($r['qty']) ? (float)($r['qty'] ?? 0) : 0.0;
            if ($qty <= 0) continue;
            $needByItem[$iid] = ($needByItem[$iid] ?? 0.0) + $qty;
        }
        if (!$needByItem) {
            Session::flash('toast_error', 'Nu există accesorii rezervate pentru consum.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            self::consumeReservedMagazieForProjectProduct($projectId, $ppId, $needByItem);
            Session::flash('toast_success', 'Accesoriile au fost date în consum.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot da în consum accesoriile: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    /**
     * Renunță la accesorii rezervate pe piesă (DIRECT, mode=RESERVED).
     * - șterge rezervările pentru item_id pe piesa de proiect
     * - nu afectează stocul (rezervarea nu a scăzut stocul)
     */
    public static function unallocateMagazieForProjectProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);
        $itemId = (int)($params['itemId'] ?? 0);

        if ($projectId <= 0 || $ppId <= 0 || $itemId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/projects');
        }
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

        // OPERATOR lock after final status
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($pp['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate modifica.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        $item = MagazieItem::find($itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $src = strtoupper(trim((string)($_POST['src'] ?? 'DIRECT')));
        $qtyReq = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        if ($qtyReq !== null && $qtyReq < 0) $qtyReq = null;

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $stSel = $pdo->prepare("
                SELECT id, qty, unit
                FROM project_magazie_consumptions
                WHERE project_id = ?
                  AND project_product_id = ?
                  AND item_id = ?
                  AND mode = 'RESERVED'
            ");
            $stSel->execute([$projectId, $ppId, $itemId]);
            $rows = $stSel->fetchAll();
            $sumQty = 0.0;
            $unit = '';
            if ($rows) {
                // DIRECT: ștergem rezervările de pe piesă (nu afectează stocul)
                foreach ($rows as $r) {
                    $sumQty += (float)($r['qty'] ?? 0);
                    $unit = (string)($r['unit'] ?? $unit);
                }
                $stDel = $pdo->prepare("
                    DELETE FROM project_magazie_consumptions
                    WHERE project_id = ?
                      AND project_product_id = ?
                      AND item_id = ?
                      AND mode = 'RESERVED'
                ");
                $stDel->execute([$projectId, $ppId, $itemId]);
            } else {
                // PROIECT: anulăm (scădem) din rezervarea la nivel de proiect cu cantitatea alocată acestei piese în UI.
                if ($src !== 'PROIECT') throw new \RuntimeException('Nu există rezervări directe de anulat.');
                if ($qtyReq === null || $qtyReq <= 0) throw new \RuntimeException('Cantitate invalidă pentru renunțare.');

                $stProj = $pdo->prepare("
                    SELECT id, qty, unit
                    FROM project_magazie_consumptions
                    WHERE project_id = ?
                      AND (project_product_id IS NULL OR project_product_id = 0)
                      AND item_id = ?
                      AND mode = 'RESERVED'
                    ORDER BY created_at ASC, id ASC
                    FOR UPDATE
                ");
                $stProj->execute([$projectId, $itemId]);
                $projRows = $stProj->fetchAll();
                if (!$projRows) throw new \RuntimeException('Nu există rezervări de proiect pentru acest accesoriu.');

                $need = (float)$qtyReq;
                $unit = (string)($projRows[0]['unit'] ?? '');
                foreach ($projRows as $r) {
                    if ($need <= 1e-9) break;
                    $rid = (int)($r['id'] ?? 0);
                    $q = (float)($r['qty'] ?? 0);
                    if ($rid <= 0 || $q <= 0) continue;
                    $take = min($q, $need);
                    $newQ = $q - $take;
                    if ($newQ <= 1e-9) {
                        $pdo->prepare("DELETE FROM project_magazie_consumptions WHERE id = ?")->execute([$rid]);
                    } else {
                        $pdo->prepare("UPDATE project_magazie_consumptions SET qty = ? WHERE id = ?")->execute([$newQ, $rid]);
                    }
                    $sumQty += $take;
                    $need -= $take;
                }
                if ($need > 1e-6) {
                    throw new \RuntimeException('Rezervarea de proiect este insuficientă pentru cantitatea cerută.');
                }
            }

            $pdo->commit();
            Audit::log('PROJECT_PRODUCT_MAGAZIE_UNALLOCATE', 'project_magazie_consumptions', 0, null, null, [
                'message' => 'Anulat accesoriu rezervat pe produs: ' . (string)($item['winmentor_code'] ?? '') . ' · ' . (string)($item['name'] ?? ''),
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'item_id' => $itemId,
                'qty' => $sumQty,
                'unit' => $unit,
                'src' => $rows ? 'DIRECT' : 'PROIECT',
            ]);
            Session::flash('toast_success', 'Rezervarea de accesoriu a fost anulată.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot anula rezervarea: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    /**
     * HPL pe piesă (alocare, fără consum automat):
     * - source=PROJECT: selectăm o piesă RESERVED din stocul proiectului (contabilă)
     *   - FULL: alocăm o placă/piesă întreagă (exclusiv)
     *   - HALF: dacă alegem dintr-o placă FULL, o împărțim în 2 jumătăți (OFFCUT):
     *     - 1 jumătate alocată piesei (stock_piece_id nou)
     *     - 1 jumătate rămasă în proiect (stoc proiect, nealocată)
     * - source=REST: selectăm o piesă AVAILABLE (is_accounting=0) și o rezervăm pe proiect (se alocă integral)
     *
     * Consumul efectiv se face manual, prin acțiunea "Debitat" (va marca piesa ca CONSUMED și va scădea din stoc).
     */
    public static function addHplConsumptionForProduct(array $params): void
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
        $projLabel = self::projectLabel($project);
        $prodLabel = self::productLabelFromProjectProduct($pp);
        $projNote = 'Proiect: ' . $projLabel;
        $prodNote = 'Produs: ' . $prodLabel;
        // lock OPERATOR after final status
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($pp['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate modifica.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        $source = strtoupper(trim((string)($_POST['source'] ?? 'PROJECT')));
        if ($source !== 'PROJECT' && $source !== 'REST') $source = 'PROJECT';
        $pieceId = Validator::int(trim((string)($_POST['piece_id'] ?? '')), 1);
        $consumeMode = strtoupper(trim((string)($_POST['consume_mode'] ?? 'FULL')));
        if ($consumeMode !== 'FULL' && $consumeMode !== 'HALF') $consumeMode = 'FULL';
        if ($source === 'REST') $consumeMode = 'FULL';
        if ($pieceId === null) {
            Session::flash('toast_error', 'Selectează o placă/piesă HPL.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        try {
            $pdo->beginTransaction();
            // Evităm dublurile: aceeași piesă HPL alocată de mai multe ori (pe orice piesă din proiect).
            try {
                $stDup = $pdo->prepare("
                    SELECT id
                    FROM project_product_hpl_consumptions
                    WHERE project_id = ?
                      AND stock_piece_id = ?
                      AND status = 'RESERVED'
                    LIMIT 1
                ");
                $stDup->execute([(int)$projectId, (int)$pieceId]);
                $dup = $stDup->fetch();
                if ($dup) {
                    $pdo->rollBack();
                    Session::flash('toast_error', 'Această piesă HPL este deja alocată pe o piesă din proiect.');
                    Response::redirect('/projects/' . $projectId . '?tab=products');
                }
            } catch (\Throwable $e) {
                // compat: dacă tabela nu există, continuăm (se va crea mai jos)
            }
            $st = $pdo->prepare("SELECT sp.* FROM hpl_stock_pieces sp WHERE sp.id = ? FOR UPDATE");
            $st->execute([(int)$pieceId]);
            $piece = $st->fetch();
            if (!$piece) throw new \RuntimeException('Piesă HPL inexistentă.');
            $boardId = (int)($piece['board_id'] ?? 0);
            $qty = (int)($piece['qty'] ?? 0);
            $status = (string)($piece['status'] ?? '');
            $proj = isset($piece['project_id']) && $piece['project_id'] !== null && $piece['project_id'] !== '' ? (int)$piece['project_id'] : 0;
            $isAcc = (int)($piece['is_accounting'] ?? 1);
            $ptype = (string)($piece['piece_type'] ?? '');
            $w = (int)($piece['width_mm'] ?? 0);
            $h = (int)($piece['height_mm'] ?? 0);
            $loc = (string)($piece['location'] ?? '');
            if ($boardId <= 0 || $qty <= 0) throw new \RuntimeException('Stoc insuficient.');

            if ($source === 'PROJECT') {
                // trebuie să fie RESERVED în proiect
                if ($status !== 'RESERVED') throw new \RuntimeException('Piesa selectată nu este rezervată.');
                if ($proj !== $projectId) {
                    // compat: rezervări vechi fără project_id -> acceptăm dacă notele menționează proiectul
                    $notes = (string)($piece['notes'] ?? '');
                    $notesL = mb_strtolower($notes);
                    $ok = str_contains($notesL, 'proiect ' . (string)$projectId) || str_contains($notesL, 'proiect #' . (string)$projectId);
                    $projCode = trim((string)($project['code'] ?? ''));
                    $projName = trim((string)($project['name'] ?? ''));
                    if (!$ok && $projCode !== '') {
                        $projCodeL = mb_strtolower($projCode);
                        $ok = str_contains($notesL, 'proiect: ' . $projCodeL) || str_contains($notesL, 'proiect ' . $projCodeL);
                    }
                    if (!$ok && $projName !== '') {
                        $projNameL = mb_strtolower($projName);
                        $ok = str_contains($notesL, 'proiect: ' . $projNameL) || str_contains($notesL, 'proiect ' . $projNameL);
                    }
                    if (!$ok) {
                        throw new \RuntimeException('Piesa selectată nu aparține acestui proiect.');
                    }
                }
                // Dacă selectăm o piesă deja tăiată (OFFCUT), nu are sens HALF -> forțăm FULL (consum integral pe piesă).
                if ($ptype !== 'FULL') $consumeMode = 'FULL';
            } else {
                // REST: trebuie să fie FULL, AVAILABLE, is_accounting=0
                if ($isAcc !== 0) throw new \RuntimeException('Piesa REST trebuie să fie „nestocată”.');
                if ($status !== 'AVAILABLE') throw new \RuntimeException('Placa REST nu este disponibilă.');
                // rezervăm pe proiect ca să nu mai fie disponibilă
                $restNote = 'REST rezervat · ' . $projNote . ' · ' . $prodNote;
                HplStockPiece::updateFields((int)$pieceId, ['status' => 'RESERVED', 'project_id' => $projectId, 'notes' => $restNote]);
            }

            // Pentru alocare, lucrăm ideal cu qty=1 per rând (mai simplu pentru FULL/HALF).
            // Dacă rândul are qty > 1, extragem o singură bucată într-un rând separat și alocăm acea bucată.
            if ($qty > 1) {
                // decrementăm sursa
                HplStockPiece::updateQty((int)$pieceId, $qty - 1);
                // creăm un rând nou pentru 1 bucată identică
                $newId = HplStockPiece::create([
                    'board_id' => $boardId,
                    'project_id' => ($source === 'PROJECT') ? $projectId : ($source === 'REST' ? $projectId : null),
                    'is_accounting' => $isAcc,
                    'piece_type' => ($ptype !== '' ? $ptype : 'FULL'),
                    'status' => ($source === 'REST') ? 'RESERVED' : $status,
                    'width_mm' => $w,
                    'height_mm' => $h,
                    'qty' => 1,
                    'location' => $loc,
                    'notes' => (string)($piece['notes'] ?? ''),
                ]);
                // continuăm pe noua bucată
                $pieceId = $newId;
                $qty = 1;
            }

            $allocPieceId = (int)$pieceId;
            if ($source === 'PROJECT') {
                // Asigurăm legătura vizibilă în "stoc proiect"
                try { HplStockPiece::updateFields((int)$allocPieceId, ['project_id' => $projectId, 'status' => 'RESERVED']); } catch (\Throwable $e) {}

                if ($consumeMode === 'HALF') {
                    if ($ptype !== 'FULL') throw new \RuntimeException('Poți alege 1/2 doar dintr-o placă FULL.');
                    if ($h <= 0 || $w <= 0) throw new \RuntimeException('Dimensiuni placă invalide.');
                    $halfH = (int)floor(((float)$h) / 2.0);
                    if ($halfH <= 0) throw new \RuntimeException('Nu pot calcula jumătatea de placă.');

                    // Transformăm piesa curentă în "jumătatea rămasă" (OFFCUT)
                    $stUpd = $pdo->prepare("UPDATE hpl_stock_pieces SET piece_type='OFFCUT', height_mm=?, width_mm=?, qty=1 WHERE id=?");
                    $stUpd->execute([$halfH, $w, (int)$allocPieceId]);
                    try { HplStockPiece::appendNote((int)$allocPieceId, 'Jumătate rămasă (alocare) · ' . $projNote . ' · ' . $prodNote); } catch (\Throwable $e) {}

                    // Creăm "jumătatea alocată" (OFFCUT) ca piesă separată pentru această piesă de proiect.
                    $allocPieceId = HplStockPiece::create([
                        'board_id' => $boardId,
                        'project_id' => $projectId,
                        'is_accounting' => $isAcc,
                        'piece_type' => 'OFFCUT',
                        'status' => 'RESERVED',
                        'width_mm' => $w,
                        'height_mm' => $halfH,
                        'qty' => 1,
                        'location' => $loc,
                        'notes' => null,
                    ]);
                    try { HplStockPiece::appendNote((int)$allocPieceId, 'Alocat (1/2) · ' . $projNote . ' · ' . $prodNote); } catch (\Throwable $e) {}
                } else {
                    try { HplStockPiece::appendNote((int)$allocPieceId, 'Alocat (FULL) · ' . $projNote . ' · ' . $prodNote); } catch (\Throwable $e) {}
                }
            } else {
                // REST: alocăm integral piesa selectată (rămâne RESERVED pe proiect până la "Debitat")
                try { HplStockPiece::updateFields((int)$allocPieceId, ['project_id' => $projectId, 'status' => 'RESERVED']); } catch (\Throwable $e) {}
                try { HplStockPiece::appendNote((int)$allocPieceId, 'Alocat (REST) · ' . $projNote . ' · ' . $prodNote); } catch (\Throwable $e) {}
            }

            // Compat: dacă tabela nu există încă, încercăm auto-migrate și reîncercăm.
            try {
                $cid = ProjectProductHplConsumption::create([
                    'project_id' => $projectId,
                    'project_product_id' => $ppId,
                    'board_id' => $boardId,
                    'stock_piece_id' => (int)$allocPieceId,
                    'source' => $source,
                    'consume_mode' => $consumeMode,
                    'status' => 'RESERVED',
                    'created_by' => Auth::id(),
                ]);
            } catch (\Throwable $e) {
                try { \App\Core\DbMigrations::runAuto(); } catch (\Throwable $e2) {}
                $cid = ProjectProductHplConsumption::create([
                    'project_id' => $projectId,
                    'project_product_id' => $ppId,
                    'board_id' => $boardId,
                    'stock_piece_id' => (int)$allocPieceId,
                    'source' => $source,
                    'consume_mode' => $consumeMode,
                    'status' => 'RESERVED',
                    'created_by' => Auth::id(),
                ]);
            }
            Audit::log('PROJECT_PRODUCT_HPL_RESERVE', 'project_product_hpl_consumptions', $cid, null, null, [
                'message' => 'HPL alocat pe produs (' . $source . ', ' . $consumeMode . ')',
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'stock_piece_id' => (int)$allocPieceId,
                'board_id' => $boardId,
                'source' => $source,
                'consume_mode' => $consumeMode,
            ]);

            $pdo->commit();
            Session::flash('toast_success', 'HPL alocat pe produs.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva consumul HPL: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    /**
     * "Debitat" HPL pe piesă (consum efectiv):
     * - marchează alocarea ca CONSUMED
     * - scade din stoc (RESERVED -> CONSUMED) pentru piesa HPL alocată
     */
    public static function cutHplForProjectProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);
        $cid = (int)($params['cid'] ?? 0);

        if ($projectId <= 0 || $ppId <= 0 || $cid <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/projects');
        }
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

        $projLabel = self::projectLabel($project);
        $prodLabel = self::productLabelFromProjectProduct($pp);
        $projNote = 'Proiect: ' . $projLabel;
        $prodNote = 'Produs: ' . $prodLabel;

        // lock OPERATOR after final status
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($pp['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate modifica.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            // lock consumption
            $stC = $pdo->prepare("SELECT * FROM project_product_hpl_consumptions WHERE id = ? FOR UPDATE");
            $stC->execute([(int)$cid]);
            $c = $stC->fetch();
            if (!$c) throw new \RuntimeException('Alocare HPL inexistentă.');
            if ((int)($c['project_id'] ?? 0) !== $projectId || (int)($c['project_product_id'] ?? 0) !== $ppId) {
                throw new \RuntimeException('Alocare HPL invalidă.');
            }
            if ((string)($c['status'] ?? '') !== 'RESERVED') {
                throw new \RuntimeException('Această alocare este deja debitată.');
            }

            $pieceId = (int)($c['stock_piece_id'] ?? 0);
            if ($pieceId <= 0) throw new \RuntimeException('Piesa HPL alocată lipsește.');

            // lock stock piece
            $stP = $pdo->prepare("SELECT * FROM hpl_stock_pieces WHERE id = ? FOR UPDATE");
            $stP->execute([(int)$pieceId]);
            $p = $stP->fetch();
            if (!$p) throw new \RuntimeException('Piesa HPL alocată nu mai există în stoc.');
            $qty = (int)($p['qty'] ?? 0);
            if ($qty <= 0) throw new \RuntimeException('Stoc insuficient pentru debitare.');
            $status = (string)($p['status'] ?? '');
            if ($status !== 'RESERVED') throw new \RuntimeException('Piesa HPL nu este rezervată.');

            $boardId = (int)($p['board_id'] ?? 0);
            $ptype = (string)($p['piece_type'] ?? '');
            $w = (int)($p['width_mm'] ?? 0);
            $h = (int)($p['height_mm'] ?? 0);
            $loc = (string)($p['location'] ?? '');
            $isAcc = (int)($p['is_accounting'] ?? 1);

            $consumedPieceId = $pieceId;
            // Cerință: la "Debitat" trecem materialul în Producție și apoi îl consumăm,
            // astfel încât consumul să apară pe locația Producție.
            $prodLoc = 'Producție';
            $noteMove = 'TRANSFER · Debitat -> Producție · ' . $projNote . ' · ' . $prodNote;
            $note = 'CONSUMED · Debitat · ' . $projNote . ' · ' . $prodNote;

            if ($qty === 1) {
                $pdo->prepare("UPDATE hpl_stock_pieces SET location=?, status='CONSUMED' WHERE id=?")->execute([$prodLoc, (int)$pieceId]);
                try { HplStockPiece::appendNote((int)$pieceId, $noteMove); } catch (\Throwable $e) {}
                try { HplStockPiece::appendNote((int)$pieceId, $note); } catch (\Throwable $e) {}
            } else {
                // decrement reserved qty, create a new consumed row (qty=1) for trasabilitate
                $pdo->prepare("UPDATE hpl_stock_pieces SET location=?, qty = qty - 1 WHERE id=?")->execute([$prodLoc, (int)$pieceId]);
                try { HplStockPiece::appendNote((int)$pieceId, $noteMove); } catch (\Throwable $e) {}
                $consumedPieceId = HplStockPiece::create([
                    'board_id' => $boardId,
                    'project_id' => $projectId,
                    'is_accounting' => $isAcc,
                    'piece_type' => ($ptype !== '' ? $ptype : 'OFFCUT'),
                    'status' => 'CONSUMED',
                    'width_mm' => $w,
                    'height_mm' => $h,
                    'qty' => 1,
                    'location' => $prodLoc,
                    'notes' => $note,
                ]);
            }

            ProjectProductHplConsumption::markConsumed((int)$cid);
            ProjectProductHplConsumption::setConsumedPiece((int)$cid, (int)$consumedPieceId);

            Audit::log('PROJECT_PRODUCT_HPL_CONSUME', 'project_product_hpl_consumptions', $cid, null, null, [
                'message' => 'HPL debitat pe produs.',
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'board_id' => (int)($c['board_id'] ?? 0),
                'stock_piece_id' => $pieceId,
                'consumed_piece_id' => (int)$consumedPieceId,
                'source' => (string)($c['source'] ?? ''),
                'consume_mode' => (string)($c['consume_mode'] ?? ''),
            ]);
            if ($boardId > 0) {
                Audit::log('HPL_STOCK_CONSUME', 'hpl_boards', $boardId, null, null, [
                    'message' => 'Consumat (Debitat) pe produs ' . $prodLabel . ' · Proiect ' . $projLabel,
                    'board_id' => $boardId,
                    'project_id' => $projectId,
                    'project_code' => (string)($project['code'] ?? ''),
                    'project_name' => (string)($project['name'] ?? ''),
                    'project_product_id' => $ppId,
                ]);
            }

            $pdo->commit();
            Session::flash('toast_success', 'HPL debitat (consumat).');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot debita HPL: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    /**
     * Renunță la o alocare HPL pe piesă (status=RESERVED).
     * - șterge rândul din project_product_hpl_consumptions
     * - eliberează piesa:
     *   - contabilă (is_accounting=1): rămâne RESERVED în proiect (devine disponibilă pentru alte piese)
     *   - REST (is_accounting=0): revine AVAILABLE în stoc (Depozit), project_id=NULL (cu merge dacă există piesă identică)
     */
    public static function unallocateHplForProjectProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);
        $cid = (int)($params['cid'] ?? 0);

        if ($projectId <= 0 || $ppId <= 0 || $cid <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/projects');
        }
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

        // OPERATOR lock after final status
        $u = Auth::user();
        if ($u && (string)($u['role'] ?? '') === Auth::ROLE_OPERATOR) {
            $st = (string)($pp['production_status'] ?? 'CREAT');
            if (self::isFinalProductStatus($st)) {
                Session::flash('toast_error', 'Produsul este definitivat (Gata de livrare/Avizare/Livrat). Doar Admin/Gestionar poate modifica.');
                Response::redirect('/projects/' . $projectId . '?tab=products');
            }
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $stC = $pdo->prepare("SELECT * FROM project_product_hpl_consumptions WHERE id = ? FOR UPDATE");
            $stC->execute([(int)$cid]);
            $c = $stC->fetch();
            if (!$c) throw new \RuntimeException('Alocare HPL inexistentă.');
            if ((int)($c['project_id'] ?? 0) !== $projectId || (int)($c['project_product_id'] ?? 0) !== $ppId) {
                throw new \RuntimeException('Alocare HPL invalidă.');
            }
            if ((string)($c['status'] ?? '') !== 'RESERVED') {
                throw new \RuntimeException('Poți renunța doar la alocări rezervate (neconsumate).');
            }
            $pieceId = (int)($c['stock_piece_id'] ?? 0);
            if ($pieceId <= 0) throw new \RuntimeException('Piesa HPL alocată lipsește.');

            $stP = $pdo->prepare("SELECT * FROM hpl_stock_pieces WHERE id = ? FOR UPDATE");
            $stP->execute([(int)$pieceId]);
            $p = $stP->fetch();
            if (!$p) throw new \RuntimeException('Piesa HPL alocată nu mai există în stoc.');

            $isAcc = (int)($p['is_accounting'] ?? 1);
            $boardId = (int)($p['board_id'] ?? 0);
            $ptype = (string)($p['piece_type'] ?? '');
            $w = (int)($p['width_mm'] ?? 0);
            $h = (int)($p['height_mm'] ?? 0);
            $qty = (int)($p['qty'] ?? 0);
            $loc = (string)($p['location'] ?? '');

            // delete allocation row first (so Select2 sees it as available again)
            $pdo->prepare("DELETE FROM project_product_hpl_consumptions WHERE id = ?")->execute([(int)$cid]);

            if ($isAcc === 0) {
                // REST -> return to general stock
                $note = 'Revenire (renunțat) · ' . $projNote . ' · ' . $prodNote;
                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, $ptype !== '' ? $ptype : 'OFFCUT', 'AVAILABLE', $w, $h, 'Depozit', 0, null, $pieceId);
                } catch (\Throwable $e) {
                    $ident = null;
                }
                if ($ident && (int)($ident['id'] ?? 0) > 0) {
                    HplStockPiece::incrementQty((int)$ident['id'], max(1, $qty));
                    try { HplStockPiece::appendNote((int)$ident['id'], $note); } catch (\Throwable $e) {}
                    HplStockPiece::delete($pieceId);
                } else {
                    HplStockPiece::updateFields($pieceId, ['status' => 'AVAILABLE', 'project_id' => null, 'location' => 'Depozit']);
                    try { HplStockPiece::appendNote($pieceId, $note); } catch (\Throwable $e) {}
                }
            } else {
                // accounting: keep RESERVED for project, but make it "unallocated" (merge with identical if possible)
                $ident = null;
                try {
                    $ident = HplStockPiece::findIdentical($boardId, $ptype !== '' ? $ptype : 'OFFCUT', 'RESERVED', $w, $h, $loc, 1, $projectId, $pieceId);
                } catch (\Throwable $e) {
                    $ident = null;
                }
                if ($ident && (int)($ident['id'] ?? 0) > 0) {
                    HplStockPiece::incrementQty((int)$ident['id'], max(1, $qty));
                    HplStockPiece::delete($pieceId);
                } else {
                    // ensure it's visible in project stock
                    HplStockPiece::updateFields($pieceId, ['project_id' => $projectId, 'status' => 'RESERVED']);
                }
                try { HplStockPiece::appendNote((int)$pieceId, 'Renunțat de pe produs · ' . $projNote . ' · ' . $prodNote); } catch (\Throwable $e) {}
            }

            $pdo->commit();
            Audit::log('PROJECT_PRODUCT_HPL_UNALLOCATE', 'project_product_hpl_consumptions', $cid, null, null, [
                'message' => 'Renunțat la HPL alocat pe produs (neconsumat).',
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'board_id' => (int)($c['board_id'] ?? 0),
                'stock_piece_id' => $pieceId,
                'source' => (string)($c['source'] ?? ''),
                'consume_mode' => (string)($c['consume_mode'] ?? ''),
            ]);
            Session::flash('toast_success', 'Alocarea HPL a fost anulată.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot anula alocarea HPL: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    /**
     * Revenire în stoc pentru piese HPL nestocabile (is_accounting=0) rezervate pe proiect.
     * - setează piesa AVAILABLE + project_id=NULL + locație Depozit
     * - elimină rezervarea de pe produs (project_product_hpl_consumptions RESERVED)
     */
    public static function returnRestHplToStock(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $pieceId = (int)($params['pieceId'] ?? 0);
        if ($projectId <= 0 || $pieceId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/projects');
        }
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }
        $projLabel = self::projectLabel($project);
        $consumTab = trim((string)($_POST['consum_tab'] ?? 'hpl'));
        if (!in_array($consumTab, ['accesorii', 'hpl'], true)) $consumTab = 'hpl';
        $consumRedirect = '/projects/' . $projectId . '?tab=consum' . ($consumTab !== '' ? ('&consum_tab=' . urlencode($consumTab)) : '');
        $u = Auth::user();
        $role = $u ? (string)($u['role'] ?? '') : '';
        if (!$u || !in_array($role, [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR], true)) {
            Session::flash('toast_error', 'Nu ai drepturi.');
            Response::redirect($consumRedirect);
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            // lock source
            $st = $pdo->prepare("SELECT * FROM hpl_stock_pieces WHERE id = ? FOR UPDATE");
            $st->execute([$pieceId]);
            $p = $st->fetch();
            if (!$p) throw new \RuntimeException('Piesă HPL inexistentă.');

            $isAcc = (int)($p['is_accounting'] ?? 1);
            $status = (string)($p['status'] ?? '');
            $proj = isset($p['project_id']) && $p['project_id'] !== null && $p['project_id'] !== '' ? (int)$p['project_id'] : 0;
            if ($isAcc !== 0) throw new \RuntimeException('Doar piesele nestocabile (REST) pot fi returnate din această secțiune.');
            if ($status !== 'RESERVED') throw new \RuntimeException('Piesa nu este rezervată.');
            if ($proj !== $projectId) {
                $notes = mb_strtolower((string)($p['notes'] ?? ''));
                $ok = str_contains($notes, 'proiect #' . (string)$projectId) || str_contains($notes, 'proiect ' . (string)$projectId);
                $projCode = trim((string)($project['code'] ?? ''));
                $projName = trim((string)($project['name'] ?? ''));
                if (!$ok && $projCode !== '') {
                    $projCodeL = mb_strtolower($projCode);
                    $ok = str_contains($notes, 'proiect: ' . $projCodeL) || str_contains($notes, 'proiect ' . $projCodeL);
                }
                if (!$ok && $projName !== '') {
                    $projNameL = mb_strtolower($projName);
                    $ok = str_contains($notes, 'proiect: ' . $projNameL) || str_contains($notes, 'proiect ' . $projNameL);
                }
                if (!$ok) {
                    throw new \RuntimeException('Piesa nu aparține acestui proiect.');
                }
            }

            $boardId = (int)($p['board_id'] ?? 0);
            $ptype = (string)($p['piece_type'] ?? '');
            $w = (int)($p['width_mm'] ?? 0);
            $h = (int)($p['height_mm'] ?? 0);
            $qty = (int)($p['qty'] ?? 0);
            if ($boardId <= 0 || $qty <= 0) throw new \RuntimeException('Stoc insuficient.');

            $userNote = trim((string)($_POST['note_user'] ?? ''));
            $note = $userNote;
            $noteForMatch = $note;
            if ($note === '') $noteForMatch = '';

            // merge with identical AVAILABLE row, else update in place
            $ident = null;
            try {
                $ident = HplStockPiece::findIdentical($boardId, $ptype !== '' ? $ptype : 'OFFCUT', 'AVAILABLE', $w, $h, 'Depozit', 0, null, $pieceId);
            } catch (\Throwable $e) {
                $ident = null;
            }
            if ($ident && (int)($ident['id'] ?? 0) > 0) {
                $destNote = trim((string)($ident['notes'] ?? ''));
                if ($noteForMatch === '') {
                    if ($destNote !== '') $ident = null;
                } elseif ($destNote !== $noteForMatch) {
                    $ident = null;
                }
            }
            if ($ident && (int)($ident['id'] ?? 0) > 0) {
                $destId = (int)$ident['id'];
                HplStockPiece::incrementQty($destId, $qty);
                if ($note !== '') {
                    try { HplStockPiece::appendNote($destId, $note); } catch (\Throwable $e) {}
                }
                HplStockPiece::delete($pieceId);
            } else {
                $fields = ['status' => 'AVAILABLE', 'project_id' => null, 'location' => 'Depozit'];
                $fields['notes'] = $note !== '' ? $note : null;
                HplStockPiece::updateFields($pieceId, $fields);
            }

            // eliminăm rezervările pe produse pentru această piesă (ca să nu mai fie consumată ulterior)
            try {
                $stDel = $pdo->prepare("DELETE FROM project_product_hpl_consumptions WHERE project_id = ? AND stock_piece_id = ? AND status = 'RESERVED'");
                $stDel->execute([$projectId, $pieceId]);
            } catch (\Throwable $e) {
                // compat: tabela poate lipsi
            }

            Audit::log('PROJECT_HPL_REST_RETURN', 'hpl_stock_pieces', $pieceId, null, null, [
                'message' => 'Revenire HPL REST în stoc din proiect.',
                'project_id' => $projectId,
                'board_id' => $boardId,
                'piece_type' => $ptype,
                'qty' => $qty,
            ]);

            $pdo->commit();
            Session::flash('toast_success', 'Placa/piesa REST a fost returnată în stoc.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot face revenirea: ' . $e->getMessage());
        }
        Response::redirect($consumRedirect);
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
        $consumTab = trim((string)($_POST['consum_tab'] ?? 'hpl'));
        if (!in_array($consumTab, ['accesorii', 'hpl'], true)) $consumTab = 'hpl';
        $consumRedirect = '/projects/' . $projectId . '?tab=consum' . ($consumTab !== '' ? ('&consum_tab=' . urlencode($consumTab)) : '');

        $boardId = Validator::int(trim((string)($_POST['board_id'] ?? '')), 1);
        $qtyBoards = Validator::int(trim((string)($_POST['qty_boards'] ?? '')), 1);
        $mode = trim((string)($_POST['mode'] ?? 'RESERVED'));
        $note = trim((string)($_POST['note'] ?? ''));
        $offcutDim = trim((string)($_POST['offcut_dim'] ?? ''));
        $offcutW = 0;
        $offcutH = 0;
        if ($offcutDim !== '') {
            if (!preg_match('/^(\d{1,6})[xX×](\d{1,6})$/', $offcutDim, $m)) {
                Session::flash('toast_error', 'Dimensiune rest invalidă.');
                Response::redirect($consumRedirect);
            }
            $offcutW = (int)($m[1] ?? 0);
            $offcutH = (int)($m[2] ?? 0);
            if ($offcutW <= 0 || $offcutH <= 0) {
                Session::flash('toast_error', 'Dimensiune rest invalidă.');
                Response::redirect($consumRedirect);
            }
        }
        if ($boardId === null || $qtyBoards === null || $qtyBoards <= 0) {
            Session::flash('toast_error', 'Consum HPL invalid.');
            Response::redirect($consumRedirect);
        }
        if (!in_array($mode, ['RESERVED','CONSUMED'], true)) $mode = 'RESERVED';

        try {
            $board = HplBoard::find((int)$boardId);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot încărca placa HPL.');
            Response::redirect($consumRedirect);
        }
        if (!$board) {
            Session::flash('toast_error', 'Placă HPL inexistentă.');
            Response::redirect($consumRedirect);
        }

        $isOffcut = ($offcutW > 0 && $offcutH > 0);
        if ($isOffcut) {
            $stockOffcut = 0;
            try {
                $stockOffcut = self::countOffcutAvailableByDim((int)$boardId, (int)$offcutW, (int)$offcutH);
            } catch (\Throwable $e) {
                Session::flash('toast_error', 'Nu pot calcula stocul HPL (resturi).');
                Response::redirect($consumRedirect);
            }
            if ($qtyBoards > $stockOffcut) {
                Session::flash('toast_error', 'Stoc HPL insuficient (resturi).');
                Response::redirect($consumRedirect);
            }
        } else {
            // Stoc disponibil (plăci întregi)
            $stockFull = 0;
            try {
                $stockFull = self::countFullBoardsAvailable((int)$boardId);
            } catch (\Throwable $e) {
                Session::flash('toast_error', 'Nu pot calcula stocul HPL.');
                Response::redirect($consumRedirect);
            }
            if ($qtyBoards > $stockFull) {
                Session::flash('toast_error', 'Stoc HPL insuficient (plăci întregi).');
                Response::redirect($consumRedirect);
            }
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $wStd = (int)($board['std_width_mm'] ?? 0);
            $hStd = (int)($board['std_height_mm'] ?? 0);
            if ($isOffcut) {
                $areaPer = ($offcutW > 0 && $offcutH > 0) ? (($offcutW * $offcutH) / 1000000.0) : 0.0;
                $qtyM2 = $areaPer > 0 ? ($areaPer * (float)$qtyBoards) : 0.0;
            } else {
                $areaPer = ($wStd > 0 && $hStd > 0) ? (($wStd * $hStd) / 1000000.0) : 0.0;
                $qtyM2 = $areaPer > 0 ? ($areaPer * (float)$qtyBoards) : 0.0;
            }

            $cid = ProjectHplConsumption::create([
                'project_id' => $projectId,
                'board_id' => (int)$boardId,
                'qty_boards' => $isOffcut ? 0 : (int)$qtyBoards,
                'qty_m2' => (float)$qtyM2,
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            // Actualizează stocul pe plăci întregi (AVAILABLE -> RESERVED/CONSUMED)
            $target = $mode === 'CONSUMED' ? 'CONSUMED' : 'RESERVED';
            $projCode = (string)($project['code'] ?? '');
            $projName = (string)($project['name'] ?? '');
            $projLabel = self::projectLabel($project);
            // IMPORTANT (cerință): nota afișată pe piesa din stoc trebuie să coincidă cu nota din proiect.
            // Păstrăm mesajul tehnic (proiect/consumption id) în Audit, nu în notes.
            $noteAppend = ($note !== '') ? $note : ('Proiect: ' . $projLabel . ' · consum HPL');
            if ($isOffcut) {
                self::moveOffcutPieces((int)$boardId, (int)$offcutW, (int)$offcutH, (int)$qtyBoards, 'AVAILABLE', $target, $noteAppend, $projectId);
            } else {
                self::moveFullBoards((int)$boardId, (int)$qtyBoards, 'AVAILABLE', $target, $noteAppend, $projectId);
            }

            $pdo->commit();

            $pieceLabel = $isOffcut ? ('REST ' . (int)$offcutH . '×' . (int)$offcutW) : 'FULL';
            Audit::log('PROJECT_CONSUMPTION_CREATE', 'project_hpl_consumptions', $cid, null, null, [
                'message' => 'Proiect ' . (string)($project['code'] ?? '') . ' · ' . (string)($project['name'] ?? '') . ' — HPL ' . $mode . ': ' . (string)($board['code'] ?? '') . ' · ' . (string)($board['name'] ?? '') . ' · ' . (int)$qtyBoards . ' buc (' . $pieceLabel . ')',
                'project_id' => $projectId,
                'board_id' => (int)$boardId,
                'qty_boards' => $isOffcut ? 0 : (int)$qtyBoards,
                'qty_m2' => (float)$qtyM2,
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
            ]);

            // Log explicit pe placă (pentru Istoric placă + Jurnal activitate), cu link-uri către proiect și stoc.
            Audit::log('HPL_STOCK_' . ($target === 'RESERVED' ? 'RESERVE' : 'CONSUME'), 'hpl_boards', (int)$boardId, null, null, [
                'message' => ($target === 'RESERVED' ? 'Rezervat' : 'Consumat') . ' ' . (int)$qtyBoards . ' buc (' . $pieceLabel . ') pentru Proiect: ' . (string)($project['code'] ?? '') . ' · ' . (string)($project['name'] ?? ''),
                'board_id' => (int)$boardId,
                'board_code' => (string)($board['code'] ?? ''),
                'board_name' => (string)($board['name'] ?? ''),
                'project_id' => $projectId,
                'project_code' => (string)($project['code'] ?? ''),
                'project_name' => (string)($project['name'] ?? ''),
                'consumption_id' => $cid,
                'mode' => $mode,
                'qty_boards' => $isOffcut ? 0 : (int)$qtyBoards,
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
        Response::redirect($consumRedirect);
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
        $consumTab = trim((string)($_POST['consum_tab'] ?? 'accesorii'));
        if (!in_array($consumTab, ['accesorii', 'hpl'], true)) $consumTab = 'accesorii';
        $consumRedirect = '/projects/' . $projectId . '?tab=consum' . ($consumTab !== '' ? ('&consum_tab=' . urlencode($consumTab)) : '');
        $before = ProjectMagazieConsumption::find($cid);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Consum inexistent.');
            Response::redirect($consumRedirect);
        }

        $ppId = Validator::int(trim((string)($_POST['project_product_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        $unit = trim((string)($_POST['unit'] ?? (string)($before['unit'] ?? 'buc')));
        $mode = trim((string)($_POST['mode'] ?? (string)($before['mode'] ?? 'CONSUMED')));
        $note = trim((string)($_POST['note'] ?? ''));
        $includeInDeviz = (array_key_exists('include_in_deviz_flag', $_POST) || array_key_exists('include_in_deviz', $_POST))
            ? self::includeInDevizFromPost(1)
            : (int)($before['include_in_deviz'] ?? 1);
        if ($qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Cantitate invalidă.');
            Response::redirect($consumRedirect);
        }
        if (!in_array($mode, ['RESERVED','CONSUMED'], true)) $mode = (string)($before['mode'] ?? 'CONSUMED');

        $itemId = (int)($before['item_id'] ?? 0);
        $item = MagazieItem::find($itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect($consumRedirect);
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
            Response::redirect($consumRedirect);
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
                'include_in_deviz' => $includeInDeviz,
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
        Response::redirect($consumRedirect);
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
        $consumTab = trim((string)($_POST['consum_tab'] ?? 'accesorii'));
        if (!in_array($consumTab, ['accesorii', 'hpl'], true)) $consumTab = 'accesorii';
        $consumRedirect = '/projects/' . $projectId . '?tab=consum' . ($consumTab !== '' ? ('&consum_tab=' . urlencode($consumTab)) : '');
        $before = ProjectMagazieConsumption::find($cid);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Consum inexistent.');
            Response::redirect($consumRedirect);
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
        Response::redirect($consumRedirect);
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
        $consumTab = trim((string)($_POST['consum_tab'] ?? 'hpl'));
        if (!in_array($consumTab, ['accesorii', 'hpl'], true)) $consumTab = 'hpl';
        $consumRedirect = '/projects/' . $projectId . '?tab=consum' . ($consumTab !== '' ? ('&consum_tab=' . urlencode($consumTab)) : '');
        $before = ProjectHplConsumption::find($cid);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Consum inexistent.');
            Response::redirect($consumRedirect);
        }
        try {
            // Reverse stoc (best-effort): RESERVED/CONSUMED -> AVAILABLE
            $mode = (string)($before['mode'] ?? 'RESERVED');
            $from = $mode === 'CONSUMED' ? 'CONSUMED' : 'RESERVED';
            $qtyBoards = (int)($before['qty_boards'] ?? 0);
            if ($qtyBoards > 0) {
                try {
                    self::moveFullBoards((int)($before['board_id'] ?? 0), $qtyBoards, $from, 'AVAILABLE', 'Anulare consum HPL · Proiect: ' . $projLabel, $projectId);
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
        Response::redirect($consumRedirect);
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

    public static function addProductComment(array $params): void
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
        $msg = trim((string)($_POST['comment'] ?? ''));
        if ($msg === '') {
            Session::flash('toast_error', 'Mesaj gol.');
            Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
        }
        if (mb_strlen($msg) > 4000) {
            $msg = mb_substr($msg, 0, 4000);
        }
        try {
            $cid = EntityComment::create('project_products', $ppId, $msg, Auth::id());
            if ($cid > 0) {
                Audit::log('PROJECT_PRODUCT_COMMENT_ADD', 'entity_comments', $cid, null, null, [
                    'message' => 'Observație pe produs.',
                    'project_id' => $projectId,
                    'project_product_id' => $ppId,
                ]);
                Session::flash('toast_success', 'Observație salvată.');
            } else {
                Session::flash('toast_error', 'Nu pot salva observația.');
            }
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot salva observația.');
        }
        Response::redirect('/projects/' . $projectId . '?tab=products#pp-' . $ppId);
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

