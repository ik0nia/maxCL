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
use App\Models\ClientAddress;
use App\Models\ClientGroup;
use App\Models\Project;

final class ClientsController
{
    /** @return array<int, array{value:string,label:string}> */
    private static function types(): array
    {
        return [
            ['value' => 'PERSOANA_FIZICA', 'label' => 'Persoană fizică'],
            ['value' => 'FIRMA', 'label' => 'Firmă'],
        ];
    }

    public static function index(): void
    {
        try {
            $rows = Client::allWithProjects();
            echo View::render('clients/index', [
                'title' => 'Clienți',
                'rows' => $rows,
            ]);
        } catch (\Throwable $e) {
            echo View::render('system/placeholder', [
                'title' => 'Clienți',
                'message' => 'Clienții nu sunt disponibili momentan. Rulează Setup dacă lipsesc tabelele.',
            ]);
        }
    }

    public static function createForm(): void
    {
        echo View::render('clients/form', [
            'title' => 'Client nou',
            'mode' => 'create',
            'row' => ['type' => 'PERSOANA_FIZICA'],
            'errors' => [],
            'types' => self::types(),
            'groups' => ClientGroup::forSelect(),
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'type' => 'Tip client',
            'name' => 'Nume (client/companie)',
            'phone' => 'Telefon',
            'email' => 'Email',
            'address' => 'Adresă livrare',
        ]);
        $errors = $check['errors'];

        $type = trim((string)($_POST['type'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $groupIdRaw = trim((string)($_POST['client_group_id'] ?? ''));
        $groupNew = trim((string)($_POST['client_group_new'] ?? ''));
        $cui = trim((string)($_POST['cui'] ?? ''));
        $contact = trim((string)($_POST['contact_person'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $allowedTypes = array_map(fn($t) => (string)$t['value'], self::types());
        if ($type !== '' && !in_array($type, $allowedTypes, true)) {
            $errors['type'] = 'Tip invalid.';
        }
        if ($email !== '' && !Validator::email($email)) {
            $errors['email'] = 'Email invalid.';
        }
        if ($type === 'FIRMA' && $cui === '') {
            $errors['cui'] = 'CUI este obligatoriu pentru firmă.';
        }
        if ($groupNew !== '' && mb_strlen($groupNew) > 190) {
            $errors['client_group_new'] = 'Nume grup prea lung.';
        }

        if ($errors) {
            echo View::render('clients/form', [
                'title' => 'Client nou',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
                'types' => self::types(),
                'groups' => ClientGroup::forSelect(),
            ]);
            return;
        }

        $groupId = null;
        if ($groupNew !== '') {
            $gid = ClientGroup::upsertByName($groupNew);
            if ($gid > 0) $groupId = $gid;
        } elseif ($groupIdRaw !== '') {
            $gid = Validator::int($groupIdRaw, 1);
            if ($gid) $groupId = $gid;
        }

        $data = [
            'type' => $type,
            'name' => $name,
            'client_group_id' => $groupId,
            'cui' => $cui,
            'contact_person' => $contact,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'notes' => $notes,
        ];

        $id = Client::create($data);
        // Best-effort: creează adresa principală în tabela de adrese (dacă există).
        ClientAddress::ensureDefaultFromLegacy($id, $address);
        Audit::log('CLIENT_CREATE', 'clients', $id, null, $data, [
            'message' => 'A creat client: ' . $name . ' · ' . ($type === 'FIRMA' ? 'Firmă' : 'Persoană fizică'),
        ]);
        Session::flash('toast_success', 'Client creat.');
        Response::redirect('/clients');
    }

    public static function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $row = Client::find($id);
        if (!$row) {
            Session::flash('toast_error', 'Client inexistent.');
            Response::redirect('/clients');
        }

        $projects = [];
        try {
            $projects = Project::forClient($id);
        } catch (\Throwable $e) {
            $projects = [];
        }
        $addresses = ClientAddress::forClient($id);
        $group = null;
        $groupMembers = [];
        try {
            $gid = (int)($row['client_group_id'] ?? 0);
            $group = $gid > 0 ? ClientGroup::find($gid) : null;
            $groupMembers = $gid > 0 ? Client::othersInGroup($id, $gid) : [];
        } catch (\Throwable $e) {
            $group = null;
            $groupMembers = [];
        }

        echo View::render('clients/show', [
            'title' => 'Client',
            'row' => $row,
            'projects' => $projects,
            'addresses' => $addresses,
            'group' => $group,
            'groupMembers' => $groupMembers,
        ]);
    }

    public static function editForm(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $row = Client::find($id);
        if (!$row) {
            Session::flash('toast_error', 'Client inexistent.');
            Response::redirect('/clients');
        }
        echo View::render('clients/form', [
            'title' => 'Editează client',
            'mode' => 'edit',
            'row' => $row,
            'errors' => [],
            'types' => self::types(),
            'groups' => ClientGroup::forSelect(),
        ]);
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Client::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Client inexistent.');
            Response::redirect('/clients');
        }

        $check = Validator::required($_POST, [
            'type' => 'Tip client',
            'name' => 'Nume (client/companie)',
            'phone' => 'Telefon',
            'email' => 'Email',
            'address' => 'Adresă livrare',
        ]);
        $errors = $check['errors'];

        $type = trim((string)($_POST['type'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $groupIdRaw = trim((string)($_POST['client_group_id'] ?? ''));
        $groupNew = trim((string)($_POST['client_group_new'] ?? ''));
        $cui = trim((string)($_POST['cui'] ?? ''));
        $contact = trim((string)($_POST['contact_person'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $allowedTypes = array_map(fn($t) => (string)$t['value'], self::types());
        if ($type !== '' && !in_array($type, $allowedTypes, true)) {
            $errors['type'] = 'Tip invalid.';
        }
        if ($email !== '' && !Validator::email($email)) {
            $errors['email'] = 'Email invalid.';
        }
        if ($type === 'FIRMA' && $cui === '') {
            $errors['cui'] = 'CUI este obligatoriu pentru firmă.';
        }
        if ($groupNew !== '' && mb_strlen($groupNew) > 190) {
            $errors['client_group_new'] = 'Nume grup prea lung.';
        }

        if ($errors) {
            $row = array_merge($before, $_POST);
            echo View::render('clients/form', [
                'title' => 'Editează client',
                'mode' => 'edit',
                'row' => $row,
                'errors' => $errors,
                'types' => self::types(),
                'groups' => ClientGroup::forSelect(),
            ]);
            return;
        }

        $groupId = null;
        if ($groupNew !== '') {
            $gid = ClientGroup::upsertByName($groupNew);
            if ($gid > 0) $groupId = $gid;
        } elseif ($groupIdRaw !== '') {
            $gid = Validator::int($groupIdRaw, 1);
            if ($gid) $groupId = $gid;
        }

        $after = [
            'type' => $type,
            'name' => $name,
            'client_group_id' => $groupId,
            'cui' => $cui,
            'contact_person' => $contact,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'notes' => $notes,
        ];

        Client::update($id, $after);
        ClientAddress::ensureDefaultFromLegacy($id, $address);
        Audit::log('CLIENT_UPDATE', 'clients', $id, $before, $after, [
            'message' => 'A actualizat client: ' . $name,
        ]);
        Session::flash('toast_success', 'Client actualizat.');
        Response::redirect('/clients/' . $id);
    }

    public static function createAddress(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $clientId = (int)($params['id'] ?? 0);
        $client = Client::find($clientId);
        if (!$client) {
            Session::flash('toast_error', 'Client inexistent.');
            Response::redirect('/clients');
        }

        $address = trim((string)($_POST['address'] ?? ''));
        if ($address === '') {
            Session::flash('toast_error', 'Adresa este obligatorie.');
            Response::redirect('/clients/' . $clientId);
        }
        $label = trim((string)($_POST['label'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        try {
            $id = ClientAddress::create($clientId, [
                'label' => $label,
                'address' => $address,
                'notes' => $notes,
                'is_default' => $isDefault,
            ]);
            Audit::log('CLIENT_ADDRESS_CREATE', 'client_addresses', $id, null, null, [
                'message' => 'A adăugat adresă de livrare pentru client: ' . (string)$client['name'],
                'client_id' => $clientId,
            ]);
            Session::flash('toast_success', 'Adresă adăugată.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot adăuga adresa (verifică dacă tabelul a fost actualizat).');
        }
        Response::redirect('/clients/' . $clientId);
    }

    public static function updateAddress(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $clientId = (int)($params['id'] ?? 0);
        $addrId = (int)($params['addrId'] ?? 0);
        $client = Client::find($clientId);
        if (!$client) {
            Session::flash('toast_error', 'Client inexistent.');
            Response::redirect('/clients');
        }
        $before = ClientAddress::find($addrId);
        if (!$before || (int)($before['client_id'] ?? 0) !== $clientId) {
            Session::flash('toast_error', 'Adresă inexistentă.');
            Response::redirect('/clients/' . $clientId);
        }

        $address = trim((string)($_POST['address'] ?? ''));
        if ($address === '') {
            Session::flash('toast_error', 'Adresa este obligatorie.');
            Response::redirect('/clients/' . $clientId);
        }
        $label = trim((string)($_POST['label'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        try {
            ClientAddress::update($addrId, $clientId, [
                'label' => $label,
                'address' => $address,
                'notes' => $notes,
                'is_default' => $isDefault,
            ]);
            Audit::log('CLIENT_ADDRESS_UPDATE', 'client_addresses', $addrId, $before, null, [
                'message' => 'A actualizat o adresă de livrare pentru client: ' . (string)$client['name'],
                'client_id' => $clientId,
            ]);
            Session::flash('toast_success', 'Adresă actualizată.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot actualiza adresa.');
        }
        Response::redirect('/clients/' . $clientId);
    }

    public static function deleteAddress(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $clientId = (int)($params['id'] ?? 0);
        $addrId = (int)($params['addrId'] ?? 0);
        $client = Client::find($clientId);
        if (!$client) {
            Session::flash('toast_error', 'Client inexistent.');
            Response::redirect('/clients');
        }
        $before = ClientAddress::find($addrId);
        if (!$before || (int)($before['client_id'] ?? 0) !== $clientId) {
            Session::flash('toast_error', 'Adresă inexistentă.');
            Response::redirect('/clients/' . $clientId);
        }
        try {
            ClientAddress::delete($addrId, $clientId);
            Audit::log('CLIENT_ADDRESS_DELETE', 'client_addresses', $addrId, $before, null, [
                'message' => 'A șters o adresă de livrare pentru client: ' . (string)$client['name'],
                'client_id' => $clientId,
            ]);
            Session::flash('toast_success', 'Adresă ștearsă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge adresa.');
        }
        Response::redirect('/clients/' . $clientId);
    }

    public static function delete(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Client::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Client inexistent.');
            Response::redirect('/clients');
        }

        try {
            Client::delete($id);
            Audit::log('CLIENT_DELETE', 'clients', $id, $before, null, [
                'message' => 'A șters client: ' . (string)$before['name'],
            ]);
            Session::flash('toast_success', 'Client șters.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge clientul (posibil are proiecte asociate).');
        }
        Response::redirect('/clients');
    }

    public static function canWrite(): bool
    {
        $u = Auth::user();
        return $u && in_array((string)$u['role'], [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
    }

    public static function isAdmin(): bool
    {
        $u = Auth::user();
        return $u && (string)$u['role'] === Auth::ROLE_ADMIN;
    }
}

