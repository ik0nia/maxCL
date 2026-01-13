<?php
declare(strict_types=1);

namespace App\Controllers\Hpl;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Texture;

final class InlineTexturesController
{
    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $check = Validator::required($_POST, ['name' => 'Denumire']);
        if (!$check['ok']) {
            Session::flash('toast_error', 'Completează denumirea texturii.');
            Response::redirect('/hpl/tip-culoare');
        }

        try {
            $data = [
                'code' => trim((string)($_POST['code'] ?? '')),
                'name' => trim((string)$_POST['name']),
            ];
            $id = Texture::create($data);
            Audit::log('TEXTURE_CREATE', 'textures', $id, null, $data);
            Session::flash('toast_success', 'Textură creată.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
        }
        Response::redirect('/hpl/tip-culoare');
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Texture::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Textură inexistentă.');
            Response::redirect('/hpl/tip-culoare');
        }

        $check = Validator::required($_POST, ['name' => 'Denumire']);
        if (!$check['ok']) {
            Session::flash('toast_error', 'Completează denumirea texturii.');
            Response::redirect('/hpl/tip-culoare');
        }

        try {
            $after = [
                'code' => trim((string)($_POST['code'] ?? '')),
                'name' => trim((string)$_POST['name']),
            ];
            Texture::update($id, $after);
            Audit::log('TEXTURE_UPDATE', 'textures', $id, $before, $after);
            Session::flash('toast_success', 'Textură actualizată.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
        }
        Response::redirect('/hpl/tip-culoare');
    }

    public static function delete(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Texture::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Textură inexistentă.');
            Response::redirect('/hpl/tip-culoare');
        }

        try {
            Texture::delete($id);
            Audit::log('TEXTURE_DELETE', 'textures', $id, $before, null);
            Session::flash('toast_success', 'Textură ștearsă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge textura (posibil folosită la plăci).');
        }
        Response::redirect('/hpl/tip-culoare');
    }
}

