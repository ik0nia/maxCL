<?php
declare(strict_types=1);

namespace App\Controllers\Magazie;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\DbMigrations;
use App\Core\Env;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\MagazieItem;
use App\Models\MagazieMovement;

final class ReceptionController
{
    public static function index(): void
    {
        $triedMigrate = false;
        try {
            $recent = MagazieMovement::recent(120);
            echo View::render('magazie/receptie/index', [
                'title' => 'Recepție marfă',
                'recent' => $recent,
            ]);
        } catch (\Throwable $e) {
            // Încearcă încă o dată după auto-migrare (hosting poate servi request-ul înainte să se aplice).
            if (!$triedMigrate) {
                $triedMigrate = true;
                try {
                    DbMigrations::runAuto();
                    $recent = MagazieMovement::recent(120);
                    echo View::render('magazie/receptie/index', [
                        'title' => 'Recepție marfă',
                        'recent' => $recent,
                    ]);
                    return;
                } catch (\Throwable $e2) {
                    $e = $e2;
                }
            }

            $u = Auth::user();
            $debug = Env::bool('APP_DEBUG', false) || ($u && strtolower((string)($u['email'] ?? '')) === 'sacodrut@ikonia.ro');
            $msg = 'Recepția nu este disponibilă momentan. Rulează Update DB ca să creezi tabelele necesare.';
            if ($debug) {
                $msg .= ' Eroare: ' . $e->getMessage();
            }
            echo View::render('system/placeholder', [
                'title' => 'Recepție marfă',
                'message' => $msg,
            ]);
        }
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'winmentor_code' => 'Cod WinMentor',
            'name' => 'Denumire',
            'qty' => 'Bucăți',
            'unit_price' => 'Preț/bucată',
        ]);
        $errors = $check['errors'];

        $code = trim((string)($_POST['winmentor_code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '')));
        $unitPriceRaw = trim((string)($_POST['unit_price'] ?? ''));
        $unitPrice = Validator::dec($unitPriceRaw);
        $note = trim((string)($_POST['note'] ?? ''));

        if ($code !== '' && mb_strlen($code) > 64) $errors['winmentor_code'] = 'Cod prea lung.';
        if ($name !== '' && mb_strlen($name) > 190) $errors['name'] = 'Denumire prea lungă.';
        if ($qty === null || $qty <= 0) $errors['qty'] = 'Cantitate invalidă.';
        if ($unitPrice === null || $unitPrice < 0 || $unitPrice > 100000000) $errors['unit_price'] = 'Preț invalid.';
        if ($note !== '' && mb_strlen($note) > 255) $errors['note'] = 'Notă prea lungă.';

        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile pentru recepție.');
            Response::redirect('/magazie/receptie');
        }

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $existing = MagazieItem::findByWinmentorForUpdate($code);
            if ($existing) {
                $before = $existing;
                MagazieItem::updateFields((int)$existing['id'], [
                    'name' => $name,
                    'unit_price' => $unitPrice,
                ]);
                MagazieItem::adjustStock((int)$existing['id'], (float)$qty);
                $itemId = (int)$existing['id'];
                $after = MagazieItem::findForUpdate($itemId) ?: $before;
            } else {
                $itemId = MagazieItem::create([
                    'winmentor_code' => $code,
                    'name' => $name,
                    'unit_price' => $unitPrice,
                    'stock_qty' => (float)$qty,
                ]);
                $before = null;
                $after = MagazieItem::findForUpdate($itemId);
            }

            $movementId = MagazieMovement::create([
                'item_id' => $itemId,
                'direction' => 'IN',
                'qty' => (float)$qty,
                'unit_price' => $unitPrice,
                'project_id' => null,
                'project_code' => null,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            $pdo->commit();

            Audit::log('MAGAZIE_IN', 'magazie_items', $itemId, is_array($before) ? $before : null, is_array($after) ? $after : null, [
                'movement_id' => $movementId,
                'qty' => (float)$qty,
                'unit_price' => $unitPrice,
                'note' => $note !== '' ? $note : null,
            ]);

            Session::flash('toast_success', 'Recepție salvată.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva recepția.');
        }

        Response::redirect('/magazie/receptie');
    }
}

