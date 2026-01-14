<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\HplBoard;
use App\Models\MagazieItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectHplAllocation;
use App\Models\ProjectHplConsumption;
use App\Models\ProjectMagazieConsumption;
use App\Models\ProjectProduct;

final class ProjectsController
{
    /** @return array<int, array{value:string,label:string}> */
    private static function statuses(): array
    {
        return [
            ['value' => 'DRAFT', 'label' => 'Draft'],
            ['value' => 'CONFIRMAT', 'label' => 'Confirmat'],
            ['value' => 'IN_PRODUCTIE', 'label' => 'În producție'],
            ['value' => 'IN_ASTEPTARE', 'label' => 'În așteptare'],
            ['value' => 'FINALIZAT_TEHNIC', 'label' => 'Finalizat tehnic'],
            ['value' => 'LIVRAT_PARTIAL', 'label' => 'Livrat parțial'],
            ['value' => 'LIVRAT_COMPLET', 'label' => 'Livrat complet'],
            ['value' => 'ANULAT', 'label' => 'Anulat'],
        ];
    }

    /** @return array<int, array{value:string,label:string}> */
    private static function allocationModes(): array
    {
        return [
            ['value' => 'by_area', 'label' => 'by_area (după suprafață)'],
            ['value' => 'by_qty', 'label' => 'by_qty (după cantitate)'],
            ['value' => 'manual', 'label' => 'manual'],
        ];
    }

    public static function index(): void
    {
        $q = isset($_GET['q']) ? trim((string)($_GET['q'] ?? '')) : '';
        $status = isset($_GET['status']) ? trim((string)($_GET['status'] ?? '')) : '';

        try {
            $rows = Project::all($q !== '' ? $q : null, $status !== '' ? $status : null, 800);
            echo View::render('projects/index', [
                'title' => 'Proiecte',
                'rows' => $rows,
                'q' => $q,
                'status' => $status,
                'statuses' => self::statuses(),
            ]);
        } catch (\Throwable $e) {
            echo View::render('system/placeholder', [
                'title' => 'Proiecte',
                'message' => 'Modulul Proiecte nu este disponibil momentan. Rulează Update DB dacă lipsesc tabelele.',
            ]);
        }
    }

    public static function createForm(): void
    {
        echo View::render('projects/form', [
            'title' => 'Proiect nou',
            'mode' => 'create',
            'row' => [
                'status' => 'DRAFT',
                'priority' => 0,
                'allocation_mode' => 'by_area',
                'allocations_locked' => 0,
            ],
            'errors' => [],
            'statuses' => self::statuses(),
            'allocationModes' => self::allocationModes(),
            'clients' => Client::allWithProjects(), // reuse list (name/type)
            'groups' => ClientGroup::forSelect(),
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'name' => 'Nume',
        ]);
        $errors = $check['errors'];

        $clientIdRaw = trim((string)($_POST['client_id'] ?? ''));
        $groupIdRaw = trim((string)($_POST['client_group_id'] ?? ''));
        $clientId = $clientIdRaw !== '' ? (Validator::int($clientIdRaw, 1) ?? null) : null;
        $groupId = $groupIdRaw !== '' ? (Validator::int($groupIdRaw, 1) ?? null) : null;
        if ($clientIdRaw !== '' && $clientId === null) $errors['client_id'] = 'Client invalid.';
        if ($groupIdRaw !== '' && $groupId === null) $errors['client_group_id'] = 'Grup invalid.';
        if ($clientId !== null && $groupId !== null) {
            $errors['client_id'] = 'Alege fie client, fie grup.';
            $errors['client_group_id'] = 'Alege fie client, fie grup.';
        }

        $priority = Validator::int(trim((string)($_POST['priority'] ?? '0')), -100000, 100000) ?? 0;
        $status = trim((string)($_POST['status'] ?? 'DRAFT'));
        $allowedStatuses = array_map(fn($s) => (string)$s['value'], self::statuses());
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) $errors['status'] = 'Status invalid.';

