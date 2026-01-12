<?php
declare(strict_types=1);

namespace App\Controllers\Catalog;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Finish;
use App\Models\Material;
use App\Models\Variant;

final class VariantsController
{
    public static function index(): void
    {
        $rows = Variant::allWithJoins();
        echo View::render('catalog/variants/index', [
            'title' => 'Variante',
            'rows' => $rows,
        ]);
    }

    public static function createForm(): void
    {
        echo View::render('catalog/variants/form', [
            'title' => 'Variantă nouă',
            'mode' => 'create',
            'row' => null,
            'errors' => [],
            'materials' => Material::forSelect(),
            'finishes' => Finish::forSelect(),
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'material_id' => 'Material',
            'finish_face_id' => 'Finisaj față',
        ]);
        $errors = $check['errors'];

        $materialId = Validator::int((string)($_POST['material_id'] ?? ''), 1) ?? null;
        $faceId = Validator::int((string)($_POST['finish_face_id'] ?? ''), 1) ?? null;
        $backRaw = trim((string)($_POST['finish_back_id'] ?? ''));
        $backId = $backRaw === '' ? null : (Validator::int($backRaw, 1) ?? null);

        if ($materialId === null) $errors['material_id'] = 'Selectează un material.';
        if ($faceId === null) $errors['finish_face_id'] = 'Selectează un finisaj pentru față.';
        if ($backRaw !== '' && $backId === null) $errors['finish_back_id'] = 'Finisajul verso este invalid.';

        if ($errors) {
            echo View::render('catalog/variants/form', [
                'title' => 'Variantă nouă',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
                'materials' => Material::forSelect(),
                'finishes' => Finish::forSelect(),
            ]);
            return;
        }

        try {
            $data = [
                'material_id' => $materialId,
                'finish_face_id' => $faceId,
                'finish_back_id' => $backId,
            ];
            $id = Variant::create($data);
            Audit::log('VARIANT_CREATE', 'material_variants', $id, null, $data);
            Session::flash('toast_success', 'Variantă creată.');
            Response::redirect('/catalog/variants');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/catalog/variants/create');
        }
    }

    public static function editForm(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $row = Variant::find($id);
        if (!$row) {
            Session::flash('toast_error', 'Variantă inexistentă.');
            Response::redirect('/catalog/variants');
        }

        echo View::render('catalog/variants/form', [
            'title' => 'Editează variantă',
            'mode' => 'edit',
            'row' => $row,
            'errors' => [],
            'materials' => Material::forSelect(),
            'finishes' => Finish::forSelect(),
        ]);
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Variant::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Variantă inexistentă.');
            Response::redirect('/catalog/variants');
        }

        $check = Validator::required($_POST, [
            'material_id' => 'Material',
            'finish_face_id' => 'Finisaj față',
        ]);
        $errors = $check['errors'];

        $materialId = Validator::int((string)($_POST['material_id'] ?? ''), 1) ?? null;
        $faceId = Validator::int((string)($_POST['finish_face_id'] ?? ''), 1) ?? null;
        $backRaw = trim((string)($_POST['finish_back_id'] ?? ''));
        $backId = $backRaw === '' ? null : (Validator::int($backRaw, 1) ?? null);

        if ($materialId === null) $errors['material_id'] = 'Selectează un material.';
        if ($faceId === null) $errors['finish_face_id'] = 'Selectează un finisaj pentru față.';
        if ($backRaw !== '' && $backId === null) $errors['finish_back_id'] = 'Finisajul verso este invalid.';

        if ($errors) {
            $row = array_merge($before, $_POST);
            echo View::render('catalog/variants/form', [
                'title' => 'Editează variantă',
                'mode' => 'edit',
                'row' => $row,
                'errors' => $errors,
                'materials' => Material::forSelect(),
                'finishes' => Finish::forSelect(),
            ]);
            return;
        }

        try {
            $after = [
                'material_id' => $materialId,
                'finish_face_id' => $faceId,
                'finish_back_id' => $backId,
            ];
            Variant::update($id, $after);
            Audit::log('VARIANT_UPDATE', 'material_variants', $id, $before, $after);
            Session::flash('toast_success', 'Variantă actualizată.');
            Response::redirect('/catalog/variants');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/catalog/variants/' . $id . '/edit');
        }
    }

    public static function delete(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = Variant::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Variantă inexistentă.');
            Response::redirect('/catalog/variants');
        }

        try {
            Variant::delete($id);
            Audit::log('VARIANT_DELETE', 'material_variants', $id, $before, null);
            Session::flash('toast_success', 'Variantă ștearsă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge varianta (posibil folosită în stoc).');
        }
        Response::redirect('/catalog/variants');
    }
}

