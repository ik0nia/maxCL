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
                $msg .= ' Eroare: ' . get_class($e) . ' · ' . $e->getMessage() . ' · ' . basename((string)$e->getFile()) . ':' . (int)$e->getLine();
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

        // Recepție cu multiple poziții (rânduri). Unitatea se poate selecta per linie (implicit buc).
        $codes = $_POST['winmentor_code'] ?? [];
        $names = $_POST['name'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $units = $_POST['unit'] ?? [];
        $prices = $_POST['unit_price'] ?? [];
        $note = trim((string)($_POST['note'] ?? ''));

        $errors = [];
        if (!is_array($codes) || !is_array($names) || !is_array($qtys) || !is_array($units) || !is_array($prices)) {
            Session::flash('toast_error', 'Formular invalid.');
            Response::redirect('/magazie/receptie');
        }
        if ($note !== '' && mb_strlen($note) > 255) $errors['note'] = 'Notă prea lungă.';

        /** @var array<int, array{code:string,name:string,qty:float,unit:string,unit_price:float|null}> $lines */
        $lines = [];
        $n = max(count($codes), count($names), count($qtys), count($units), count($prices));
        for ($i = 0; $i < $n; $i++) {
            $code = is_scalar($codes[$i] ?? null) ? trim((string)$codes[$i]) : '';
            $name = is_scalar($names[$i] ?? null) ? trim((string)$names[$i]) : '';
            $qtyRaw = is_scalar($qtys[$i] ?? null) ? trim((string)$qtys[$i]) : '';
            $unitRaw = is_scalar($units[$i] ?? null) ? trim((string)$units[$i]) : '';
            $priceRaw = is_scalar($prices[$i] ?? null) ? trim((string)$prices[$i]) : '';
            $unit = $unitRaw !== '' ? $unitRaw : 'buc';

            // Sari peste rând complet gol
            if ($code === '' && $name === '' && $qtyRaw === '' && $unitRaw === '' && $priceRaw === '') {
                continue;
            }

            $qty = Validator::dec($qtyRaw);
            $unitPrice = Validator::dec($priceRaw);

            if ($code === '') $errors['row_' . $i] = 'Cod WinMentor lipsă (rând ' . ($i + 1) . ').';
            if ($name === '') $errors['row_' . $i] = 'Denumire lipsă (rând ' . ($i + 1) . ').';
            if ($code !== '' && mb_strlen($code) > 64) $errors['row_' . $i] = 'Cod prea lung (rând ' . ($i + 1) . ').';
            if ($name !== '' && mb_strlen($name) > 190) $errors['row_' . $i] = 'Denumire prea lungă (rând ' . ($i + 1) . ').';
            if ($unit !== '' && mb_strlen($unit) > 16) $errors['row_' . $i] = 'Unitate prea lungă (rând ' . ($i + 1) . ').';
            if ($qty === null || $qty <= 0) $errors['row_' . $i] = 'Cantitate invalidă (rând ' . ($i + 1) . ').';
            if ($unitPrice === null || $unitPrice < 0 || $unitPrice > 100000000) $errors['row_' . $i] = 'Preț invalid (rând ' . ($i + 1) . ').';

            if (!$errors) {
                $lines[] = [
                    'code' => $code,
                    'name' => $name,
                    'qty' => (float)$qty,
                    'unit' => $unit,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        if (!$lines) {
            Session::flash('toast_error', 'Adaugă cel puțin o poziție la recepție.');
            Response::redirect('/magazie/receptie');
        }
        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile pentru recepție.');
            Response::redirect('/magazie/receptie');
        }

        // Agregare pe cod (dacă același cod e introdus de mai multe ori în aceeași recepție)
        $byCode = [];
        foreach ($lines as $ln) {
            $c = $ln['code'];
            if (!isset($byCode[$c])) {
                $byCode[$c] = $ln;
            } else {
                if ((string)($byCode[$c]['unit'] ?? '') !== (string)($ln['unit'] ?? '')) {
                    $errors['unit'] = 'Unitate diferită pentru același cod WinMentor.';
                    break;
                }
                $byCode[$c]['qty'] += (float)$ln['qty'];
                // păstrăm ultima denumire/preț dacă sunt diferite (WinMentor poate actualiza)
                $byCode[$c]['name'] = $ln['name'];
                $byCode[$c]['unit'] = $ln['unit'];
                $byCode[$c]['unit_price'] = $ln['unit_price'];
            }
        }
        $lines = array_values($byCode);
        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile pentru recepție.');
            Response::redirect('/magazie/receptie');
        }

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $createdMovements = 0;
            foreach ($lines as $ln) {
                $code = $ln['code'];
                $name = $ln['name'];
                $qty = (float)$ln['qty'];
                $unit = (string)($ln['unit'] ?? 'buc');
                $unitPrice = $ln['unit_price'];

                $existing = MagazieItem::findByWinmentorForUpdate($code);
                if ($existing) {
                    $before = $existing;
                    MagazieItem::updateFields((int)$existing['id'], [
                        'name' => $name,
                        'unit' => $unit,
                        'unit_price' => $unitPrice,
                    ]);
                    MagazieItem::adjustStock((int)$existing['id'], (float)$qty);
                    $itemId = (int)$existing['id'];
                    $after = MagazieItem::findForUpdate($itemId) ?: $before;
                } else {
                    $itemId = MagazieItem::create([
                        'winmentor_code' => $code,
                        'name' => $name,
                        'unit' => $unit,
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
                $createdMovements++;

                Audit::log('MAGAZIE_IN', 'magazie_items', $itemId, is_array($before) ? $before : null, is_array($after) ? $after : null, [
                    'movement_id' => $movementId,
                    'qty' => (float)$qty,
                    'unit_price' => $unitPrice,
                    'note' => $note !== '' ? $note : null,
                ]);
            }

            $pdo->commit();

            Session::flash('toast_success', 'Recepție salvată (' . $createdMovements . ' poziții).');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva recepția.');
        }

        Response::redirect('/magazie/receptie');
    }
}

