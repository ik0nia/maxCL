<?php
declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\View;

final class ManualController
{
    public static function index(): void
    {
        echo View::render('system/manual', [
            'title' => 'Manual avansat',
        ]);
    }
}
