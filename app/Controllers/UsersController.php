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
use App\Models\User;

final class UsersController
{
    public static function index(): void
    {
        $rows = User::all();
        echo View::render('users/index', [
            'title' => 'Utilizatori',
            'rows' => $rows,
        ]);
    }

    public static function createForm(): void
    {
        echo View::render('users/form', [
            'title' => 'Utilizator nou',
            'mode' => 'create',
            'row' => null,
            'errors' => [],
            'roles' => self::roles(),
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'email' => 'Email',
            'name' => 'Nume',
            'role' => 'Rol',
            'password' => 'Parolă',
            'password_confirm' => 'Confirmare parolă',
        ]);
        $errors = $check['errors'];

        $email = trim((string)($_POST['email'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password_confirm'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($email !== '' && !Validator::email($email)) {
            $errors['email'] = 'Email invalid.';
        }
        if ($role !== '' && !in_array($role, self::roles(), true)) {
            $errors['role'] = 'Rol invalid.';
        }
        if ($pass !== '' && strlen($pass) < 8) {
            $errors['password'] = 'Parola trebuie să aibă minim 8 caractere.';
        }
        if ($pass !== $pass2) {
            $errors['password_confirm'] = 'Parolele nu coincid.';
        }
        if ($email !== '' && User::findByEmail($email)) {
            $errors['email'] = 'Există deja un utilizator cu acest email.';
        }

        if ($errors) {
            echo View::render('users/form', [
                'title' => 'Utilizator nou',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
                'roles' => self::roles(),
            ]);
            return;
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $data = [
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'is_active' => $isActive,
            'password_hash' => $hash,
        ];

        $id = User::create($data);
        Audit::log('USER_CREATE', 'users', $id, null, [
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'is_active' => $isActive,
        ], [
            'message' => 'A creat utilizator: ' . $name . ' (' . $email . ') · rol ' . $role,
        ]);
        Session::flash('toast_success', 'Utilizator creat.');
        Response::redirect('/users');
    }

    public static function editForm(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $row = User::findAuthRow($id);
        if (!$row) {
            Session::flash('toast_error', 'Utilizator inexistent.');
            Response::redirect('/users');
        }
        echo View::render('users/form', [
            'title' => 'Editează utilizator',
            'mode' => 'edit',
            'row' => $row,
            'errors' => [],
            'roles' => self::roles(),
        ]);
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = User::findAuthRow($id);
        if (!$before) {
            Session::flash('toast_error', 'Utilizator inexistent.');
            Response::redirect('/users');
        }

        $check = Validator::required($_POST, [
            'email' => 'Email',
            'name' => 'Nume',
            'role' => 'Rol',
        ]);
        $errors = $check['errors'];

        $email = trim((string)($_POST['email'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $pass = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password_confirm'] ?? '');

        if ($email !== '' && !Validator::email($email)) {
            $errors['email'] = 'Email invalid.';
        }
        if ($role !== '' && !in_array($role, self::roles(), true)) {
            $errors['role'] = 'Rol invalid.';
        }
        if ($pass !== '') {
            if (strlen($pass) < 8) $errors['password'] = 'Parola trebuie să aibă minim 8 caractere.';
            if ($pass !== $pass2) $errors['password_confirm'] = 'Parolele nu coincid.';
        }

        // Unicitate email (dacă s-a schimbat)
        if ($email !== '' && $email !== (string)$before['email']) {
            $exists = User::findByEmail($email);
            if ($exists) $errors['email'] = 'Există deja un utilizator cu acest email.';
        }

        // Nu permite dezactivarea propriei sesiuni
        if (Auth::id() === $id && $isActive === 0) {
            $errors['is_active'] = 'Nu îți poți dezactiva propriul cont.';
        }

        if ($errors) {
            $row = array_merge($before, $_POST);
            echo View::render('users/form', [
                'title' => 'Editează utilizator',
                'mode' => 'edit',
                'row' => $row,
                'errors' => $errors,
                'roles' => self::roles(),
            ]);
            return;
        }

        $after = [
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'is_active' => $isActive,
        ];

        User::update($id, $after);

        $pwdChanged = false;
        if ($pass !== '') {
            User::updatePassword($id, password_hash($pass, PASSWORD_DEFAULT));
            $pwdChanged = true;
        }

        Audit::log('USER_UPDATE', 'users', $id, [
            'email' => (string)$before['email'],
            'name' => (string)$before['name'],
            'role' => (string)$before['role'],
            'is_active' => (int)$before['is_active'],
        ], array_merge($after, ['password_changed' => $pwdChanged]), [
            'message' => 'A actualizat utilizator: ' . $after['name'] . ' (' . $after['email'] . ') · rol ' . $after['role'] . ($pwdChanged ? ' · parolă schimbată' : ''),
        ]);

        Session::flash('toast_success', 'Utilizator actualizat.');
        Response::redirect('/users');
    }

    /** @return array<int,string> */
    private static function roles(): array
    {
        return [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR, Auth::ROLE_VIEW];
    }
}

