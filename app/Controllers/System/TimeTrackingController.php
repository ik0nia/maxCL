<?php
declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\View;
use App\Models\ProjectTimeLog;

final class TimeTrackingController
{
    public static function index(): void
    {
        $category = isset($_GET['category']) ? trim((string)($_GET['category'] ?? '')) : '';
        $person = isset($_GET['person']) ? trim((string)($_GET['person'] ?? '')) : '';
        $df = isset($_GET['date_from']) ? trim((string)($_GET['date_from'] ?? '')) : '';
        $dt = isset($_GET['date_to']) ? trim((string)($_GET['date_to'] ?? '')) : '';
        $dateFrom = self::normalizeDate($df);
        $dateTo = self::normalizeDate($dt);

        $allowed = array_map(static fn(array $c): string => (string)($c['value'] ?? ''), ProjectTimeLog::categories());
        if ($category !== '' && !in_array($category, $allowed, true)) {
            $category = '';
        }

        $filters = [
            'category' => $category,
            'person' => $person,
            'date_from' => $dateFrom ?? '',
            'date_to' => $dateTo ?? '',
        ];

        $rows = [];
        $sumMinutes = 0;
        try {
            $rows = ProjectTimeLog::search($filters, 5000);
            $sumMinutes = ProjectTimeLog::sumMinutes($filters);
        } catch (\Throwable $e) {
            $rows = [];
            $sumMinutes = 0;
        }

        echo View::render('system/pontaj', [
            'title' => 'Pontaj',
            'rows' => $rows,
            'sumMinutes' => $sumMinutes,
            'filters' => [
                'category' => $category,
                'person' => $person,
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
            'categories' => ProjectTimeLog::categories(),
        ]);
    }

    private static function normalizeDate(?string $val): ?string
    {
        $val = trim((string)($val ?? ''));
        if ($val === '') return null;
        $dt = \DateTime::createFromFormat('Y-m-d', $val);
        if (!$dt) return null;
        return $dt->format('Y-m-d');
    }
}

