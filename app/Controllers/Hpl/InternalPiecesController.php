<?php
declare(strict_types=1);

namespace App\Controllers\Hpl;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\HplBoard;
use App\Models\HplStockPiece;

final class InternalPiecesController
{
    /** @return array<int,string> */
    private static function locations(): array
    {
        return ['Depozit', 'Producție', 'Magazin', 'Atelier', 'Depozit (Stricat)'];
    }

    public static function index(): void
    {
        $boards = [];
        try {
            $boards = HplBoard::allWithTotals(null, null);
        } catch (\Throwable $e) {
            $boards = [];
        }
        echo View::render('hpl/internal_pieces/index', [
            'title' => 'Adăugare plăci mici (nestocabile)',
            'boards' => $boards,
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'board_id' => 'Tip placă',
            'width_mm' => 'Lățime (mm)',
            'height_mm' => 'Lungime (mm)',
            'qty' => 'Bucăți',
            'location' => 'Locație',
        ]);
        $errors = $check['errors'];

        $boardId = Validator::int((string)($_POST['board_id'] ?? ''), 1) ?? null;
        $width = Validator::int((string)($_POST['width_mm'] ?? ''), 1, 100000) ?? null;
        $height = Validator::int((string)($_POST['height_mm'] ?? ''), 1, 100000) ?? null;
        $qty = Validator::int((string)($_POST['qty'] ?? ''), 1, 100000) ?? null;
        $location = trim((string)($_POST['location'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($boardId === null) $errors['board_id'] = 'Selectează tipul plăcii.';
        if ($width === null) $errors['width_mm'] = 'Valoare invalidă.';
        if ($height === null) $errors['height_mm'] = 'Valoare invalidă.';
        if ($qty === null) $errors['qty'] = 'Valoare invalidă.';
        if ($location === '' || !in_array($location, self::locations(), true)) $errors['location'] = 'Locație invalidă.';

        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile.');
            Response::redirect('/hpl/piese-interne');
        }

        $board = HplBoard::find((int)$boardId);
        if (!$board) {
            Session::flash('toast_error', 'Tip de placă inexistent.');
            Response::redirect('/hpl/piese-interne');
        }

        // Regulă: Producție = indisponibil (RESERVED)
        $status = ($location === 'Producție') ? 'RESERVED' : 'AVAILABLE';
        if ($status === 'RESERVED') {
            Session::flash('toast_error', 'Locația „Producție” setează automat statusul ca Rezervat/Indisponibil.');
        }

        $data = [
            'board_id' => (int)$boardId,
            'is_accounting' => 0,
            'piece_type' => 'OFFCUT',
            'status' => $status,
            'width_mm' => (int)$width,
            'height_mm' => (int)$height,
            'qty' => (int)$qty,
            'location' => $location,
            'notes' => $notes,
        ];

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            // Cumulare dacă există o piesă internă identică
            $existing = HplStockPiece::findIdentical(
                (int)$boardId,
                (string)$data['piece_type'],
                (string)$data['status'],
                (int)$data['width_mm'],
                (int)$data['height_mm'],
                (string)$data['location'],
                0
            );
            if ($existing) {
                $before = $existing;
                HplStockPiece::incrementQty((int)$existing['id'], (int)$data['qty']);
                if (trim((string)$data['notes']) !== '') {
                    HplStockPiece::appendNote((int)$existing['id'], (string)$data['notes']);
                }
                $pieceId = (int)$existing['id'];
                $pdo->commit();
                $after = HplStockPiece::find($pieceId) ?: $before;
                Audit::log('INTERNAL_PIECE_CREATE', 'hpl_stock_pieces', $pieceId, $before, $after, [
                    'message' => 'A adăugat (cumulare) piesă internă OFFCUT ' . (int)$data['height_mm'] . '×' . (int)$data['width_mm'] . ' mm, ' . (int)$data['qty'] . ' buc, locație ' . (string)$data['location'] . '.',
                    'board_id' => (int)$boardId,
                    'is_accounting' => 0,
                ]);
                Session::flash('toast_success', 'Piesă internă actualizată (cumulare).');
                Response::redirect('/hpl/piese-interne');
            }

            $pieceId = HplStockPiece::create($data);
            $pdo->commit();

            $m2 = (((int)$data['width_mm'] * (int)$data['height_mm']) / 1000000.0) * (int)$data['qty'];
            Audit::log('INTERNAL_PIECE_CREATE', 'hpl_stock_pieces', $pieceId, null, $data, [
                'message' => 'A adăugat piesă internă OFFCUT ' . (int)$data['height_mm'] . '×' . (int)$data['width_mm'] . ' mm, ' . (int)$data['qty'] . ' buc, ' . number_format((float)$m2, 2, '.', '') . ' mp, locație ' . (string)$data['location'] . '.',
                'board_id' => (int)$boardId,
                'board_code' => (string)($board['code'] ?? ''),
                'is_accounting' => 0,
                'area_m2' => $m2,
            ]);

            Session::flash('toast_success', 'Piesă internă adăugată.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot adăuga piesa internă: ' . $e->getMessage());
        }

        Response::redirect('/hpl/piese-interne');
    }
}

