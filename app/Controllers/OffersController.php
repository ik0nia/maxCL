<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\AppSetting;
use App\Models\Audit;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\HplBoard;
use App\Models\HplStockPiece;
use App\Models\MagazieItem;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\OfferProductAccessory;
use App\Models\OfferProductHpl;
use App\Models\OfferWorkLog;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMagazieConsumption;
use App\Models\ProjectProduct;
use App\Models\ProjectProductHplConsumption;
use App\Models\ProjectWorkLog;
use PDO;

final class OffersController
{
    /** @return array<int, array{value:string,label:string}> */
    private static function statuses(): array
    {
        return [
            ['value' => 'DRAFT', 'label' => 'Draft'],
            ['value' => 'TRIMISA', 'label' => 'Trimisă'],
            ['value' => 'ACCEPTATA', 'label' => 'Acceptată'],
            ['value' => 'RESPINSA', 'label' => 'Respinsă'],
            ['value' => 'ANULATA', 'label' => 'Anulată'],
        ];
    }

    /** @return array<string,string> */
    private static function companySettingsForDocs(): array
    {
        $keys = [
            'company_name',
            'company_cui',
            'company_reg',
            'company_address',
            'company_phone',
            'company_email',
            'company_contact_name',
            'company_contact_phone',
            'company_contact_email',
            'company_contact_position',
            'company_logo_url',
            'company_logo_thumb_url',
        ];
        $vals = AppSetting::getMany($keys);
        $name = trim((string)($vals['company_name'] ?? ''));
        if ($name === '') $name = 'HPL Manager';
        return [
            'name' => $name,
            'cui' => trim((string)($vals['company_cui'] ?? '')),
            'reg' => trim((string)($vals['company_reg'] ?? '')),
            'address' => trim((string)($vals['company_address'] ?? '')),
            'phone' => trim((string)($vals['company_phone'] ?? '')),
            'email' => trim((string)($vals['company_email'] ?? '')),
            'contact_name' => trim((string)($vals['company_contact_name'] ?? '')),
            'contact_phone' => trim((string)($vals['company_contact_phone'] ?? '')),
            'contact_email' => trim((string)($vals['company_contact_email'] ?? '')),
            'contact_position' => trim((string)($vals['company_contact_position'] ?? '')),
            'logo_url' => trim((string)($vals['company_logo_url'] ?? '')),
            'logo_thumb' => trim((string)($vals['company_logo_thumb_url'] ?? '')),
        ];
    }

    private static function fmtMoney(?float $v): string
    {
        if ($v === null || !is_finite($v)) return '0.00';
        return number_format((float)$v, 2, '.', '');
    }

