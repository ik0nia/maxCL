<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Finish;
use App\Models\HplBoard;
use App\Models\HplStockPiece;
use App\Models\Texture;

final class StockController
{
    /** @return array<int,string> */
    private static function locations(): array
    {
        return ['Depozit', 'Producție', 'Magazin'];
    }

    private static function fmtBoardLabel(array $board, ?int $faceColorId, ?int $faceTextureId, ?int $backColorId, ?int $backTextureId): string
    {
        $faceColor = $faceColorId ? Finish::find($faceColorId) : null;
        $faceTex = $faceTextureId ? Texture::find($faceTextureId) : null;
        $backColor = $backColorId ? Finish::find($backColorId) : null;
        $backTex = $backTextureId ? Texture::find($backTextureId) : null;

        $face = trim(
            (($faceColor['color_name'] ?? '') ? (string)$faceColor['color_name'] : '—') .
            (($faceTex['name'] ?? '') ? (' / ' . (string)$faceTex['name']) : '')
        );
        $back = '';
        if ($backColor || $backTex) {
            $back = trim(
                (($backColor['color_name'] ?? '') ? (string)$backColor['color_name'] : '—') .
                (($backTex['name'] ?? '') ? (' / ' . (string)$backTex['name']) : '')
            );
        }

        $code = (string)($board['code'] ?? '');
        $name = (string)($board['name'] ?? '');
        $brand = (string)($board['brand'] ?? '');
        $th = (int)($board['thickness_mm'] ?? 0);
        $stdW = (int)($board['std_width_mm'] ?? 0);
        $stdH = (int)($board['std_height_mm'] ?? 0);

        $label = trim("{$code} · {$name} · {$brand} · {$th}mm · {$stdW}×{$stdH}");
        if ($back !== '') {
            $label .= " · Față: {$face} · Verso: {$back}";
        } else {
            $label .= " · Față: {$face}";
        }

        $salePrice = $board['sale_price'] ?? null;
        if ($salePrice !== null && $salePrice !== '' && is_numeric($salePrice)) {
            $sp = (float)$salePrice;
            if ($sp >= 0) {
                $area = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
                $perM2 = ($area > 0) ? ($sp / $area) : null;
                $label .= ' · Preț: ' . number_format($sp, 2, '.', '') . ' lei/placă';
                if ($perM2 !== null && is_finite($perM2)) {
                    $label .= ' (' . number_format($perM2, 2, '.', '') . ' lei/mp)';
                }
            }
        }
        return $label;
    }

    public static function index(): void
    {
        try {
            // Filtru culoare: acceptă atât cod (ex: 617) cât și id intern (compat)
            $colorId = null;
            $color = null;
            $colorRaw = isset($_GET['color']) ? trim((string)$_GET['color']) : '';
            if ($colorRaw !== '') {
                $color = Finish::findByCode($colorRaw);
                if (!$color) {
                    $maybeId = Validator::int($colorRaw, 1);
                    if ($maybeId) $color = Finish::find($maybeId);
                }
            } elseif (isset($_GET['color_id']) && (string)$_GET['color_id'] !== '') {
                $maybeId = Validator::int((string)$_GET['color_id'], 1);
                if ($maybeId) $color = Finish::find($maybeId);
            }
            if ($color) $colorId = (int)$color['id'];

            $thicknessMm = null;
            if (isset($_GET['thickness_mm']) && (string)$_GET['thickness_mm'] !== '') {
                $thicknessMm = Validator::int((string)$_GET['thickness_mm'], 1);
            }
            $rows = HplBoard::allWithTotals($colorId ?: null, $thicknessMm ?: null);
            echo View::render('stock/index', [
                'title' => 'Stoc',
                'rows' => $rows,
                'filterColor' => $color,
                'filterColorQuery' => $color ? (string)($color['code'] ?? '') : $colorRaw,
                'filterThicknessMm' => $thicknessMm,
                'thicknessOptions' => HplBoard::thicknessOptions(),
            ]);
        } catch (\Throwable $e) {
            // Cel mai des: tabelele noi nu există încă (nu s-a rulat setup după update).
            echo View::render('system/placeholder', [
                'title' => 'Stoc',
                'message' => 'Stoc indisponibil momentan. Rulează din nou Setup (butonul „Instalează acum”) ca să creezi tabelele noi (textures/hpl_boards/hpl_stock_pieces).',
            ]);
        }
    }

