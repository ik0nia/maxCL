<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DbMigrations;
use App\Core\Env;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Core\DB;
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
        return ['Depozit', 'Producție', 'Magazin', 'Depozit (Stricat)'];
    }

    /** @return array<string,string> */
    private static function statusLabels(): array
    {
        return [
            'AVAILABLE' => 'Disponibil',
            'RESERVED' => 'Rezervat / Indisponibil',
            'CONSUMED' => 'Consumat',
            'SCRAP' => 'Rebut / Stricat',
        ];
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

        // Afișare dimensiuni: Lungime × Lățime
        $label = trim("{$code} · {$name} · {$brand} · {$th}mm · {$stdH}×{$stdW}");
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
        $triedMigrate = false;
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
            // Încearcă încă o dată după auto-migrare (hosting poate servi request-ul înainte să se aplice).
            if (!$triedMigrate) {
                $triedMigrate = true;
                try {
                    DbMigrations::runAuto();
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
                    return;
                } catch (\Throwable $e2) {
                    $e = $e2;
                }
            }

            $u = Auth::user();
            $debug = Env::bool('APP_DEBUG', false) || ($u && strtolower((string)($u['email'] ?? '')) === 'sacodrut@ikonia.ro');
            $msg = 'Stoc indisponibil momentan.';
            if ($debug) {
                $msg .= ' Eroare: ' . get_class($e) . ' · ' . $e->getMessage() . ' · ' . basename((string)$e->getFile()) . ':' . (int)$e->getLine();
            } else {
                $msg .= ' Rulează din nou Setup (butonul „Instalează acum”) ca să creezi tabelele noi (textures/hpl_boards/hpl_stock_pieces).';
            }
            echo View::render('system/placeholder', [
                'title' => 'Stoc',
                'message' => $msg,
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
            $pieces = HplStockPiece::forBoard($id, true); // doar piese "în contabilitate"
            $internalPieces = HplStockPiece::forBoard($id, false); // piese interne (nestocabile)
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
                'internalPieces' => $internalPieces,
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

        // Regulă: Producție = indisponibil (RESERVED)
        $status = ($location === 'Producție') ? 'RESERVED' : 'AVAILABLE';
        if ($status === 'RESERVED') {
            Session::flash('toast_error', 'Locația „Producție” setează automat statusul ca Rezervat/Indisponibil.');
        }

        $data = [
            'board_id' => $boardId,
            'piece_type' => $type,
            'status' => $status,
            'width_mm' => $width,
            'height_mm' => $height,
            'qty' => (int)$_POST['qty'],
            'location' => $location,
            'notes' => trim((string)($_POST['notes'] ?? '')),
        ];

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            // Dacă există deja piesă identică (același tip/status/dim/locație), cumulăm qty.
            $existing = HplStockPiece::findIdentical(
                $boardId,
                (string)$data['piece_type'],
                (string)$data['status'],
                (int)$data['width_mm'],
                (int)$data['height_mm'],
                (string)$data['location'],
                1
            );
            if ($existing) {
                $before = $existing;
                HplStockPiece::incrementQty((int)$existing['id'], (int)$data['qty']);
                if (trim((string)$data['notes']) !== '') {
                    HplStockPiece::appendNote((int)$existing['id'], (string)$data['notes']);
                }
                $pieceId = (int)$existing['id'];
                $pdo->commit();

                $after = HplStockPiece::find($pieceId) ?: $before;
                $m2 = (($width * $height) / 1000000.0) * (int)$data['qty'];
                $boardLabel = self::fmtBoardLabel($board, (int)$board['face_color_id'], (int)$board['face_texture_id'], $board['back_color_id'] ? (int)$board['back_color_id'] : null, $board['back_texture_id'] ? (int)$board['back_texture_id'] : null);
                $msg = 'A adăugat ' . (int)$data['qty'] . ' buc la piesa existentă ' . $type . " {$height}×{$width} mm, locație " . (string)$data['location'] . ' (cumulare).';
                Audit::log('STOCK_PIECE_CREATE', 'hpl_stock_pieces', $pieceId, $before, $after, [
                    'message' => $msg . " · Placă: {$boardLabel}",
                    'board_id' => $boardId,
                    'board_code' => (string)($board['code'] ?? ''),
                    'requested_type' => $requestedType,
                    'final_type' => $type,
                    'width_mm' => $width,
                    'height_mm' => $height,
                    'qty' => (int)$data['qty'],
                    'area_m2' => $m2,
                    'location' => (string)$data['location'],
                    'merged_into_piece_id' => $pieceId,
                ]);
                Session::flash('toast_success', 'Piesă actualizată (cumulare).');
                Response::redirect('/stock/boards/' . $boardId);
            }

            $pieceId = HplStockPiece::create($data);
            $pdo->commit();
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot adăuga piesa.');
            Response::redirect('/stock/boards/' . $boardId);
        }
        $m2 = (($width * $height) / 1000000.0) * (int)$data['qty'];
        $boardLabel = self::fmtBoardLabel($board, (int)$board['face_color_id'], (int)$board['face_texture_id'], $board['back_color_id'] ? (int)$board['back_color_id'] : null, $board['back_texture_id'] ? (int)$board['back_texture_id'] : null);
        $msg = 'A adăugat piesă ' . $type . " {$height}×{$width} mm, " . (int)$data['qty'] . ' buc, ' . number_format($m2, 2, '.', '') . ' mp, locație ' . (string)$data['location'] . " · Placă: {$boardLabel}";
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

    // Mutare piesă (split pe qty) + schimbare locație/status (ADMIN/GESTIONAR)
    public static function movePiece(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $boardId = (int)($params['id'] ?? 0);
        $board = HplBoard::find($boardId);
        if (!$board) {
            Session::flash('toast_error', 'Placă inexistentă.');
            Response::redirect('/stock');
        }

        $check = Validator::required($_POST, [
            'from_piece_id' => 'Sursă',
            'qty' => 'Bucăți',
            'to_location' => 'Locație destinație',
            'to_status' => 'Status destinație',
            'note' => 'Notiță',
        ]);
        $errors = $check['errors'];

        $fromId = Validator::int((string)($_POST['from_piece_id'] ?? ''), 1) ?? null;
        $qty = Validator::int((string)($_POST['qty'] ?? ''), 1, 100000) ?? null;
        $toLocation = trim((string)($_POST['to_location'] ?? ''));
        $toStatus = trim((string)($_POST['to_status'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($fromId === null) $errors['from_piece_id'] = 'Selectează piesa sursă.';
        if ($qty === null) $errors['qty'] = 'Cantitate invalidă.';
        if ($toLocation === '' || !in_array($toLocation, self::locations(), true)) $errors['to_location'] = 'Locație invalidă.';
        $allowedStatuses = array_keys(self::statusLabels());
        // Regulă: dacă destinația este Producție, statusul este forțat pe RESERVED (indisponibil).
        if ($toLocation === 'Producție') {
            $toStatus = 'RESERVED';
        }
        if ($toStatus === '' || !in_array($toStatus, $allowedStatuses, true)) $errors['to_status'] = 'Status invalid.';
        if ($note === '') $errors['note'] = 'Notița este obligatorie.';

        if ($errors) {
            Session::flash('toast_error', 'Completează corect câmpurile pentru mutare.');
            Response::redirect('/stock/boards/' . $boardId);
        }

        $from = HplStockPiece::find((int)$fromId);
        if (!$from || (int)$from['board_id'] !== $boardId) {
            Session::flash('toast_error', 'Piesa sursă este invalidă.');
            Response::redirect('/stock/boards/' . $boardId);
        }

        $fromQty = (int)($from['qty'] ?? 0);
        if ($qty > $fromQty) {
            Session::flash('toast_error', 'Nu poți muta mai multe bucăți decât există în piesa sursă.');
            Response::redirect('/stock/boards/' . $boardId);
        }

        $labels = self::statusLabels();
        $fromStatus = (string)($from['status'] ?? '');
        $fromLoc = (string)($from['location'] ?? '');

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $destId = null;
            $updatedFrom = $from;
            $syncProject = null; // ['action'=>'update'|'delete','project_id'=>int,'consumption_id'=>int,'before'=>array,'after'=>array|null]

            $destExisting = HplStockPiece::findIdentical(
                $boardId,
                (string)$from['piece_type'],
                $toStatus,
                (int)$from['width_mm'],
                (int)$from['height_mm'],
                $toLocation,
                1,
                (int)$from['id']
            );

            if ($qty < $fromQty) {
                // Split: scade din sursă + creează/actualizează destinație
                HplStockPiece::updateQty((int)$from['id'], $fromQty - $qty);
                $updatedFrom['qty'] = $fromQty - $qty;

                if ($destExisting) {
                    $destId = (int)$destExisting['id'];
                    HplStockPiece::incrementQty($destId, $qty);
                    HplStockPiece::appendNote($destId, $note);
                } else {
                    $destData = [
                        'board_id' => $boardId,
                        'piece_type' => (string)$from['piece_type'],
                        'status' => $toStatus,
                        'width_mm' => (int)$from['width_mm'],
                        'height_mm' => (int)$from['height_mm'],
                        'qty' => $qty,
                        'location' => $toLocation,
                        'notes' => $note,
                    ];
                    $destId = HplStockPiece::create($destData);
                }
            } else {
                // Mută întreaga piesă: dacă există deja destinație identică, cumulăm și ștergem sursa.
                if ($destExisting) {
                    $destId = (int)$destExisting['id'];
                    HplStockPiece::incrementQty($destId, $qty);
                    HplStockPiece::appendNote($destId, $note);
                    HplStockPiece::delete((int)$from['id']);
                } else {
                    // Update pe aceeași piesă
                    $newNotes = $note;
                    $oldNotes = trim((string)($from['notes'] ?? ''));
                    if ($oldNotes !== '') $newNotes = $oldNotes . "\n" . $note;
                    HplStockPiece::updateFields((int)$from['id'], [
                        'status' => $toStatus,
                        'location' => $toLocation,
                        'notes' => $newNotes,
                    ]);
                    $destId = (int)$from['id'];
                }
            }

            // LOGIC FIX: dacă mutăm plăci întregi RESERVED -> AVAILABLE în Depozit,
            // sincronizăm și consumul din proiect (altfel rămâne "rezervare" în proiect, dar stocul e eliberat).
            $isReturnToStock = ((string)($from['piece_type'] ?? '') === 'FULL')
                && ($fromStatus === 'RESERVED')
                && ($toStatus === 'AVAILABLE')
                && ($toLocation === 'Depozit');
            if ($isReturnToStock) {
                // găsim ultimul HPL_STOCK_RESERVE pe placa asta, ca să știm ce consum să ajustăm
                $consumptionId = null;
                $projectId = null;
                $meta = null;
                try {
                    $st = $pdo->prepare("
                        SELECT id, meta_json
                        FROM audit_log
                        WHERE entity_type = 'hpl_boards'
                          AND entity_id = ?
                          AND action = 'HPL_STOCK_RESERVE'
                        ORDER BY id DESC
                        LIMIT 25
                    ");
                    $st->execute([(int)$boardId]);
                    $logs = $st->fetchAll();
                    foreach (($logs ?: []) as $lr) {
                        $mj = (string)($lr['meta_json'] ?? '');
                        if ($mj === '') continue;
                        $m = json_decode($mj, true);
                        if (!is_array($m)) continue;
                        $cid = (int)($m['consumption_id'] ?? 0);
                        $pid = (int)($m['project_id'] ?? 0);
                        if ($cid > 0) {
                            $consumptionId = $cid;
                            $projectId = $pid > 0 ? $pid : null;
                            $meta = $m;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // silent: nu blocăm mutarea dacă audit lookup eșuează
                }

                if ($consumptionId !== null && $consumptionId > 0) {
                    // lock consumption row
                    $st = $pdo->prepare('SELECT * FROM project_hpl_consumptions WHERE id = ? FOR UPDATE');
                    $st->execute([(int)$consumptionId]);
                    $c = $st->fetch();
                    if ($c && (int)($c['board_id'] ?? 0) === $boardId) {
                        $beforeC = $c;
                        $oldBoards = isset($c['qty_boards']) ? (int)($c['qty_boards'] ?? 0) : 0;
                        $oldM2 = (float)($c['qty_m2'] ?? 0);
                        // fallback dacă qty_boards nu există / e 0
                        $stdW = (int)($board['std_width_mm'] ?? 0);
                        $stdH = (int)($board['std_height_mm'] ?? 0);
                        $areaPer = ($stdW > 0 && $stdH > 0) ? (($stdW * $stdH) / 1000000.0) : 0.0;
                        if ($oldBoards <= 0 && $areaPer > 0.0 && $oldM2 > 0.0) {
                            $oldBoards = (int)round($oldM2 / $areaPer);
                        }

                        $newBoards = max(0, $oldBoards - (int)$qty);
                        $newM2 = ($areaPer > 0.0) ? ($areaPer * $newBoards) : (float)$oldM2;

                        if ($newBoards <= 0) {
                            $pdo->prepare('DELETE FROM project_hpl_consumptions WHERE id = ?')->execute([(int)$consumptionId]);
                            $syncProject = [
                                'action' => 'delete',
                                'project_id' => (int)($c['project_id'] ?? ($projectId ?? 0)),
                                'consumption_id' => (int)$consumptionId,
                                'before' => $beforeC,
                                'after' => null,
                                'meta' => $meta,
                            ];
                        } else {
                            // update consumption (qty_boards + qty_m2)
                            try {
                                $pdo->prepare('UPDATE project_hpl_consumptions SET qty_boards = ?, qty_m2 = ? WHERE id = ?')
                                    ->execute([(int)$newBoards, (float)$newM2, (int)$consumptionId]);
                            } catch (\Throwable $e) {
                                // compat: dacă qty_boards nu există, păstrăm doar qty_m2
                                $pdo->prepare('UPDATE project_hpl_consumptions SET qty_m2 = ? WHERE id = ?')
                                    ->execute([(float)$newM2, (int)$consumptionId]);
                            }

                            // scale allocations proportionally (best-effort)
                            try {
                                $oldBase = $oldM2 > 0.0 ? $oldM2 : (($areaPer > 0.0) ? ($areaPer * $oldBoards) : 0.0);
                                $factor = $oldBase > 0.0 ? ($newM2 / $oldBase) : 0.0;
                                $stA = $pdo->prepare('SELECT project_product_id, qty_m2 FROM project_hpl_allocations WHERE consumption_id = ?');
                                $stA->execute([(int)$consumptionId]);
                                $allocs = $stA->fetchAll();
                                if ($allocs) {
                                    $upA = $pdo->prepare('UPDATE project_hpl_allocations SET qty_m2 = ? WHERE consumption_id = ? AND project_product_id = ?');
                                    foreach ($allocs as $ar) {
                                        $ppid = (int)($ar['project_product_id'] ?? 0);
                                        $m2 = (float)($ar['qty_m2'] ?? 0);
                                        if ($ppid <= 0 || $m2 <= 0) continue;
                                        $upA->execute([(float)max(0.0, $m2 * $factor), (int)$consumptionId, $ppid]);
                                    }
                                }
                            } catch (\Throwable $e) {
                                // ignore allocations scaling failures
                            }

                            $st2 = $pdo->prepare('SELECT * FROM project_hpl_consumptions WHERE id = ?');
                            $st2->execute([(int)$consumptionId]);
                            $afterC = $st2->fetch() ?: $beforeC;
                            $syncProject = [
                                'action' => 'update',
                                'project_id' => (int)($c['project_id'] ?? ($projectId ?? 0)),
                                'consumption_id' => (int)$consumptionId,
                                'before' => $beforeC,
                                'after' => $afterC,
                                'meta' => $meta,
                            ];
                        }
                    }
                }
            }

            $pdo->commit();

            $dim = (int)$from['height_mm'] . '×' . (int)$from['width_mm'] . ' mm';
            $msg = 'A mutat ' . (int)$qty . ' buc ' . (string)$from['piece_type'] . " {$dim} din {$fromLoc} (" . ($labels[$fromStatus] ?? $fromStatus) . ') în ' . $toLocation . ' (' . ($labels[$toStatus] ?? $toStatus) . ').';
            if ($destExisting) {
                $msg .= ' (cumulare)';
            }
            $msg .= ' Notă: ' . $note;
            Audit::log('STOCK_PIECE_MOVE', 'hpl_stock_pieces', $destId, $from, null, [
                'message' => $msg,
                'board_id' => $boardId,
                'from_piece_id' => (int)$from['id'],
                'to_piece_id' => (int)$destId,
                'qty' => (int)$qty,
                'piece_type' => (string)$from['piece_type'],
                'height_mm' => (int)$from['height_mm'],
                'width_mm' => (int)$from['width_mm'],
                'from_location' => $fromLoc,
                'to_location' => $toLocation,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
                'merged' => $destExisting ? true : false,
            ]);

            if ($syncProject && (int)($syncProject['project_id'] ?? 0) > 0) {
                $pid = (int)$syncProject['project_id'];
                $cid = (int)$syncProject['consumption_id'];
                $a = (string)($syncProject['action'] ?? '');
                $meta = is_array($syncProject['meta'] ?? null) ? $syncProject['meta'] : [];
                $projCode = (string)($meta['project_code'] ?? '');
                $projName = (string)($meta['project_name'] ?? '');
                $boardCode = (string)($meta['board_code'] ?? ((string)($board['code'] ?? '')));
                $txtProj = trim(($projCode !== '' ? $projCode : ('ID ' . $pid)) . ($projName !== '' ? (' · ' . $projName) : ''));
                if ($a === 'delete') {
                    Audit::log('PROJECT_HPL_CONSUMPTION_DELETE', 'projects', $pid, $syncProject['before'] ?? null, null, [
                        'message' => 'Sincronizare automată: rezervarea HPL #' . $cid . ' a fost ștearsă deoarece stocul a fost mutat manual înapoi în Depozit (AVAILABLE). Proiect: ' . $txtProj . ' · Placă: ' . $boardCode,
                        'project_id' => $pid,
                        'project_code' => $projCode !== '' ? $projCode : null,
                        'project_name' => $projName !== '' ? $projName : null,
                        'consumption_id' => $cid,
                        'board_id' => $boardId,
                        'board_code' => $boardCode !== '' ? $boardCode : null,
                        'via' => 'stock_move',
                    ]);
                } else {
                    Audit::log('PROJECT_HPL_CONSUMPTION_UPDATE', 'projects', $pid, $syncProject['before'] ?? null, $syncProject['after'] ?? null, [
                        'message' => 'Sincronizare automată: rezervarea HPL #' . $cid . ' a fost ajustată deoarece stocul a fost mutat manual înapoi în Depozit (AVAILABLE). Proiect: ' . $txtProj . ' · Placă: ' . $boardCode,
                        'project_id' => $pid,
                        'project_code' => $projCode !== '' ? $projCode : null,
                        'project_name' => $projName !== '' ? $projName : null,
                        'consumption_id' => $cid,
                        'board_id' => $boardId,
                        'board_code' => $boardCode !== '' ? $boardCode : null,
                        'via' => 'stock_move',
                    ]);
                }
                Session::flash('toast_success', 'Mutare efectuată. Consum proiect sincronizat.');
            } else {
                Session::flash('toast_success', 'Mutare efectuată.');
            }

        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot efectua mutarea: ' . $e->getMessage());
        }

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

            $msg = 'A șters piesă ' . (string)$before['piece_type'] . ' ' . (int)$before['height_mm'] . '×' . (int)$before['width_mm'] . ' mm, ' . (int)$before['qty'] . ' buc, ' . number_format($m2, 2, '.', '') . ' mp, locație ' . (string)$before['location'] . " · Placă: {$boardLabel}";

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

