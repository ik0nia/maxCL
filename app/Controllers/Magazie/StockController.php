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
use App\Models\Project;

final class StockController
{
    public static function index(): void
    {
        $triedMigrate = false;
        try {
            $q = isset($_GET['q']) ? trim((string)($_GET['q'] ?? '')) : '';
            $items = MagazieItem::all($q !== '' ? $q : null, 5000);
            echo View::render('magazie/stoc/index', [
                'title' => 'Stoc Magazie',
                'items' => $items,
                'q' => $q,
            ]);
        } catch (\Throwable $e) {
            if (!$triedMigrate) {
                $triedMigrate = true;
                try {
                    DbMigrations::runAuto();
                    $q = isset($_GET['q']) ? trim((string)($_GET['q'] ?? '')) : '';
                    $items = MagazieItem::all($q !== '' ? $q : null, 5000);
                    echo View::render('magazie/stoc/index', [
                        'title' => 'Stoc Magazie',
                        'items' => $items,
                        'q' => $q,
                    ]);
                    return;
                } catch (\Throwable $e2) {
                    $e = $e2;
                }
            }

            $u = Auth::user();
            $debug = Env::bool('APP_DEBUG', false) || ($u && strtolower((string)($u['email'] ?? '')) === 'sacodrut@ikonia.ro');
            $msg = 'Magazia nu este disponibilă momentan. Rulează Update DB ca să creezi tabelele necesare.';
            if ($debug) {
                $msg .= ' Eroare: ' . get_class($e) . ' · ' . $e->getMessage() . ' · ' . basename((string)$e->getFile()) . ':' . (int)$e->getLine();
            }
            echo View::render('system/placeholder', [
                'title' => 'Stoc Magazie',
                'message' => $msg,
            ]);
        }
    }

    public static function consume(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);

        $check = Validator::required($_POST, [
            'qty' => 'Bucăți',
            'project_code' => 'Cod proiect',
        ]);
        $errors = $check['errors'];

        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '')));
        $projectCode = trim((string)($_POST['project_code'] ?? ''));
        $projectName = trim((string)($_POST['project_name'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($qty === null || $qty <= 0) $errors['qty'] = 'Cantitate invalidă.';
        if ($projectCode !== '' && mb_strlen($projectCode) > 64) $errors['project_code'] = 'Cod proiect prea lung.';
        if ($projectName !== '' && mb_strlen($projectName) > 190) $errors['project_name'] = 'Denumire proiect prea lungă.';
        if ($note !== '' && mb_strlen($note) > 255) $errors['note'] = 'Notă prea lungă.';

        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile pentru consum.');
            Response::redirect('/magazie/stoc');
        }

        $item = MagazieItem::find($id);
        if (!$item) {
            Session::flash('toast_error', 'Produs inexistent.');
            Response::redirect('/magazie/stoc');
        }

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $before = MagazieItem::findForUpdate($id);
            if (!$before) {
                throw new \RuntimeException('Produs inexistent.');
            }
            $beforeQty = (float)($before['stock_qty'] ?? 0);
            if ($qty > $beforeQty) {
                throw new \RuntimeException('Stoc insuficient.');
            }

            $projectId = null;
            $proj = null;
            if ($projectCode !== '') {
                $proj = Project::findByCode($projectCode);
                if (!$proj) {
                    $projectId = Project::createPlaceholder($projectCode, $projectName !== '' ? $projectName : null, Auth::id());
                    $proj = Project::findByCode($projectCode);
                } else {
                    $projectId = (int)($proj['id'] ?? 0) ?: null;
                }
            }

            if (!MagazieItem::adjustStock($id, -(float)$qty)) {
                throw new \RuntimeException('Nu pot scădea stocul (concurență sau stoc insuficient).');
            }

            $movementId = MagazieMovement::create([
                'item_id' => $id,
                'direction' => 'OUT',
                'qty' => (float)$qty,
                'unit_price' => (isset($before['unit_price']) && $before['unit_price'] !== '' && is_numeric($before['unit_price'])) ? (float)$before['unit_price'] : null,
                'project_id' => $projectId,
                'project_code' => $projectCode !== '' ? $projectCode : null,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            $after = MagazieItem::findForUpdate($id) ?: $before;
            $pdo->commit();

            Audit::log('MAGAZIE_OUT', 'magazie_items', $id, $before, $after, [
                'movement_id' => $movementId,
                'project_id' => $projectId,
                'project_code' => $projectCode,
                'project_name' => $proj ? (string)($proj['name'] ?? '') : null,
                'qty' => (float)$qty,
                'note' => $note !== '' ? $note : null,
            ]);

            Session::flash('toast_success', 'Stoc scăzut din proiect.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            $msg = $e->getMessage();
            if (str_contains(mb_strtolower($msg), 'insuficient')) {
                Session::flash('toast_error', 'Stoc insuficient.');
            } else {
                Session::flash('toast_error', 'Nu pot scădea stocul.');
            }
        }

        Response::redirect('/magazie/stoc');
    }
}

