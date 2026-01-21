<?php
declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Env;
use App\Core\Response;
use PDO;

final class ConsumptionsResetController
{
    private static function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare("
            SELECT COUNT(*) AS c
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $st->execute([$table]);
        $r = $st->fetch();
        return ((int)($r['c'] ?? 0)) > 0;
    }

    private static function columnExists(PDO $pdo, string $table, string $col): bool
    {
        $st = $pdo->prepare("
            SELECT COUNT(*) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $st->execute([$table, $col]);
        $r = $st->fetch();
        return ((int)($r['c'] ?? 0)) > 0;
    }

    public static function run(): void
    {
        $token = trim((string)($_GET['token'] ?? ''));
        $expected = trim((string)Env::get('CONSUMPTIONS_RESET_TOKEN', ''));
        if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
            Response::json(['ok' => false, 'error' => 'Token invalid sau lipsă.'], 403);
        }

        $u = Auth::user();
        $role = $u ? (string)($u['role'] ?? '') : '';
        if (!in_array($role, [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER], true)) {
            Response::json(['ok' => false, 'error' => 'Acces interzis.'], 403);
        }

        $confirm = strtolower(trim((string)($_GET['confirm'] ?? '')));
        if (!in_array($confirm, ['1', 'da', 'yes', 'true'], true)) {
            Response::json(['ok' => false, 'error' => 'Confirmare lipsă. Adaugă confirm=1 la link.'], 400);
        }

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        $docFiles = [];
        try {
            $counts = [];
            if (self::tableExists($pdo, 'project_hpl_allocations')) {
                $counts['project_hpl_allocations'] = (int)$pdo->exec('DELETE FROM project_hpl_allocations');
            }
            if (self::tableExists($pdo, 'project_product_hpl_consumptions')) {
                $counts['project_product_hpl_consumptions'] = (int)$pdo->exec('DELETE FROM project_product_hpl_consumptions');
            }
            if (self::tableExists($pdo, 'project_hpl_consumptions')) {
                $counts['project_hpl_consumptions'] = (int)$pdo->exec('DELETE FROM project_hpl_consumptions');
            }
            if (self::tableExists($pdo, 'project_magazie_consumptions')) {
                $counts['project_magazie_consumptions'] = (int)$pdo->exec('DELETE FROM project_magazie_consumptions');
            }
            if (self::tableExists($pdo, 'hpl_stock_pieces')) {
                $st = $pdo->prepare("DELETE FROM hpl_stock_pieces WHERE status = 'CONSUMED'");
                $st->execute();
                $counts['hpl_stock_pieces_consumed'] = (int)$st->rowCount();
                if (self::columnExists($pdo, 'hpl_stock_pieces', 'is_accounting')) {
                    $st = $pdo->prepare("DELETE FROM hpl_stock_pieces WHERE is_accounting = 0");
                    $st->execute();
                    $counts['hpl_stock_pieces_non_stockable'] = (int)$st->rowCount();
                }
            }
            if (self::tableExists($pdo, 'magazie_movements')) {
                $st = $pdo->prepare("
                    DELETE FROM magazie_movements
                    WHERE direction IN ('OUT', 'ADJUST')
                      AND project_id IS NOT NULL
                ");
                $st->execute();
                $counts['magazie_movements'] = (int)$st->rowCount();
            }
            if (self::tableExists($pdo, 'entity_files')) {
                $st = $pdo->prepare("
                    SELECT id, stored_name
                    FROM entity_files
                    WHERE stored_name LIKE 'deviz-%-pp%.html'
                       OR stored_name LIKE 'bon-consum-%-pp%.html'
                ");
                $st->execute();
                $docFiles = $st->fetchAll() ?: [];
                $docIds = [];
                foreach ($docFiles as $f) {
                    $fid = (int)($f['id'] ?? 0);
                    if ($fid > 0) $docIds[] = $fid;
                }
                if ($docIds) {
                    $ph = implode(',', array_fill(0, count($docIds), '?'));
                    $st = $pdo->prepare("DELETE FROM entity_files WHERE id IN ($ph)");
                    $st->execute($docIds);
                    $counts['entity_files_docs'] = (int)$st->rowCount();
                }
            }
            if (self::tableExists($pdo, 'app_settings')) {
                $st = $pdo->prepare("DELETE FROM app_settings WHERE `key` IN ('deviz_last_number','bon_consum_last_number')");
                $st->execute();
                $counts['app_settings_docs'] = (int)$st->rowCount();
            }
            if (self::tableExists($pdo, 'audit_log')) {
                $st = $pdo->prepare("DELETE FROM audit_log WHERE created_at >= ?");
                $st->execute(['2026-01-14 00:00:00']);
                $counts['audit_log_since_2026_01_14'] = (int)$st->rowCount();

                $actions = [
                    'PROJECT_CONSUMPTION_CREATE',
                    'PROJECT_CONSUMPTION_UPDATE',
                    'PROJECT_CONSUMPTION_DELETE',
                    'PROJECT_PRODUCT_HPL_RESERVE',
                    'PROJECT_PRODUCT_HPL_CONSUME',
                    'PROJECT_PRODUCT_HPL_UNALLOCATE',
                    'PROJECT_PRODUCT_MAGAZIE_UNALLOCATE',
                    'PROJECT_HPL_REST_RETURN',
                    'PROJECT_HPL_CONSUMPTION_UPDATE',
                    'PROJECT_HPL_CONSUMPTION_DELETE',
                    'HPL_STOCK_RESERVE',
                    'HPL_STOCK_CONSUME',
                    'HPL_STOCK_UNRESERVE',
                    'MAGAZIE_OUT',
                    'DEVIZ_GENERATED',
                    'BON_CONSUM_GENERATED',
                ];
                $entityTypes = [
                    'project_magazie_consumptions',
                    'project_hpl_consumptions',
                    'project_product_hpl_consumptions',
                    'project_hpl_allocations',
                ];
                $actionPh = implode(',', array_fill(0, count($actions), '?'));
                $entityPh = implode(',', array_fill(0, count($entityTypes), '?'));
                $sql = "
                    DELETE FROM audit_log
                    WHERE action IN ($actionPh)
                       OR entity_type IN ($entityPh)
                ";
                $st = $pdo->prepare($sql);
                $st->execute(array_merge($actions, $entityTypes));
                $counts['audit_log'] = (int)$st->rowCount();
            }

            $pdo->commit();
            $deletedFiles = 0;
            $failedFiles = [];
            if ($docFiles) {
                $dir = __DIR__ . '/../../../storage/uploads/files';
                foreach ($docFiles as $f) {
                    $name = basename((string)($f['stored_name'] ?? ''));
                    if ($name === '') continue;
                    $path = $dir . '/' . $name;
                    if (!is_file($path)) continue;
                    if (@unlink($path)) {
                        $deletedFiles++;
                    } else {
                        $failedFiles[] = $name;
                    }
                }
            }
            if ($deletedFiles > 0) $counts['files_deleted'] = $deletedFiles;
            if ($failedFiles) {
                $counts['files_failed'] = count($failedFiles);
                $counts['files_failed_names'] = $failedFiles;
            }
            Response::json(['ok' => true, 'deleted' => $counts]);
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Response::json(['ok' => false, 'error' => 'Eroare la reset: ' . $e->getMessage()], 500);
        }
    }
}
