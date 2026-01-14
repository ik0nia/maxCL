<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Product;

final class ProductsController
{
    public static function index(): void
    {
        $q = isset($_GET['q']) ? trim((string)($_GET['q'] ?? '')) : '';
        try {
            $rows = Product::all($q !== '' ? $q : null, 1200);
            echo View::render('products/index', [
                'title' => 'Produse',
                'rows' => $rows,
                'q' => $q,
            ]);
        } catch (\Throwable $e) {
            echo View::render('system/placeholder', [
                'title' => 'Produse',
                'message' => 'Produsele nu sunt disponibile momentan. Rulează Update DB dacă lipsește tabela products.',
            ]);
        }
    }
}