    public static function createBoardForm(): void
    {
        try {
            echo View::render('stock/board_form', [
                'title' => 'Placă nouă',
                'mode' => 'create',
                'row' => null,
                'errors' => [],
                'colors' => [],
                'textures' => Texture::forSelect(),
            ]);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot încărca formularul. Rulează Setup pentru a crea tabelele necesare.');
            Response::redirect('/setup');
        }
    }

    public static function editBoardForm(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        try {
            $board = HplBoard::find($id);
            if (!$board) {
                Session::flash('toast_error', 'Placă inexistentă.');
                Response::redirect('/stock');
            }
            $colorIds = [(int)($board['face_color_id'] ?? 0), (int)($board['back_color_id'] ?? 0)];
            echo View::render('stock/board_form', [
                'title' => 'Editează placă',
                'mode' => 'edit',
                'row' => $board,
                'errors' => [],
                'colors' => Finish::forSelectByIds($colorIds),
                'textures' => Texture::forSelect(),
            ]);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot încărca placa. Rulează Setup dacă lipsesc tabele.');
            Response::redirect('/stock');
        }
    }

    public static function updateBoard(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = HplBoard::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Placă inexistentă.');
            Response::redirect('/stock');
        }

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'name' => 'Denumire',
            'brand' => 'Brand',
            'thickness_mm' => 'Grosime (mm)',
            'std_width_mm' => 'Lățime standard (mm)',
            'std_height_mm' => 'Lungime standard (mm)',
            'face_color_id' => 'Culoare față',
            'face_texture_id' => 'Textură față',
        ]);
        $errors = $check['errors'];

        foreach (['thickness_mm' => 1, 'std_width_mm' => 1, 'std_height_mm' => 1] as $k => $min) {
            if (!empty($_POST[$k] ?? '') && Validator::int((string)$_POST[$k], $min, 100000) === null) {
                $errors[$k] = 'Valoare invalidă.';
            }
        }

        $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
        $salePrice = null;
        if ($salePriceRaw !== '') {
            $salePrice = Validator::dec($salePriceRaw);
            if ($salePrice === null || $salePrice < 0 || $salePrice > 100000000) {
                $errors['sale_price'] = 'Preț invalid.';
            }
        }

        $faceColor = Validator::int((string)($_POST['face_color_id'] ?? ''), 1) ?? null;
        $faceTex = Validator::int((string)($_POST['face_texture_id'] ?? ''), 1) ?? null;
        $backColorRaw = trim((string)($_POST['back_color_id'] ?? ''));
        $backTexRaw = trim((string)($_POST['back_texture_id'] ?? ''));
        $backColor = $backColorRaw === '' ? null : (Validator::int($backColorRaw, 1) ?? null);
        $backTex = $backTexRaw === '' ? null : (Validator::int($backTexRaw, 1) ?? null);

        if ($faceColor === null) $errors['face_color_id'] = 'Selectează culoarea feței.';
        if ($faceTex === null) $errors['face_texture_id'] = 'Selectează textura feței.';
        if ($backColorRaw !== '' && $backColor === null) $errors['back_color_id'] = 'Culoarea verso este invalidă.';
        if ($backTexRaw !== '' && $backTex === null) $errors['back_texture_id'] = 'Textura verso este invalidă.';

        if ($errors) {
            $row = array_merge($before, $_POST);
            $colorIds = [
                (int)($row['face_color_id'] ?? 0),
                (int)($row['back_color_id'] ?? 0),
            ];
            echo View::render('stock/board_form', [
                'title' => 'Editează placă',
                'mode' => 'edit',
                'row' => $row,
                'errors' => $errors,
                'colors' => Finish::forSelectByIds($colorIds),
                'textures' => Texture::forSelect(),
            ]);
            return;
        }

        try {
            $after = [
                'code' => trim((string)$_POST['code']),
                'name' => trim((string)$_POST['name']),
                'brand' => trim((string)$_POST['brand']),
                'thickness_mm' => (int)$_POST['thickness_mm'],
                'std_width_mm' => (int)$_POST['std_width_mm'],
                'std_height_mm' => (int)$_POST['std_height_mm'],
                'sale_price' => $salePrice,
                'face_color_id' => $faceColor,
                'face_texture_id' => $faceTex,
                'back_color_id' => $backColor,
                'back_texture_id' => $backTex,
                'notes' => trim((string)($_POST['notes'] ?? '')),
            ];
            HplBoard::update($id, $after);
            $msg = 'A actualizat placa: ' . self::fmtBoardLabel($after, $faceColor, $faceTex, $backColor, $backTex);
            Audit::log('BOARD_UPDATE', 'hpl_boards', $id, $before, $after, ['message' => $msg]);
            Session::flash('toast_success', 'Placă actualizată.');
            Response::redirect('/stock/boards/' . $id);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/stock/boards/' . $id . '/edit');
        }
    }

    public static function deleteBoard(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int)($params['id'] ?? 0);
        $before = HplBoard::find($id);
        if (!$before) {
            Session::flash('toast_error', 'Placă inexistentă.');
            Response::redirect('/stock');
        }

        try {
            $cnt = HplStockPiece::countForBoard($id);
            if ($cnt > 0) {
                Session::flash('toast_error', 'Nu pot șterge placa: există piese asociate în stoc. Șterge întâi piesele.');
                Response::redirect('/stock/boards/' . $id);
            }

            HplBoard::delete($id);
            $msg = 'A șters placa: ' . self::fmtBoardLabel($before, (int)$before['face_color_id'], (int)$before['face_texture_id'], $before['back_color_id'] ? (int)$before['back_color_id'] : null, $before['back_texture_id'] ? (int)$before['back_texture_id'] : null);
            Audit::log('BOARD_DELETE', 'hpl_boards', $id, $before, null, ['message' => $msg]);
            Session::flash('toast_success', 'Placă ștearsă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge placa.');
        }
        Response::redirect('/stock');
    }

    public static function createBoard(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);

        $check = Validator::required($_POST, [
            'code' => 'Cod',
            'name' => 'Denumire',
            'brand' => 'Brand',
            'thickness_mm' => 'Grosime (mm)',
            'std_width_mm' => 'Lățime standard (mm)',
            'std_height_mm' => 'Lungime standard (mm)',
            'face_color_id' => 'Culoare față',
            'face_texture_id' => 'Textură față',
        ]);
        $errors = $check['errors'];

        foreach (['thickness_mm' => 1, 'std_width_mm' => 1, 'std_height_mm' => 1] as $k => $min) {
            if (!empty($_POST[$k] ?? '') && Validator::int((string)$_POST[$k], $min, 100000) === null) {
                $errors[$k] = 'Valoare invalidă.';
            }
        }

        $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
        $salePrice = null;
        if ($salePriceRaw !== '') {
            $salePrice = Validator::dec($salePriceRaw);
            if ($salePrice === null || $salePrice < 0 || $salePrice > 100000000) {
                $errors['sale_price'] = 'Preț invalid.';
            }
        }

        $faceColor = Validator::int((string)($_POST['face_color_id'] ?? ''), 1) ?? null;
        $faceTex = Validator::int((string)($_POST['face_texture_id'] ?? ''), 1) ?? null;
        $backColorRaw = trim((string)($_POST['back_color_id'] ?? ''));
        $backTexRaw = trim((string)($_POST['back_texture_id'] ?? ''));
        $backColor = $backColorRaw === '' ? null : (Validator::int($backColorRaw, 1) ?? null);
        $backTex = $backTexRaw === '' ? null : (Validator::int($backTexRaw, 1) ?? null);

        if ($faceColor === null) $errors['face_color_id'] = 'Selectează culoarea feței.';
        if ($faceTex === null) $errors['face_texture_id'] = 'Selectează textura feței.';
        if ($backColorRaw !== '' && $backColor === null) $errors['back_color_id'] = 'Culoarea verso este invalidă.';
        if ($backTexRaw !== '' && $backTex === null) $errors['back_texture_id'] = 'Textura verso este invalidă.';

        if ($errors) {
            echo View::render('stock/board_form', [
                'title' => 'Placă nouă',
                'mode' => 'create',
                'row' => $_POST,
                'errors' => $errors,
                'colors' => Finish::forSelectByIds([
                    (int)($_POST['face_color_id'] ?? 0),
                    (int)($_POST['back_color_id'] ?? 0),
                ]),
                'textures' => Texture::forSelect(),
            ]);
            return;
        }

        try {
            $data = [
                'code' => trim((string)$_POST['code']),
                'name' => trim((string)$_POST['name']),
                'brand' => trim((string)$_POST['brand']),
                'thickness_mm' => (int)$_POST['thickness_mm'],
                'std_width_mm' => (int)$_POST['std_width_mm'],
                'std_height_mm' => (int)$_POST['std_height_mm'],
                'sale_price' => $salePrice,
                'face_color_id' => $faceColor,
                'face_texture_id' => $faceTex,
                'back_color_id' => $backColor,
                'back_texture_id' => $backTex,
                'notes' => trim((string)($_POST['notes'] ?? '')),
            ];
            $id = HplBoard::create($data);
            $msg = 'A creat placa: ' . self::fmtBoardLabel($data, $faceColor, $faceTex, $backColor, $backTex);
            Audit::log('BOARD_CREATE', 'hpl_boards', $id, null, $data, ['message' => $msg]);
            Session::flash('toast_success', 'Placă creată.');
            Response::redirect('/stock');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Eroare: ' . $e->getMessage());
            Response::redirect('/stock/boards/create');
        }
    }

    public static function boardDetails(array $params): void
    {
        try {
            $id = (int)($params['id'] ?? 0);
            $board = HplBoard::find($id);
            if (!$board) {
                Session::flash('toast_error', 'Placă inexistentă.');
                Response::redirect('/stock');
            }
            $pieces = HplStockPiece::forBoard($id);
            $history = [];
            try {
                $history = AuditLog::forBoard($id, 120);
            } catch (\Throwable $e) {
                $history = [];
            }
            echo View::render('stock/board_details', [
                'title' => 'Stoc · Placă',
                'board' => $board,
                'pieces' => $pieces,
                'history' => $history,
            ]);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Stoc indisponibil. Rulează Setup.');
            Response::redirect('/setup');
        }
    }

    public static function addPiece(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $boardId = (int)($params['id'] ?? 0);
        $board = HplBoard::find($boardId);
        if (!$board) {
            Session::flash('toast_error', 'Placă inexistentă.');
            Response::redirect('/stock');
        }

        $check = Validator::required($_POST, [
            'piece_type' => 'Tip piesă',
            'width_mm' => 'Lățime (mm)',
            'height_mm' => 'Lungime (mm)',
            'qty' => 'Bucăți',
            'location' => 'Locație',
        ]);
        $errors = $check['errors'];

        $type = (string)($_POST['piece_type'] ?? '');
        if (!in_array($type, ['FULL', 'OFFCUT'], true)) $errors['piece_type'] = 'Tip piesă invalid.';

        foreach (['width_mm','height_mm','qty'] as $k) {
            if (!empty($_POST[$k] ?? '') && Validator::int((string)$_POST[$k], 1, 100000) === null) {
                $errors[$k] = 'Valoare invalidă.';
            }
        }

        $location = trim((string)($_POST['location'] ?? ''));
        if ($location !== '' && !in_array($location, self::locations(), true)) {
            $errors['location'] = 'Locație invalidă.';
        }

        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile piesei.');
            Response::redirect('/stock/boards/' . $boardId);
        }

        $width = (int)$_POST['width_mm'];
        $height = (int)$_POST['height_mm'];
        $stdW = (int)($board['std_width_mm'] ?? 0);
        $stdH = (int)($board['std_height_mm'] ?? 0);
        $requestedType = $type;

        // Regula: FULL = doar dimensiuni standard. Dacă diferă -> OFFCUT automat.
        if ($type === 'FULL' && ($width !== $stdW || $height !== $stdH)) {
            $type = 'OFFCUT';
            Session::flash('toast_error', 'Dimensiunile diferă de standard; piesa a fost salvată automat ca OFFCUT.');
        }

        $data = [
            'board_id' => $boardId,
            'piece_type' => $type,
            'status' => 'AVAILABLE',
            'width_mm' => $width,
            'height_mm' => $height,
            'qty' => (int)$_POST['qty'],
            'location' => $location,
            'notes' => trim((string)($_POST['notes'] ?? '')),
        ];

        $pieceId = HplStockPiece::create($data);
        $m2 = (($width * $height) / 1000000.0) * (int)$data['qty'];
        $boardLabel = self::fmtBoardLabel($board, (int)$board['face_color_id'], (int)$board['face_texture_id'], $board['back_color_id'] ? (int)$board['back_color_id'] : null, $board['back_texture_id'] ? (int)$board['back_texture_id'] : null);
        $msg = 'A adăugat piesă ' . $type . " {$width}×{$height} mm, " . (int)$data['qty'] . ' buc, ' . number_format($m2, 2, '.', '') . ' mp, locație ' . (string)$data['location'] . " · Placă: {$boardLabel}";
        Audit::log('STOCK_PIECE_CREATE', 'hpl_stock_pieces', $pieceId, null, $data, [
            'message' => $msg,
            'board_id' => $boardId,
            'board_code' => (string)($board['code'] ?? ''),
            'requested_type' => $requestedType,
            'final_type' => $type,
            'width_mm' => $width,
            'height_mm' => $height,
            'qty' => (int)$data['qty'],
            'area_m2' => $m2,
            'location' => (string)$data['location'],
            'std_width_mm' => $stdW,
            'std_height_mm' => $stdH,
        ]);
        Session::flash('toast_success', 'Piesă adăugată în stoc.');
        Response::redirect('/stock/boards/' . $boardId);
    }

    // Ștergere piesă (ADMIN/GESTIONAR)
    public static function deletePiece(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $boardId = (int)($params['boardId'] ?? 0);
        $pieceId = (int)($params['pieceId'] ?? 0);
        $before = HplStockPiece::find($pieceId);
        if (!$before || (int)$before['board_id'] !== $boardId) {
            Session::flash('toast_error', 'Piesă inexistentă.');
            Response::redirect('/stock/boards/' . $boardId);
        }

        try {
            HplStockPiece::delete($pieceId);
            $m2 = (((int)$before['width_mm'] * (int)$before['height_mm']) / 1000000.0) * (int)$before['qty'];
            $board = HplBoard::find($boardId);
            $boardLabel = $board
                ? self::fmtBoardLabel(
                    $board,
                    (int)$board['face_color_id'],
                    (int)$board['face_texture_id'],
                    $board['back_color_id'] ? (int)$board['back_color_id'] : null,
                    $board['back_texture_id'] ? (int)$board['back_texture_id'] : null
                )
                : ('ID ' . $boardId);

            $msg = 'A șters piesă ' . (string)$before['piece_type'] . ' ' . (int)$before['width_mm'] . '×' . (int)$before['height_mm'] . ' mm, ' . (int)$before['qty'] . ' buc, ' . number_format($m2, 2, '.', '') . ' mp, locație ' . (string)$before['location'] . " · Placă: {$boardLabel}";

            Audit::log('STOCK_PIECE_DELETE', 'hpl_stock_pieces', $pieceId, $before, null, [
                'message' => $msg,
                'board_id' => $boardId,
                'board_code' => $board ? (string)($board['code'] ?? '') : null,
                'area_m2' => $m2,
                'width_mm' => (int)$before['width_mm'],
                'height_mm' => (int)$before['height_mm'],
                'qty' => (int)$before['qty'],
                'location' => (string)$before['location'],
            ]);
            Session::flash('toast_success', 'Piesă ștearsă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge piesa.');
        }
        Response::redirect('/stock/boards/' . $boardId);
    }
}