        $allocationMode = trim((string)($_POST['allocation_mode'] ?? 'by_area'));
        $allowedAlloc = array_map(fn($m) => (string)$m['value'], self::allocationModes());
        if ($allocationMode !== '' && !in_array($allocationMode, $allowedAlloc, true)) $errors['allocation_mode'] = 'Mod invalid.';
        $allocLocked = isset($_POST['allocations_locked']) ? 1 : 0;

        if ($errors) {
            echo View::render('projects/form', [
                'title' => 'Proiect nou',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
                'statuses' => self::statuses(),
                'allocationModes' => self::allocationModes(),
                'clients' => Client::allWithProjects(),
                'groups' => ClientGroup::forSelect(),
            ]);
            return;
        }

        $data = [
            'code' => trim((string)$_POST['code']),
            'name' => trim((string)$_POST['name']),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'category' => trim((string)($_POST['category'] ?? '')) ?: null,
            'status' => $status ?: 'DRAFT',
            'priority' => $priority,
            'start_date' => trim((string)($_POST['start_date'] ?? '')) ?: null,
            'due_date' => trim((string)($_POST['due_date'] ?? '')) ?: null,
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
            'technical_notes' => trim((string)($_POST['technical_notes'] ?? '')) ?: null,
            'tags' => trim((string)($_POST['tags'] ?? '')) ?: null,
            'client_id' => $clientId,
            'client_group_id' => $groupId,
            'allocation_mode' => $allocationMode ?: 'by_area',
            'allocations_locked' => $allocLocked,
            'created_by' => Auth::id(),
        ];

