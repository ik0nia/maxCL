<?php
declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\AppSetting;

final class CostSettingsController
{
    public static function index(): void
    {
        $labor = null;
        $cnc = null;
        try {
            $labor = AppSetting::getFloat(AppSetting::KEY_COST_LABOR_PER_HOUR);
            $cnc = AppSetting::getFloat(AppSetting::KEY_COST_CNC_PER_HOUR);
        } catch (\Throwable $e) {
            // fallback: dacă lipsesc tabele după deploy, lăsăm pagina să se încarce
            $labor = null;
            $cnc = null;
        }

        echo View::render('system/cost_settings', [
            'title' => 'Setări costuri',
            'labor' => $labor,
            'cnc' => $cnc,
        ]);
    }

    public static function save(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $labor = Validator::dec(trim((string)($_POST['cost_labor_per_hour'] ?? '')));
        $cnc = Validator::dec(trim((string)($_POST['cost_cnc_per_hour'] ?? '')));
        if ($labor !== null && $labor < 0) $labor = null;
        if ($cnc !== null && $cnc < 0) $cnc = null;

        try {
            $before = [
                AppSetting::KEY_COST_LABOR_PER_HOUR => AppSetting::get(AppSetting::KEY_COST_LABOR_PER_HOUR),
                AppSetting::KEY_COST_CNC_PER_HOUR => AppSetting::get(AppSetting::KEY_COST_CNC_PER_HOUR),
            ];

            AppSetting::set(AppSetting::KEY_COST_LABOR_PER_HOUR, $labor !== null ? (string)$labor : null, Auth::id());
            AppSetting::set(AppSetting::KEY_COST_CNC_PER_HOUR, $cnc !== null ? (string)$cnc : null, Auth::id());

            $after = [
                AppSetting::KEY_COST_LABOR_PER_HOUR => AppSetting::get(AppSetting::KEY_COST_LABOR_PER_HOUR),
                AppSetting::KEY_COST_CNC_PER_HOUR => AppSetting::get(AppSetting::KEY_COST_CNC_PER_HOUR),
            ];

            Audit::log('SETTINGS_COSTS_UPDATE', 'app_settings', 0, $before, $after, [
                'message' => 'A actualizat setările de costuri (manoperă/CNC).',
            ]);
            Session::flash('toast_success', 'Setări salvate.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot salva setările.');
        }
        Response::redirect('/system/costuri');
    }
}

