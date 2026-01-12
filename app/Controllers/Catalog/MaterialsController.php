<?php
declare(strict_types=1);

namespace App\Controllers\Catalog;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Material;

final class MaterialsController
{
    public static function index(): void
    {
        $rows = Material::all();
        echo View::render('catalog/materials/index', [
            'title' => 'Materiale',
            'rows' => $rows,
        ]);
    }

    public static function createForm(): void
    {
        echo View::render('catalog/materials/form', [
            'title' => 'Material nou',
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
            'name' => 'Denumire',
            'brand' => 'Brand',
            'thickness_mm' => 'Grosime (mm)',
        ]);
        $errors = $check['errors'];

        if (!empty($_POST['thickness_mm'] ?? '') && Validator::int((string)$_POST['thickness_mm'], 1, 200) === null) {
            $errors['thickness_mm'] = 'Grosimea trebuie să fie un număr (1–200).';
        }

        if ($errors) {
            echo View::render('catalog/materials/form', [
                'title' => 'Material nou',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        try {
            $data = [
                'code' => trim((string)$_POST['code']),
                'name' => trim((string)$_POST['name']),
                'brand' => trim((string)$_POST['brand']),
                'thickness_mm' => (int)$_POST['thickness_mm'],
                'notes' => trim((string)($_POST['notes'] ?? '')),
                'track_stock' => isset($_POST['track_stock']) ? 1 : 0,
            ];

            $id = Material::create($data);
            Audit::log('MATERIAL_CREATE', 'materials', $id, null, $data);
            Session::flash('toast_success', 'Material creat.');
            Response::redirect('/catalog/materials');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/catalog/materials/create');
        }
    }

    public static function editForm(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $row = Material::find($id);
        if (!$row) {
            Session::flash('toast_error', 'Material inexistent.');
            Response::redirect('/catalog/materials');
        }

        echo View::render('catalog/materials/form', [
            'title' => 'Editează material',
            'mode' => 'edit',
            'row' => $row,
            'errors' => [],
        ]);
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Material::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Material inexistent.');
            Response::redirect('/catalog/materials');
        }

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'name' => 'Denumire',
            'brand' => 'Brand',
            'thickness_mm' => 'Grosime (mm)',
        ]);
        $errors = $check['errors'];

        if (!empty($_POST['thickness_mm'] ?? '') && Validator::int((string)$_POST['thickness_mm'], 1, 200) === null) {
            $errors['thickness_mm'] = 'Grosimea trebuie să fie un număr (1–200).';
        }

        if ($errors) {
            $row = array_merge($before, $_POST);
            echo View::render('catalog/materials/form', [
                'title' => 'Editează material',
                'mode' => 'edit',
                'row' => $row,
                'errors' => $errors,
            ]);
            return;
        }

        try {
            $after = [
                'code' => trim((string)$_POST['code']),
                'name' => trim((string)$_POST['name']),
                'brand' => trim((string)$_POST['brand']),
                'thickness_mm' => (int)$_POST['thickness_mm'],
                'notes' => trim((string)($_POST['notes'] ?? '')),
                'track_stock' => isset($_POST['track_stock']) ? 1 : 0,
            ];

            Material::update($id, $after);
            Audit::log('MATERIAL_UPDATE', 'materials', $id, $before, $after);
            Session::flash('toast_success', 'Material actualizat.');
            Response::redirect('/catalog/materials');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/catalog/materials/' . $id . '/edit');
        }
    }

    public static function delete(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Material::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Material inexistent.');
            Response::redirect('/catalog/materials');
        }

        try {
            Material::delete($id);
            Audit::log('MATERIAL_DELETE', 'materials', $id, $before, null);
            Session::flash('toast_success', 'Material șters.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge materialul (posibil folosit în variante / stoc).');
        }
        Response::redirect('/catalog/materials');
    }
}