        try {
            $id = Project::create($data);
            Audit::log('PROJECT_CREATE', 'projects', $id, null, $data, [
                'message' => 'A creat proiect: ' . $data['code'] . ' · ' . $data['name'],
            ]);
            Session::flash('toast_success', 'Proiect creat.');
            Response::redirect('/projects/' . $id);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot crea proiectul: ' . $e->getMessage());
            Response::redirect('/projects/create');
        }
    }

    public static function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $project = Project::find($id);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $tab = isset($_GET['tab']) ? trim((string)($_GET['tab'] ?? '')) : '';
        if ($tab === '') $tab = 'general';

        $projectProducts = [];
        $magazieConsum = [];
        $hplConsum = [];
        $hplAlloc = [];
        $hplBoards = [];
        $magazieItems = [];
        if ($tab === 'products') {
            try {
                $projectProducts = ProjectProduct::forProject($id);
            } catch (\Throwable $e) {
                $projectProducts = [];
            }
        } elseif ($tab === 'consum') {
            try { $projectProducts = ProjectProduct::forProject($id); } catch (\Throwable $e) { $projectProducts = []; }
            try { $magazieConsum = ProjectMagazieConsumption::forProject($id); } catch (\Throwable $e) { $magazieConsum = []; }
            try { $hplConsum = ProjectHplConsumption::forProject($id); } catch (\Throwable $e) { $hplConsum = []; }
            try { $hplAlloc = ProjectHplAllocation::forProject($id); } catch (\Throwable $e) { $hplAlloc = []; }
            try { $hplBoards = HplBoard::allWithTotals(null, null); } catch (\Throwable $e) { $hplBoards = []; }
            try { $magazieItems = MagazieItem::all(null, 5000); } catch (\Throwable $e) { $magazieItems = []; }
        }

        echo View::render('projects/show', [
            'title' => 'Proiect',
            'project' => $project,
            'tab' => $tab,
            'projectProducts' => $projectProducts,
            'magazieConsum' => $magazieConsum,
            'hplConsum' => $hplConsum,
            'hplAlloc' => $hplAlloc,
            'hplBoards' => $hplBoards,
            'magazieItems' => $magazieItems,
            'statuses' => self::statuses(),
            'allocationModes' => self::allocationModes(),
            'clients' => Client::allWithProjects(),
            'groups' => ClientGroup::forSelect(),
        ]);
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Project::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'name' => 'Nume',
        ]);
        $errors = $check['errors'];

        $clientIdRaw = trim((string)($_POST['client_id'] ?? ''));
        $groupIdRaw = trim((string)($_POST['client_group_id'] ?? ''));
        $clientId = $clientIdRaw !== '' ? (Validator::int($clientIdRaw, 1) ?? null) : null;
        $groupId = $groupIdRaw !== '' ? (Validator::int($groupIdRaw, 1) ?? null) : null;
        if ($clientIdRaw !== '' && $clientId === null) $errors['client_id'] = 'Client invalid.';
        if ($groupIdRaw !== '' && $groupId === null) $errors['client_group_id'] = 'Grup invalid.';
        if ($clientId !== null && $groupId !== null) {
            $errors['client_id'] = 'Alege fie client, fie grup.';
            $errors['client_group_id'] = 'Alege fie client, fie grup.';
        }

        $priority = Validator::int(trim((string)($_POST['priority'] ?? '0')), -100000, 100000) ?? 0;
        $status = trim((string)($_POST['status'] ?? (string)($before['status'] ?? 'DRAFT')));
        $allowedStatuses = array_map(fn($s) => (string)$s['value'], self::statuses());
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) $errors['status'] = 'Status invalid.';

        $allocationMode = trim((string)($_POST['allocation_mode'] ?? (string)($before['allocation_mode'] ?? 'by_area')));
        $allowedAlloc = array_map(fn($m) => (string)$m['value'], self::allocationModes());
        if ($allocationMode !== '' && !in_array($allocationMode, $allowedAlloc, true)) $errors['allocation_mode'] = 'Mod invalid.';
        $allocLocked = isset($_POST['allocations_locked']) ? 1 : 0;

        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile proiectului.');
            Response::redirect('/projects/' . $id . '?tab=general');
        }

        $after = [
            'code' => trim((string)$_POST['code']),
            'name' => trim((string)$_POST['name']),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'category' => trim((string)($_POST['category'] ?? '')) ?: null,
            'status' => $status ?: 'DRAFT',
            'priority' => $priority,
            'start_date' => trim((string)($_POST['start_date'] ?? '')) ?: null,
            'due_date' => trim((string)($_POST['due_date'] ?? '')) ?: null,
            'completed_at' => $before['completed_at'] ?? null,
            'cancelled_at' => $before['cancelled_at'] ?? null,
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
            'technical_notes' => trim((string)($_POST['technical_notes'] ?? '')) ?: null,
            'tags' => trim((string)($_POST['tags'] ?? '')) ?: null,
            'client_id' => $clientId,
            'client_group_id' => $groupId,
            'allocation_mode' => $allocationMode ?: 'by_area',
            'allocations_locked' => $allocLocked,
        ];

        try {
            Project::update($id, $after);
            Audit::log('PROJECT_UPDATE', 'projects', $id, $before, $after, [
                'message' => 'A actualizat proiect: ' . $after['code'] . ' · ' . $after['name'],
            ]);
            Session::flash('toast_success', 'Proiect actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot actualiza proiectul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $id . '?tab=general');
    }

    public static function changeStatus(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Project::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $newStatus = trim((string)($_POST['status'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $allowedStatuses = array_map(fn($s) => (string)$s['value'], self::statuses());
        if ($newStatus === '' || !in_array($newStatus, $allowedStatuses, true)) {
            Session::flash('toast_error', 'Status invalid.');
            Response::redirect('/projects/' . $id . '?tab=general');
        }

        $oldStatus = (string)($before['status'] ?? '');
        if ($oldStatus === $newStatus) {
            Session::flash('toast_error', 'Statusul este deja setat.');
            Response::redirect('/projects/' . $id . '?tab=general');
        }

        $after = $before;
        $after['status'] = $newStatus;
        // timestamp-uri best-effort
        if ($newStatus === 'LIVRAT_COMPLET') {
            $after['completed_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'ANULAT') {
            $after['cancelled_at'] = date('Y-m-d H:i:s');
        }

        try {
            Project::update($id, $after);
            Audit::log('PROJECT_STATUS_CHANGE', 'projects', $id, $before, $after, [
                'message' => 'Schimbare status proiect: ' . $oldStatus . ' → ' . $newStatus,
                'note' => $note !== '' ? $note : null,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
            Session::flash('toast_success', 'Status actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot schimba statusul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $id . '?tab=general');
    }

    public static function canWrite(): bool
    {
        $u = Auth::user();
        return $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
    }

    public static function addExistingProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $productId = Validator::int(trim((string)($_POST['product_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $unit = trim((string)($_POST['unit'] ?? 'buc'));
        if ($productId === null) {
            Session::flash('toast_error', 'Produs invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }
        if ($qty <= 0) $qty = 1.0;

        $prod = Product::find((int)$productId);
        if (!$prod) {
            Session::flash('toast_error', 'Produs inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            $ppId = ProjectProduct::addToProject([
                'project_id' => $projectId,
                'product_id' => (int)$productId,
                'qty' => $qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'production_status' => 'DE_PREGATIT',
                'delivered_qty' => 0,
                'notes' => null,
            ]);
            Audit::log('PROJECT_PRODUCT_ATTACH', 'project_products', $ppId, null, null, [
                'message' => 'A atașat produs la proiect: ' . (string)($prod['name'] ?? ''),
                'project_id' => $projectId,
                'product_id' => (int)$productId,
                'qty' => $qty,
                'unit' => $unit,
            ]);
            Session::flash('toast_success', 'Produs adăugat în proiect.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot adăuga produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function createProductInProject(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $check = Validator::required($_POST, [
            'name' => 'Denumire',
            'qty' => 'Cantitate',
        ]);
        $errors = $check['errors'];
        $name = trim((string)($_POST['name'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $unit = trim((string)($_POST['unit'] ?? 'buc'));
        $width = Validator::int(trim((string)($_POST['width_mm'] ?? '')), 1, 100000);
        $height = Validator::int(trim((string)($_POST['height_mm'] ?? '')), 1, 100000);

        if ($qty <= 0) $errors['qty'] = 'Cantitate invalidă.';
        if ($errors) {
            Session::flash('toast_error', 'Completează corect produsul.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            $pid = Product::create([
                'code' => $code !== '' ? $code : null,
                'name' => $name,
                'width_mm' => $width,
                'height_mm' => $height,
                'notes' => null,
                'cnc_settings_json' => null,
            ]);
            Audit::log('PRODUCT_CREATE', 'products', $pid, null, null, [
                'message' => 'A creat produs: ' . $name,
                'project_id' => $projectId,
            ]);

            $ppId = ProjectProduct::addToProject([
                'project_id' => $projectId,
                'product_id' => $pid,
                'qty' => $qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'production_status' => 'DE_PREGATIT',
                'delivered_qty' => 0,
                'notes' => null,
            ]);
            Audit::log('PROJECT_PRODUCT_ATTACH', 'project_products', $ppId, null, null, [
                'message' => 'A atașat produs nou la proiect: ' . $name,
                'project_id' => $projectId,
                'product_id' => $pid,
                'qty' => $qty,
                'unit' => $unit,
            ]);

            Session::flash('toast_success', 'Produs creat și adăugat în proiect.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot crea produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function updateProjectProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);

        $before = ProjectProduct::find($ppId);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Produs proiect invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $del = Validator::dec(trim((string)($_POST['delivered_qty'] ?? '0'))) ?? 0.0;
        $unit = trim((string)($_POST['unit'] ?? (string)($before['unit'] ?? 'buc')));
        $st = trim((string)($_POST['production_status'] ?? (string)($before['production_status'] ?? 'DE_PREGATIT')));
        $note = trim((string)($_POST['notes'] ?? ''));

        $allowed = ['DE_PREGATIT','CNC','ATELIER','FINISARE','GATA','LIVRAT_PARTIAL','LIVRAT_COMPLET','REBUT'];
        if (!in_array($st, $allowed, true)) $st = (string)($before['production_status'] ?? 'DE_PREGATIT');
        if ($qty <= 0) $qty = 1.0;
        if ($del < 0) $del = 0.0;
        if ($del > $qty) $del = $qty;

        $after = [
            'qty' => $qty,
            'unit' => $unit !== '' ? $unit : 'buc',
            'production_status' => $st,
            'delivered_qty' => $del,
            'notes' => $note !== '' ? $note : null,
        ];

        try {
            ProjectProduct::updateFields($ppId, $after);
            Audit::log('PROJECT_PRODUCT_UPDATE', 'project_products', $ppId, $before, $after, [
                'message' => 'A actualizat produs în proiect.',
                'project_id' => $projectId,
            ]);
            Session::flash('toast_success', 'Produs actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot actualiza produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function unlinkProjectProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $ppId = (int)($params['ppId'] ?? 0);
        $before = ProjectProduct::find($ppId);
        if (!$before || (int)($before['project_id'] ?? 0) !== $projectId) {
            Session::flash('toast_error', 'Produs proiect invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=products');
        }

        try {
            ProjectProduct::delete($ppId);
            Audit::log('PROJECT_PRODUCT_DETACH', 'project_products', $ppId, $before, null, [
                'message' => 'A dezlegat produs din proiect.',
                'project_id' => $projectId,
                'product_id' => (int)($before['product_id'] ?? 0),
            ]);
            Session::flash('toast_success', 'Produs scos din proiect.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot scoate produsul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=products');
    }

    public static function addMagazieConsumption(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $itemId = Validator::int(trim((string)($_POST['item_id'] ?? '')), 1);
        $ppId = Validator::int(trim((string)($_POST['project_product_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        $unit = trim((string)($_POST['unit'] ?? 'buc'));
        $mode = trim((string)($_POST['mode'] ?? 'CONSUMED'));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($itemId === null || $qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Consum invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        if (!in_array($mode, ['RESERVED','CONSUMED'], true)) $mode = 'CONSUMED';

        $item = MagazieItem::find((int)$itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }

        // dacă e consumat, verificăm stoc
        if ($mode === 'CONSUMED') {
            $stock = (float)($item['stock_qty'] ?? 0);
            if ($qty > $stock + 1e-9) {
                Session::flash('toast_error', 'Stoc insuficient în Magazie.');
                Response::redirect('/projects/' . $projectId . '?tab=consum');
            }
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $cid = ProjectMagazieConsumption::create([
                'project_id' => $projectId,
                'project_product_id' => $ppId,
                'item_id' => (int)$itemId,
                'qty' => (float)$qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            if ($mode === 'CONSUMED') {
                if (!MagazieItem::adjustStock((int)$itemId, -(float)$qty)) {
                    throw new \RuntimeException('Nu pot scădea stocul.');
                }
                // mișcare Magazie
                \App\Models\MagazieMovement::create([
                    'item_id' => (int)$itemId,
                    'direction' => 'OUT',
                    'qty' => (float)$qty,
                    'unit_price' => (isset($item['unit_price']) && $item['unit_price'] !== '' && is_numeric($item['unit_price'])) ? (float)$item['unit_price'] : null,
                    'project_id' => $projectId,
                    'project_code' => (string)($project['code'] ?? null),
                    'note' => $note !== '' ? $note : 'Consum proiect',
                    'created_by' => Auth::id(),
                ]);
            }

            $pdo->commit();

            Audit::log('PROJECT_CONSUMPTION_CREATE', 'project_magazie_consumptions', $cid, null, null, [
                'message' => 'Consum Magazie ' . $mode . ': ' . (string)($item['winmentor_code'] ?? '') . ' · ' . (string)($item['name'] ?? ''),
                'project_id' => $projectId,
                'item_id' => (int)$itemId,
                'qty' => (float)$qty,
                'unit' => $unit,
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
            ]);
            Session::flash('toast_success', 'Consum Magazie salvat.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva consumul: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=consum');
    }

    public static function addHplConsumption(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $projectId = (int)($params['id'] ?? 0);
        $project = Project::find($projectId);
        if (!$project) {
            Session::flash('toast_error', 'Proiect inexistent.');
            Response::redirect('/projects');
        }

        $boardId = Validator::int(trim((string)($_POST['board_id'] ?? '')), 1);
        $qtyM2 = Validator::dec(trim((string)($_POST['qty_m2'] ?? ''))) ?? null;
        $mode = trim((string)($_POST['mode'] ?? 'RESERVED'));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($boardId === null || $qtyM2 === null || $qtyM2 <= 0) {
            Session::flash('toast_error', 'Consum HPL invalid.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }
        if (!in_array($mode, ['RESERVED','CONSUMED'], true)) $mode = 'RESERVED';

        $board = HplBoard::find((int)$boardId);
        if (!$board) {
            Session::flash('toast_error', 'Placă HPL inexistentă.');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }

        // Stoc disponibil (mp) - rezervări din alte proiecte (best-effort)
        $stockRows = HplBoard::allWithTotals(null, null);
        $stockM2 = 0.0;
        foreach ($stockRows as $r) {
            if ((int)($r['id'] ?? 0) === (int)$boardId) {
                $stockM2 = (float)($r['stock_m2_available'] ?? 0);
                break;
            }
        }
        $reservedOther = 0.0;
        try {
            $reservedOther = ProjectHplConsumption::reservedTotalForBoard((int)$boardId, $projectId);
        } catch (\Throwable $e) {
            $reservedOther = 0.0;
        }
        $effectiveAvailable = max(0.0, $stockM2 - $reservedOther);
        if ($qtyM2 > $effectiveAvailable + 1e-9) {
            Session::flash('toast_error', 'Stoc HPL insuficient (mp).');
            Response::redirect('/projects/' . $projectId . '?tab=consum');
        }

        /** @var \PDO $pdo */
        $pdo = \App\Core\DB::pdo();
        $pdo->beginTransaction();
        try {
            $cid = ProjectHplConsumption::create([
                'project_id' => $projectId,
                'board_id' => (int)$boardId,
                'qty_m2' => (float)$qtyM2,
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);

            // Auto-alocare pe produse (dacă nu e blocată distribuția și există produse)
            $allocLocked = (int)($project['allocations_locked'] ?? 0) === 1;
            if (!$allocLocked) {
                $pps = ProjectProduct::forProject($projectId);
                $weights = [];
                $sum = 0.0;
                $byArea = ((string)($project['allocation_mode'] ?? 'by_area')) === 'by_area';
                foreach ($pps as $pp) {
                    $ppid = (int)($pp['id'] ?? 0);
                    if ($ppid <= 0) continue;
                    $w = (int)($pp['product_width_mm'] ?? 0);
                    $h = (int)($pp['product_height_mm'] ?? 0);
                    $qq = (float)($pp['qty'] ?? 0);
                    $weight = 0.0;
                    if ($byArea && $w > 0 && $h > 0 && $qq > 0) {
                        $weight = (($w * $h) / 1000000.0) * $qq;
                    } elseif ($qq > 0) {
                        $weight = $qq;
                    }
                    if ($weight <= 0) continue;
                    $weights[$ppid] = $weight;
                    $sum += $weight;
                }
                if ($sum > 0.0) {
                    $alloc = [];
                    foreach ($weights as $ppid => $wgt) {
                        $alloc[] = [
                            'project_product_id' => (int)$ppid,
                            'qty_m2' => (float)$qtyM2 * ($wgt / $sum),
                        ];
                    }
                    ProjectHplAllocation::replaceForConsumption($cid, $alloc);
                }
            }

            $pdo->commit();

            Audit::log('PROJECT_CONSUMPTION_CREATE', 'project_hpl_consumptions', $cid, null, null, [
                'message' => 'Consum HPL ' . $mode . ': ' . (string)($board['code'] ?? '') . ' · ' . (string)($board['name'] ?? ''),
                'project_id' => $projectId,
                'board_id' => (int)$boardId,
                'qty_m2' => (float)$qtyM2,
                'mode' => $mode,
                'note' => $note !== '' ? $note : null,
            ]);
            Session::flash('toast_success', 'Consum HPL salvat.');
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot salva consumul HPL: ' . $e->getMessage());
        }
        Response::redirect('/projects/' . $projectId . '?tab=consum');
    }
}

