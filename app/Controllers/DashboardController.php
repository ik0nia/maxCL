<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\StockStats;

final class DashboardController
{
    public static function index(): void
    {
        $byThickness = [];
        $stockError = null;
        try {
            $byThickness = StockStats::availableByThickness();
        } catch (\Throwable $e) {
            $stockError = $e->getMessage();
        }

        echo View::render('dashboard/index', [
            'title' => 'Panou',
            'byThickness' => $byThickness,
            'stockError' => $stockError,
        ]);
    }
}

