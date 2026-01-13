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
        $topBoards = [];
        $stockError = null;
        try {
            $byThickness = StockStats::availableByThickness();
            $topBoards = StockStats::topBoardsByAvailableM2(6);
        } catch (\Throwable $e) {
            $stockError = $e->getMessage();
        }

        echo View::render('dashboard/index', [
            'title' => 'Panou',
            'byThickness' => $byThickness,
            'topBoards' => $topBoards,
            'stockError' => $stockError,
        ]);
    }
}

