<?php
declare(strict_types=1);

namespace App\Controllers\Hpl;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Texture;

final class TexturesController
{
    public static function index(): void
    {
        $rows = Texture::all();
        echo View::render('hpl/textures/index', [
            'title' => 'Texturi',
            'rows' => $rows,
        ]);
    }

    public static function createForm(): void
    {
        echo View::render('hpl/textures/form', [
            'title' => 'Textură nouă',
            'mode' => 'create',
            'row' => null,
            'errors' => [],
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $check = Validator::required($_POST, ['name' => 'Denumire']);
        $errors = $check['errors'];

        if ($errors) {
            echo View::render('hpl/textures/form', [
                'title' => 'Textură nouă',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        try {
            $data = [
                'code' => trim((string)($_POST['code'] ?? '')),
                'name' => trim((string)$_POST['name']),
            ];
            $id = Texture::create($data);
            Audit::log('TEXTURE_CREATE', 'textures', $id, null, $data);
            Session::flash('toast_success', 'Textură creată.');
            Response::redirect('/hpl/texturi');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/hpl/texturi/create');
        }
    }

    public static function editForm(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $row = Texture::find($id);
        if (!$row) {
            Session::flash('toast_error', 'Textură inexistentă.');
            Response::redirect('/hpl/texturi');
        }
        echo View::render('hpl/textures/form', [
            'title' => 'Editează textură',
            'mode' => 'edit',
            'row' => $row,
            'errors' => [],
        ]);
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Texture::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Textură inexistentă.');
            Response::redirect('/hpl/texturi');
        }

        $check = Validator::required($_POST, ['name' => 'Denumire']);
        $errors = $check['errors'];
        if ($errors) {
            $row = array_merge($before, $_POST);
            echo View::render('hpl/textures/form', [
                'title' => 'Editează textură',
                'mode' => 'edit',
                'row' => $row,
                'errors' => $errors,
            ]);
            return;
        }

        try {
            $after = [
                'code' => trim((string)($_POST['code'] ?? '')),
                'name' => trim((string)$_POST['name']),
            ];
            Texture::update($id, $after);
            Audit::log('TEXTURE_UPDATE', 'textures', $id, $before, $after);
            Session::flash('toast_success', 'Textură actualizată.');
            Response::redirect('/hpl/texturi');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/hpl/texturi/' . $id . '/edit');
        }
    }

    public static function delete(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Texture::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Textură inexistentă.');
            Response::redirect('/hpl/texturi');
        }

        try {
            Texture::delete($id);
            Audit::log('TEXTURE_DELETE', 'textures', $id, $before, null);
            Session::flash('toast_success', 'Textură ștearsă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge textura (posibil folosită la plăci).');
        }
        Response::redirect('/hpl/texturi');
    }
}

