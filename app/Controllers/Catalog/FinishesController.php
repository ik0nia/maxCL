<?php
declare(strict_types=1);

namespace App\Controllers\Catalog;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Core\Upload;
use App\Models\Finish;

final class FinishesController
{
    public static function index(): void
    {
        $rows = Finish::all();
        echo View::render('catalog/finishes/index', [
            'title' => 'Finisaje',
            'rows' => $rows,
        ]);
    }

    public static function createForm(): void
    {
        echo View::render('catalog/finishes/form', [
            'title' => 'Finisaj nou',
            'mode' => 'create',
            'row' => null,
            'errors' => [],
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'color_name' => 'Nume culoare',
            'texture_name' => 'Nume textură',
        ]);
        $errors = $check['errors'];

        if (empty($_FILES['image']['name'] ?? '')) {
            $errors['image'] = 'Thumbnail-ul este obligatoriu (încarcă o imagine).';
        }

        if ($errors) {
            echo View::render('catalog/finishes/form', [
                'title' => 'Finisaj nou',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        try {
            $upload = Upload::saveFinishImage($_FILES['image']);
            $data = [
                'code' => trim((string)$_POST['code']),
                'color_name' => trim((string)$_POST['color_name']),
                'color_code' => trim((string)($_POST['color_code'] ?? '')),
                'texture_name' => trim((string)$_POST['texture_name']),
                'texture_code' => trim((string)($_POST['texture_code'] ?? '')),
                'thumb_path' => $upload['thumb_url'],
                'image_path' => $upload['image_url'],
            ];

            $id = Finish::create($data);
            Audit::log('FINISH_CREATE', 'finishes', $id, null, $data);
            Session::flash('toast_success', 'Finisaj creat.');
            Response::redirect('/catalog/finishes');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/catalog/finishes/create');
        }
    }

    public static function editForm(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $row = Finish::find($id);
        if (!$row) {
            Session::flash('toast_error', 'Finisaj inexistent.');
            Response::redirect('/catalog/finishes');
        }

        echo View::render('catalog/finishes/form', [
            'title' => 'Editează finisaj',
            'mode' => 'edit',
            'row' => $row,
            'errors' => [],
        ]);
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Finish::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Finisaj inexistent.');
            Response::redirect('/catalog/finishes');
        }

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'color_name' => 'Nume culoare',
            'texture_name' => 'Nume textură',
        ]);
        $errors = $check['errors'];

        if ($errors) {
            $row = array_merge($before, $_POST);
            echo View::render('catalog/finishes/form', [
                'title' => 'Editează finisaj',
                'mode' => 'edit',
                'row' => $row,
                'errors' => $errors,
            ]);
            return;
        }

        try {
            $thumb = (string)$before['thumb_path'];
            $img = (string)($before['image_path'] ?? '');
            if (!empty($_FILES['image']['name'] ?? '')) {
                $upload = Upload::saveFinishImage($_FILES['image']);
                $thumb = $upload['thumb_url'];
                $img = $upload['image_url'];
            }

            $after = [
                'code' => trim((string)$_POST['code']),
                'color_name' => trim((string)$_POST['color_name']),
                'color_code' => trim((string)($_POST['color_code'] ?? '')),
                'texture_name' => trim((string)$_POST['texture_name']),
                'texture_code' => trim((string)($_POST['texture_code'] ?? '')),
                'thumb_path' => $thumb,
                'image_path' => $img ?: null,
            ];

            Finish::update($id, $after);
            Audit::log('FINISH_UPDATE', 'finishes', $id, $before, $after);
            Session::flash('toast_success', 'Finisaj actualizat.');
            Response::redirect('/catalog/finishes');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/catalog/finishes/' . $id . '/edit');
        }
    }

    public static function delete(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Finish::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Finisaj inexistent.');
            Response::redirect('/catalog/finishes');
        }

        try {
            Finish::delete($id);
            Audit::log('FINISH_DELETE', 'finishes', $id, $before, null);
            Session::flash('toast_success', 'Finisaj șters.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge finisajul (posibil folosit în variante).');
        }
        Response::redirect('/catalog/finishes');
    }
}