    private static function fmtQty(?float $v): string
    {
        if ($v === null || !is_finite($v)) return '0';
        $v = (float)$v;
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    public static function index(): void
    {
        $q = isset($_GET['q']) ? trim((string)($_GET['q'] ?? '')) : '';
        $status = isset($_GET['status']) ? trim((string)($_GET['status'] ?? '')) : '';
        $rows = [];
        try {
            $rows = Offer::all($q, $status, 500);
        } catch (\Throwable $e) {
            $rows = [];
        }
        $statuses = self::statuses();
        echo View::render('offers/index', [
            'title' => 'Oferte',
            'rows' => $rows,
            'q' => $q,
            'status' => $status,
            'statuses' => $statuses,
        ]);
    }

    public static function createForm(): void
    {
        $row = [
            'code' => Offer::nextAutoCode(10000),
            'status' => 'DRAFT',
        ];
        $statuses = self::statuses();
        $clients = [];
        $groups = [];
        try { $clients = Client::forSelect(); } catch (\Throwable $e) { $clients = []; }
        try { $groups = ClientGroup::all(); } catch (\Throwable $e) { $groups = []; }
        echo View::render('offers/form', [
            'title' => 'Oferta nouă',
            'row' => $row,
            'statuses' => $statuses,
            'clients' => $clients,
            'groups' => $groups,
        ]);
    }

    public static function create(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $check = Validator::required($_POST, [
            'name' => 'Denumire',
        ]);
        $errors = $check['errors'];
        $name = trim((string)($_POST['name'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'DRAFT'));
        $code = trim((string)($_POST['code'] ?? ''));
        $clientId = Validator::int(trim((string)($_POST['client_id'] ?? '')), 1);
        $groupId = Validator::int(trim((string)($_POST['client_group_id'] ?? '')), 1);
        if ($clientId !== null) $groupId = null;
        if ($code === '') {
            $code = Offer::nextAutoCode(10000);
        }
        if (!$status) $status = 'DRAFT';
        if ($errors) {
            $row = $_POST;
            $row['code'] = $code;
            $statuses = self::statuses();
            try { $clients = Client::forSelect(); } catch (\Throwable $e) { $clients = []; }
            try { $groups = ClientGroup::all(); } catch (\Throwable $e) { $groups = []; }
            echo View::render('offers/form', [
                'title' => 'Oferta nouă',
                'row' => $row,
                'statuses' => $statuses,
                'clients' => $clients,
                'groups' => $groups,
                'errors' => $errors,
            ]);
            return;
        }
        try {
            $id = Offer::create([
                'code' => $code,
                'name' => $name,
                'status' => $status,
                'category' => $_POST['category'] ?? null,
                'description' => $_POST['description'] ?? null,
                'due_date' => $_POST['due_date'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'technical_notes' => $_POST['technical_notes'] ?? null,
                'tags' => $_POST['tags'] ?? null,
                'client_id' => $clientId,
                'client_group_id' => $groupId,
                'created_by' => Auth::id(),
            ]);
            Audit::log('OFFER_CREATE', 'offers', $id, null, null, [
                'message' => 'A creat oferta: ' . $code . ' · ' . $name,
            ]);
            Session::flash('toast_success', 'Oferta a fost creată.');
            Response::redirect('/offers/' . $id);
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot crea oferta: ' . $e->getMessage());
            Response::redirect('/offers/create');
        }
    }

    public static function update(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $offer = Offer::find($offerId);
        if (!$offer) {
            Session::flash('toast_error', 'Oferta nu există.');
            Response::redirect('/offers');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            Session::flash('toast_error', 'Completează denumirea ofertei.');
            Response::redirect('/offers/' . $offerId);
        }
        $status = trim((string)($_POST['status'] ?? (string)($offer['status'] ?? 'DRAFT')));
        if ($status === '') $status = 'DRAFT';
        $clientId = Validator::int(trim((string)($_POST['client_id'] ?? '')), 1);
        $groupId = Validator::int(trim((string)($_POST['client_group_id'] ?? '')), 1);
        if ($clientId !== null) $groupId = null;
        try {
            Offer::update($offerId, [
                'code' => (string)($offer['code'] ?? ''),
                'name' => $name,
                'status' => $status,
                'category' => $_POST['category'] ?? null,
                'description' => $_POST['description'] ?? null,
                'due_date' => $_POST['due_date'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'technical_notes' => $_POST['technical_notes'] ?? null,
                'tags' => $_POST['tags'] ?? null,
                'client_id' => $clientId,
                'client_group_id' => $groupId,
            ]);
            Audit::log('OFFER_UPDATE', 'offers', $offerId, $offer, null, [
                'message' => 'A actualizat oferta: ' . (string)($offer['code'] ?? '') . ' · ' . $name,
            ]);
            Session::flash('toast_success', 'Oferta a fost actualizată.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot actualiza: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId);
    }

    public static function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $tab = isset($_GET['tab']) ? trim((string)($_GET['tab'] ?? '')) : '';
        if ($tab === '') $tab = 'general';

        $offer = Offer::find($id);
        if (!$offer) {
            Session::flash('toast_error', 'Oferta nu există.');
            Response::redirect('/offers');
        }

        $offerProducts = [];
        $hplByProduct = [];
        $accByProduct = [];
        $workByProduct = [];
        $productTotals = [];
        $totals = ['cost_total' => 0.0, 'sale_total' => 0.0];
        $workLogs = [];
        try { $offerProducts = OfferProduct::forOffer($id); } catch (\Throwable $e) { $offerProducts = []; }
        try { $workLogs = OfferWorkLog::forOffer($id); } catch (\Throwable $e) { $workLogs = []; }

        $workGrouped = [];
        foreach ($workLogs as $w) {
            $opId = isset($w['offer_product_id']) && $w['offer_product_id'] !== null ? (int)$w['offer_product_id'] : 0;
            if ($opId <= 0) continue;
            if (!isset($workGrouped[$opId])) $workGrouped[$opId] = [];
            $workGrouped[$opId][] = $w;
        }

        foreach ($offerProducts as $op) {
            $opId = (int)($op['id'] ?? 0);
            if ($opId <= 0) continue;
            $hplRows = [];
            $accRows = [];
            try { $hplRows = OfferProductHpl::forOfferProduct($opId); } catch (\Throwable $e) { $hplRows = []; }
            try { $accRows = OfferProductAccessory::forOfferProduct($opId); } catch (\Throwable $e) { $accRows = []; }
            $hplByProduct[$opId] = $hplRows;
            $accByProduct[$opId] = $accRows;
            $workByProduct[$opId] = $workGrouped[$opId] ?? [];

            $qty = isset($op['qty']) ? (float)$op['qty'] : 0.0;
            $salePrice = isset($op['product_sale_price']) && $op['product_sale_price'] !== null && $op['product_sale_price'] !== ''
                ? (float)$op['product_sale_price']
                : 0.0;
            $saleTotal = $salePrice * $qty;

            $hplCost = 0.0;
            foreach ($hplRows as $hr) {
                $bprice = isset($hr['board_sale_price']) && $hr['board_sale_price'] !== null && $hr['board_sale_price'] !== ''
                    ? (float)$hr['board_sale_price']
                    : 0.0;
                $qtyBoards = isset($hr['qty']) ? (float)$hr['qty'] : 0.0;
                $mode = strtoupper((string)($hr['consume_mode'] ?? 'FULL'));
                $coef = $mode === 'HALF' ? 0.5 : 1.0;
                $hplCost += $bprice * $qtyBoards * $coef;
            }

            $accCost = 0.0;
            foreach ($accRows as $ar) {
                $unitPrice = isset($ar['unit_price']) && $ar['unit_price'] !== null && $ar['unit_price'] !== ''
                    ? (float)$ar['unit_price']
                    : (isset($ar['item_unit_price']) && $ar['item_unit_price'] !== null && $ar['item_unit_price'] !== '' ? (float)$ar['item_unit_price'] : 0.0);
                $accCost += $unitPrice * (float)($ar['qty'] ?? 0.0);
            }

            $laborCost = 0.0;
            foreach ($workByProduct[$opId] as $wr) {
                $he = isset($wr['hours_estimated']) && $wr['hours_estimated'] !== null && $wr['hours_estimated'] !== ''
                    ? (float)$wr['hours_estimated']
                    : 0.0;
                $cph = isset($wr['cost_per_hour']) && $wr['cost_per_hour'] !== null && $wr['cost_per_hour'] !== ''
                    ? (float)$wr['cost_per_hour']
                    : 0.0;
                $laborCost += $he * $cph;
            }

            $totalCost = $hplCost + $accCost + $laborCost;
            $productTotals[$opId] = [
                'hpl_cost' => $hplCost,
                'acc_cost' => $accCost,
                'labor_cost' => $laborCost,
                'cost_total' => $totalCost,
                'sale_total' => $saleTotal,
            ];
            $totals['cost_total'] += $totalCost;
            $totals['sale_total'] += $saleTotal;
        }

        $clients = [];
        $groups = [];
        $productsAll = [];
        try { $clients = Client::forSelect(); } catch (\Throwable $e) { $clients = []; }
        try { $groups = ClientGroup::all(); } catch (\Throwable $e) { $groups = []; }
        try { $productsAll = Product::all(null, 2000); } catch (\Throwable $e) { $productsAll = []; }
        $statuses = self::statuses();
        echo View::render('offers/show', [
            'title' => 'Oferta ' . (string)($offer['code'] ?? ''),
            'offer' => $offer,
            'offerProducts' => $offerProducts,
            'hplByProduct' => $hplByProduct,
            'accByProduct' => $accByProduct,
            'workByProduct' => $workByProduct,
            'productTotals' => $productTotals,
            'totals' => $totals,
            'tab' => $tab,
            'statuses' => $statuses,
            'clients' => $clients,
            'groups' => $groups,
            'productsAll' => $productsAll,
        ]);
    }

    public static function addExistingProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $offer = Offer::find($offerId);
        if (!$offer) {
            Session::flash('toast_error', 'Oferta nu există.');
            Response::redirect('/offers');
        }

        $productId = Validator::int(trim((string)($_POST['product_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        $unit = trim((string)($_POST['unit'] ?? 'buc'));
        if ($productId === null || $qty <= 0) {
            Session::flash('toast_error', 'Produs invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $prod = Product::find((int)$productId);
        if (!$prod) {
            Session::flash('toast_error', 'Produs inexistent.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }

        try {
            OfferProduct::addToOffer([
                'offer_id' => $offerId,
                'product_id' => (int)$productId,
                'qty' => $qty,
                'unit' => $unit !== '' ? $unit : 'buc',
            ]);
            Audit::log('OFFER_PRODUCT_ATTACH', 'offer_products', 0, null, null, [
                'message' => 'A atașat produs la ofertă: ' . (string)($prod['name'] ?? ''),
                'offer_id' => $offerId,
                'product_id' => (int)$productId,
                'qty' => $qty,
                'unit' => $unit,
            ]);
            Session::flash('toast_success', 'Produs adăugat în ofertă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot adăuga produsul: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function createProductInOffer(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $offer = Offer::find($offerId);
        if (!$offer) {
            Session::flash('toast_error', 'Oferta nu există.');
            Response::redirect('/offers');
        }

        $check = Validator::required($_POST, [
            'name' => 'Denumire',
            'qty' => 'Cantitate',
        ]);
        $errors = $check['errors'];
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $salePrice = $salePriceRaw !== '' ? (Validator::dec($salePriceRaw) ?? null) : null;
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? 1.0;
        if ($qty <= 0) $errors['qty'] = 'Cantitate invalidă.';
        if ($salePriceRaw !== '' && ($salePrice === null || $salePrice < 0)) {
            $errors['sale_price'] = 'Preț vânzare invalid.';
        }
        if ($errors) {
            Session::flash('toast_error', 'Completează corect produsul.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }

        try {
            $pid = Product::create([
                'code' => $code !== '' ? $code : null,
                'name' => $name,
                'sale_price' => ($salePrice !== null && $salePrice >= 0) ? round((float)$salePrice, 2) : null,
                'width_mm' => null,
                'height_mm' => null,
                'notes' => $desc !== '' ? $desc : null,
                'cnc_settings_json' => null,
            ]);
            Audit::log('PRODUCT_CREATE', 'products', $pid, null, null, [
                'message' => 'A creat produs: ' . $name,
                'offer_id' => $offerId,
            ]);

            OfferProduct::addToOffer([
                'offer_id' => $offerId,
                'product_id' => $pid,
                'qty' => $qty,
                'unit' => 'buc',
            ]);

            Audit::log('OFFER_PRODUCT_ATTACH', 'offer_products', 0, null, null, [
                'message' => 'A atașat produs nou la ofertă: ' . $name,
                'offer_id' => $offerId,
                'product_id' => $pid,
                'qty' => $qty,
                'unit' => 'buc',
            ]);
            Session::flash('toast_success', 'Produs creat și adăugat în ofertă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot crea produsul: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function updateOfferProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $opId = (int)($params['opId'] ?? 0);
        if ($offerId <= 0 || $opId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/offers');
        }
        $offer = Offer::find($offerId);
        $op = OfferProduct::find($opId);
        if (!$offer || !$op || (int)($op['offer_id'] ?? 0) !== $offerId) {
            Session::flash('toast_error', 'Produs ofertă invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? ''))) ?? null;
        $unit = trim((string)($_POST['unit'] ?? (string)($op['unit'] ?? 'buc')));
        $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
        $salePrice = $salePriceRaw !== '' ? (Validator::dec($salePriceRaw) ?? null) : null;
        if ($qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Cantitate invalidă.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        if ($salePriceRaw !== '' && ($salePrice === null || $salePrice < 0)) {
            Session::flash('toast_error', 'Preț cu discount invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        try {
            OfferProduct::updateFields($opId, [
                'qty' => $qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'notes' => $desc !== '' ? $desc : null,
            ]);
            $prodId = (int)($op['product_id'] ?? 0);
            if ($prodId > 0) {
                $prod = Product::find($prodId);
                if ($prod) {
                    Product::updateFields($prodId, [
                        'code' => $prod['code'] ?? null,
                        'name' => $prod['name'] ?? '',
                        'sale_price' => ($salePriceRaw !== '' && $salePrice !== null) ? round((float)$salePrice, 2) : null,
                        'notes' => $prod['notes'] ?? null,
                    ]);
                }
            }
            Session::flash('toast_success', 'Produs actualizat.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot actualiza: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function removeOfferProduct(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $opId = (int)($params['opId'] ?? 0);
        if ($offerId <= 0 || $opId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/offers');
        }
        $offer = Offer::find($offerId);
        $op = OfferProduct::find($opId);
        if (!$offer || !$op || (int)($op['offer_id'] ?? 0) !== $offerId) {
            Session::flash('toast_error', 'Produs ofertă invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        try {
            OfferProduct::delete($opId);
            Session::flash('toast_success', 'Produs eliminat din ofertă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function addOfferProductHpl(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $opId = (int)($params['opId'] ?? 0);
        $offer = Offer::find($offerId);
        $op = OfferProduct::find($opId);
        if (!$offer || !$op || (int)($op['offer_id'] ?? 0) !== $offerId) {
            Session::flash('toast_error', 'Produs ofertă invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $boardId = Validator::int(trim((string)($_POST['board_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? null;
        $mode = strtoupper(trim((string)($_POST['consume_mode'] ?? 'FULL')));
        if ($mode !== 'FULL' && $mode !== 'HALF') $mode = 'FULL';
        if ($boardId === null || $qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Consum HPL invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $board = HplBoard::find((int)$boardId);
        if (!$board) {
            Session::flash('toast_error', 'Placă HPL inexistentă.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        try {
            OfferProductHpl::create([
                'offer_id' => $offerId,
                'offer_product_id' => $opId,
                'board_id' => (int)$boardId,
                'consume_mode' => $mode,
                'qty' => (float)$qty,
                'created_by' => Auth::id(),
            ]);
            Session::flash('toast_success', 'Consum HPL adăugat în ofertă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot salva: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function deleteOfferProductHpl(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $opId = (int)($params['opId'] ?? 0);
        $hplId = (int)($params['hplId'] ?? 0);
        if ($offerId <= 0 || $opId <= 0 || $hplId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/offers');
        }
        try {
            OfferProductHpl::delete($hplId);
            Session::flash('toast_success', 'Consum HPL șters.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function addOfferProductAccessory(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $opId = (int)($params['opId'] ?? 0);
        $offer = Offer::find($offerId);
        $op = OfferProduct::find($opId);
        if (!$offer || !$op || (int)($op['offer_id'] ?? 0) !== $offerId) {
            Session::flash('toast_error', 'Produs ofertă invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $itemId = Validator::int(trim((string)($_POST['item_id'] ?? '')), 1);
        $qty = Validator::dec(trim((string)($_POST['qty'] ?? '1'))) ?? null;
        $includeInDeviz = isset($_POST['include_in_deviz']) ? 1 : 0;
        if ($itemId === null || $qty === null || $qty <= 0) {
            Session::flash('toast_error', 'Consum invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $item = MagazieItem::find((int)$itemId);
        if (!$item) {
            Session::flash('toast_error', 'Accesoriu inexistent.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $unit = trim((string)($item['unit'] ?? 'buc'));
        $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== null && $item['unit_price'] !== ''
            ? (float)$item['unit_price']
            : null;
        try {
            OfferProductAccessory::create([
                'offer_id' => $offerId,
                'offer_product_id' => $opId,
                'item_id' => (int)$itemId,
                'qty' => (float)$qty,
                'unit' => $unit !== '' ? $unit : 'buc',
                'unit_price' => $unitPrice,
                'include_in_deviz' => $includeInDeviz,
                'created_by' => Auth::id(),
            ]);
            Session::flash('toast_success', 'Accesoriu adăugat în ofertă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot salva: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function deleteOfferProductAccessory(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $opId = (int)($params['opId'] ?? 0);
        $accId = (int)($params['accId'] ?? 0);
        if ($offerId <= 0 || $opId <= 0 || $accId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/offers');
        }
        try {
            OfferProductAccessory::delete($accId);
            Session::flash('toast_success', 'Accesoriu șters.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function addOfferWorkLog(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $opId = (int)($params['opId'] ?? 0);
        $offer = Offer::find($offerId);
        $op = OfferProduct::find($opId);
        if (!$offer || !$op || (int)($op['offer_id'] ?? 0) !== $offerId) {
            Session::flash('toast_error', 'Produs ofertă invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $type = trim((string)($_POST['work_type'] ?? ''));
        if (!in_array($type, ['CNC','ATELIER'], true)) {
            Session::flash('toast_error', 'Tip invalid.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }
        $he = Validator::dec(trim((string)($_POST['hours_estimated'] ?? '')));
        if ($he !== null && $he <= 0) $he = null;
        if ($he === null) {
            Session::flash('toast_error', 'Completează orele estimate (valoare > 0).');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }

        $cph = null;
        try {
            $cph = ($type === 'CNC')
                ? AppSetting::getFloat(AppSetting::KEY_COST_CNC_PER_HOUR)
                : AppSetting::getFloat(AppSetting::KEY_COST_LABOR_PER_HOUR);
        } catch (\Throwable $e) {
            $cph = null;
        }
        if ($cph === null || $cph < 0) {
            Session::flash('toast_error', 'Costurile nu sunt setate. (Setări costuri)');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }

        try {
            OfferWorkLog::create([
                'offer_id' => $offerId,
                'offer_product_id' => $opId,
                'work_type' => $type,
                'hours_estimated' => $he,
                'cost_per_hour' => $cph,
                'note' => $_POST['note'] ?? null,
                'created_by' => Auth::id(),
            ]);
            Session::flash('toast_success', 'Manoperă adăugată.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot salva: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function deleteOfferWorkLog(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $opId = (int)($params['opId'] ?? 0);
        $workId = (int)($params['workId'] ?? 0);
        if ($offerId <= 0 || $opId <= 0 || $workId <= 0) {
            Session::flash('toast_error', 'Parametri invalizi.');
            Response::redirect('/offers');
        }
        try {
            OfferWorkLog::delete($workId);
            Session::flash('toast_success', 'Manoperă ștearsă.');
        } catch (\Throwable $e) {
            Session::flash('toast_error', 'Nu pot șterge: ' . $e->getMessage());
        }
        Response::redirect('/offers/' . $offerId . '?tab=products');
    }

    public static function bonGeneral(array $params): void
    {
        $offerId = (int)($params['id'] ?? 0);
        $offer = Offer::find($offerId);
        if (!$offer) {
            Session::flash('toast_error', 'Oferta nu există.');
            Response::redirect('/offers');
        }
        $offerProducts = OfferProduct::forOffer($offerId);
        $workLogs = OfferWorkLog::forOffer($offerId);

        $workGrouped = [];
        foreach ($workLogs as $w) {
            $opId = isset($w['offer_product_id']) && $w['offer_product_id'] !== null ? (int)$w['offer_product_id'] : 0;
            if ($opId <= 0) continue;
            if (!isset($workGrouped[$opId])) $workGrouped[$opId] = [];
            $workGrouped[$opId][] = $w;
        }

        $rows = [];
        $totalCost = 0.0;
        $totalSale = 0.0;
        foreach ($offerProducts as $op) {
            $opId = (int)($op['id'] ?? 0);
            if ($opId <= 0) continue;
            $qty = isset($op['qty']) ? (float)$op['qty'] : 0.0;
            $salePrice = isset($op['product_sale_price']) && $op['product_sale_price'] !== null && $op['product_sale_price'] !== ''
                ? (float)$op['product_sale_price']
                : 0.0;
            $saleTotal = $salePrice * $qty;
            $totalSale += $saleTotal;

            $hplRows = OfferProductHpl::forOfferProduct($opId);
            $accRows = OfferProductAccessory::forOfferProduct($opId);
            $workRows = $workGrouped[$opId] ?? [];

            $hplCost = 0.0;
            foreach ($hplRows as $hr) {
                $bprice = isset($hr['board_sale_price']) && $hr['board_sale_price'] !== null && $hr['board_sale_price'] !== ''
                    ? (float)$hr['board_sale_price']
                    : 0.0;
                $qtyBoards = isset($hr['qty']) ? (float)$hr['qty'] : 0.0;
                $mode = strtoupper((string)($hr['consume_mode'] ?? 'FULL'));
                $coef = $mode === 'HALF' ? 0.5 : 1.0;
                $hplCost += $bprice * $qtyBoards * $coef;
            }

            $accCost = 0.0;
            foreach ($accRows as $ar) {
                $unitPrice = isset($ar['unit_price']) && $ar['unit_price'] !== null && $ar['unit_price'] !== ''
                    ? (float)$ar['unit_price']
                    : (isset($ar['item_unit_price']) && $ar['item_unit_price'] !== null && $ar['item_unit_price'] !== '' ? (float)$ar['item_unit_price'] : 0.0);
                $accCost += $unitPrice * (float)($ar['qty'] ?? 0.0);
            }

            $laborCost = 0.0;
            foreach ($workRows as $wr) {
                $he = isset($wr['hours_estimated']) && $wr['hours_estimated'] !== null && $wr['hours_estimated'] !== ''
                    ? (float)$wr['hours_estimated']
                    : 0.0;
                $cph = isset($wr['cost_per_hour']) && $wr['cost_per_hour'] !== null && $wr['cost_per_hour'] !== ''
                    ? (float)$wr['cost_per_hour']
                    : 0.0;
                $laborCost += $he * $cph;
            }

            $costTotal = $hplCost + $accCost + $laborCost;
            $totalCost += $costTotal;
            $rows[] = [
                'product_name' => (string)($op['product_name'] ?? ''),
                'product_desc' => (string)($op['notes'] ?? '') !== '' ? (string)($op['notes'] ?? '') : (string)($op['product_notes'] ?? ''),
                'qty' => $qty,
                'unit' => (string)($op['unit'] ?? 'buc'),
                'sale_price' => $salePrice,
                'sale_total' => $saleTotal,
                'cost_total' => $costTotal,
            ];
        }

        $company = self::companySettingsForDocs();
        $title = 'Bon ofertă';
        $html = View::render('offers/bon_general', [
            'offer' => $offer,
            'rows' => $rows,
            'totalCost' => $totalCost,
            'totalSale' => $totalSale,
            'company' => $company,
            'fmtMoney' => fn($v) => self::fmtMoney($v),
            'fmtQty' => fn($v) => self::fmtQty($v),
        ]);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public static function convertToProject(array $params): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $offerId = (int)($params['id'] ?? 0);
        $offer = Offer::find($offerId);
        if (!$offer) {
            Session::flash('toast_error', 'Oferta nu există.');
            Response::redirect('/offers');
        }
        $existingProjectId = (int)($offer['converted_project_id'] ?? 0);
        if ($existingProjectId > 0) {
            Session::flash('toast_success', 'Oferta este deja convertită.');
            Response::redirect('/projects/' . $existingProjectId);
        }

        $offerProducts = OfferProduct::forOffer($offerId);
        if (!$offerProducts) {
            Session::flash('toast_error', 'Oferta nu are produse.');
            Response::redirect('/offers/' . $offerId . '?tab=products');
        }

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $code = Project::nextAutoCode(1000, true);
            $projectId = Project::create([
                'code' => $code,
                'name' => (string)($offer['name'] ?? ''),
                'description' => $offer['description'] ?? null,
                'category' => $offer['category'] ?? null,
                'status' => 'DRAFT',
                'priority' => 0,
                'start_date' => null,
                'due_date' => $offer['due_date'] ?? null,
                'notes' => $offer['notes'] ?? null,
                'technical_notes' => $offer['technical_notes'] ?? null,
                'tags' => $offer['tags'] ?? null,
                'client_id' => $offer['client_id'] ?? null,
                'client_group_id' => $offer['client_group_id'] ?? null,
                'allocation_mode' => 'by_area',
                'allocations_locked' => 0,
                'created_by' => Auth::id(),
            ]);
            Project::updateSourceOffer($projectId, $offerId);

            $ppMap = [];
            foreach ($offerProducts as $op) {
                $productId = (int)($op['product_id'] ?? 0);
                if ($productId <= 0) throw new \RuntimeException('Produs invalid în ofertă.');
                $qty = (float)($op['qty'] ?? 1);
                if ($qty <= 0) $qty = 1.0;
                $ppId = ProjectProduct::addToProject([
                    'project_id' => $projectId,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'unit' => (string)($op['unit'] ?? 'buc'),
                    'm2_per_unit' => 0.0,
                    'production_status' => 'CREAT',
                    'delivered_qty' => 0,
                    'notes' => null,
                ]);
                $ppMap[(int)$op['id']] = $ppId;
            }

            foreach ($offerProducts as $op) {
                $opId = (int)($op['id'] ?? 0);
                $ppId = $ppMap[$opId] ?? 0;
                if ($opId <= 0 || $ppId <= 0) continue;

                $accRows = OfferProductAccessory::forOfferProduct($opId);
                foreach ($accRows as $ar) {
                    $itemId = (int)($ar['item_id'] ?? 0);
                    $qty = (float)($ar['qty'] ?? 0.0);
                    if ($itemId <= 0 || $qty <= 0) continue;
                    $unit = (string)($ar['unit'] ?? 'buc');
                    $include = (int)($ar['include_in_deviz'] ?? 1);
                    ProjectMagazieConsumption::create([
                        'project_id' => $projectId,
                        'project_product_id' => $ppId,
                        'item_id' => $itemId,
                        'qty' => $qty,
                        'unit' => $unit,
                        'mode' => 'RESERVED',
                        'include_in_deviz' => $include,
                        'note' => null,
                        'created_by' => Auth::id(),
                    ]);
                }

                $workRows = OfferWorkLog::forOfferProduct($opId);
                foreach ($workRows as $wr) {
                    $type = (string)($wr['work_type'] ?? '');
                    if (!in_array($type, ['CNC','ATELIER'], true)) continue;
                    $he = isset($wr['hours_estimated']) && $wr['hours_estimated'] !== null && $wr['hours_estimated'] !== ''
                        ? (float)$wr['hours_estimated']
                        : null;
                    $cph = isset($wr['cost_per_hour']) && $wr['cost_per_hour'] !== null && $wr['cost_per_hour'] !== ''
                        ? (float)$wr['cost_per_hour']
                        : null;
                    if ($he === null || $he <= 0 || $cph === null || $cph < 0) continue;
                    ProjectWorkLog::create([
                        'project_id' => $projectId,
                        'project_product_id' => $ppId,
                        'work_type' => $type,
                        'hours_estimated' => $he,
                        'hours_actual' => null,
                        'cost_per_hour' => $cph,
                        'note' => $wr['note'] ?? null,
                        'created_by' => Auth::id(),
                    ]);
                }

                $hplRows = OfferProductHpl::forOfferProduct($opId);
                foreach ($hplRows as $hr) {
                    $boardId = (int)($hr['board_id'] ?? 0);
                    $qtyBoards = (float)($hr['qty'] ?? 0.0);
                    if ($boardId <= 0 || $qtyBoards <= 0) continue;
                    $mode = strtoupper((string)($hr['consume_mode'] ?? 'FULL'));
                    if ($mode !== 'FULL' && $mode !== 'HALF') $mode = 'FULL';
                    $qtyInt = (int)ceil($qtyBoards);
                    if ($qtyInt <= 0) continue;
                    $prodLabel = trim((string)($op['product_name'] ?? ''));
                    if ($prodLabel === '') $prodLabel = 'Produs #' . $ppId;
                    $projLabel = (string)$code . ' · ' . (string)($offer['name'] ?? '');
                    $note = 'Proiect: ' . $projLabel . ' · Produs: ' . $prodLabel;
                    for ($i = 0; $i < $qtyInt; $i++) {
                        $pieceId = self::reserveFullBoard($pdo, $boardId, $projectId, $note);
                        ProjectProductHplConsumption::create([
                            'project_id' => $projectId,
                            'project_product_id' => $ppId,
                            'board_id' => $boardId,
                            'stock_piece_id' => $pieceId,
                            'source' => 'PROJECT',
                            'consume_mode' => $mode,
                            'status' => 'RESERVED',
                            'created_by' => Auth::id(),
                        ]);
                    }
                }
            }

            Offer::markConverted($offerId, $projectId);
            $pdo->commit();
            Audit::log('OFFER_CONVERT', 'offers', $offerId, null, null, [
                'message' => 'Oferta convertită în proiect: ' . $code,
                'offer_id' => $offerId,
                'project_id' => $projectId,
            ]);
            Session::flash('toast_success', 'Oferta a fost convertită în proiect.');
            Response::redirect('/projects/' . $projectId);
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            Session::flash('toast_error', 'Nu pot converti oferta: ' . $e->getMessage());
            Response::redirect('/offers/' . $offerId);
        }
    }

    private static function reserveFullBoard(PDO $pdo, int $boardId, int $projectId, string $note): int
    {
        $st = $pdo->prepare("
            SELECT *
            FROM hpl_stock_pieces
            WHERE board_id = ?
              AND piece_type = 'FULL'
              AND status = 'AVAILABLE'
              AND qty > 0
            ORDER BY created_at ASC, id ASC
            LIMIT 1
            FOR UPDATE
        ");
        $st->execute([$boardId]);
        $piece = $st->fetch();
        if (!$piece) {
            throw new \RuntimeException('Stoc HPL insuficient pentru placa selectată.');
        }

        $pieceId = (int)($piece['id'] ?? 0);
        $qty = (int)($piece['qty'] ?? 0);
        $w = (int)($piece['width_mm'] ?? 0);
        $h = (int)($piece['height_mm'] ?? 0);
        $loc = (string)($piece['location'] ?? '');
        $isAcc = array_key_exists('is_accounting', $piece) ? (int)($piece['is_accounting'] ?? 1) : 1;
        $notes = (string)($piece['notes'] ?? '');

        if ($pieceId <= 0 || $qty <= 0) {
            throw new \RuntimeException('Stoc HPL invalid.');
        }

        if ($qty > 1) {
            HplStockPiece::updateQty($pieceId, $qty - 1);
            $newId = HplStockPiece::create([
                'board_id' => $boardId,
                'project_id' => $projectId,
                'is_accounting' => $isAcc,
                'piece_type' => 'FULL',
                'status' => 'RESERVED',
                'width_mm' => $w,
                'height_mm' => $h,
                'qty' => 1,
                'location' => $loc,
                'notes' => $notes !== '' ? $notes : null,
            ]);
            if ($note !== '') {
                HplStockPiece::appendNote($newId, $note);
            }
            return $newId;
        }

        HplStockPiece::updateFields($pieceId, [
            'status' => 'RESERVED',
            'project_id' => $projectId,
        ]);
        if ($note !== '') {
            HplStockPiece::appendNote($pieceId, $note);
        }
        return $pieceId;
    }
}

