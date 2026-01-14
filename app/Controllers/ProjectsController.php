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
use App\Models\Project;

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

        echo View::render('projects/show', [
            'title' => 'Proiect',
            'project' => $project,
            'tab' => $tab,
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
}

