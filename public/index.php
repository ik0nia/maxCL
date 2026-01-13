<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\DB;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\SqlInstaller;
use App\Core\Url;
use App\Core\View;
use App\Controllers\Catalog\FinishesController;
use App\Controllers\Catalog\MaterialsController;
use App\Controllers\Catalog\VariantsController;
use App\Controllers\StockController;
use App\Controllers\Hpl\InlineTexturesController;
use App\Controllers\DashboardController;

require __DIR__ . '/../vendor_stub.php';

Env::load(__DIR__ . '/../.env');
Session::start();

$basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
if ($basePath === '.' || $basePath === '/' ) $basePath = '';
Url::setBasePath($basePath);

$router = new Router();

// ---- Public routes
$router->get('/login', function () {
    echo View::render('auth/login', ['title' => 'Autentificare']);
});

$router->post('/login', function () {
    Csrf::verify($_POST['_csrf'] ?? null);
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        Session::flash('toast_error', 'Completează email și parolă.');
        Response::redirect('/login');
    }

    try {
        if (!Auth::attempt($email, $password)) {
            Session::flash('toast_error', 'Date de autentificare incorecte.');
            Response::redirect('/login');
        }
    } catch (\Throwable $e) {
        // De obicei: DB neconfigurat / credențiale greșite în .env
        Session::flash('toast_error', 'Eroare la conectarea bazei de date. Verifică fișierul .env (DB_HOST/DB_NAME/DB_USER/DB_PASS).');
        Response::redirect('/login');
    }

    Session::flash('toast_success', 'Autentificare reușită.');
    Response::redirect('/');
});

$router->post('/logout', function () {
    Csrf::verify($_POST['_csrf'] ?? null);
    Auth::logout();
    Session::flash('toast_success', 'Te-ai deconectat.');
    Response::redirect('/login');
}, [Auth::requireLogin()]);

// ---- Protected routes (MVP placeholders)
$router->get('/', fn() => DashboardController::index(), [Auth::requireLogin()]);

$router->get('/setup', function () {
    echo View::render('setup/index', ['title' => 'Instalare / Setup']);
});

// Rulează installerul (schema + seed admin)
$router->post('/setup/run', function () {
    Csrf::verify($_POST['_csrf'] ?? null);
    try {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        $res = SqlInstaller::runFile($pdo, __DIR__ . '/../database/schema.sql');

        // Seed admin (idempotent)
        $email = 'admin@local';
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $exists = $stmt->fetch();
        if (!$exists) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (email, name, role, password_hash, is_active) VALUES (?,?,?,?,1)')
                ->execute([$email, 'Administrator', Auth::ROLE_ADMIN, $hash]);
        }

        $pdo->commit();
        Session::flash('toast_success', 'Instalare finalizată. Poți face login cu admin@local / admin123 (schimbă parola!).');
    } catch (\Throwable $e) {
        try { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
        Session::flash('toast_error', 'Eroare la instalare: ' . $e->getMessage());
    }
    Response::redirect('/setup');
});

// ---- Uploads (servite din storage/, doar pentru useri autentificați)
$router->get('/uploads/finishes/{name}', function (array $params) {
    $name = (string)($params['name'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $name)) {
        http_response_code(404);
        exit;
    }
    $fs = __DIR__ . '/../storage/uploads/finishes/' . $name;
    if (!is_file($fs)) {
        http_response_code(404);
        exit;
    }
    $ext = strtolower(pathinfo($fs, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=86400');
    readfile($fs);
    exit;
}, [Auth::requireLogin()]);

// ---- Catalog (Admin, Gestionar)
$catalogMW = [Auth::requireRole([Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR])];

// Plăci HPL: Tip culoare (folosește tabela finishes, dar fără texturi)
$router->get('/hpl/tip-culoare', fn() => FinishesController::index(), $catalogMW);
$router->get('/hpl/tip-culoare/create', fn() => FinishesController::createForm(), $catalogMW);
$router->post('/hpl/tip-culoare/create', fn() => FinishesController::create(), $catalogMW);
$router->get('/hpl/tip-culoare/{id}/edit', fn($p) => FinishesController::editForm($p), $catalogMW);
$router->post('/hpl/tip-culoare/{id}/edit', fn($p) => FinishesController::update($p), $catalogMW);
$router->post('/hpl/tip-culoare/{id}/delete', fn($p) => FinishesController::delete($p), $catalogMW);

// Compat: vechile rute trimit la noile rute
$router->get('/catalog/finishes', fn() => Response::redirect('/hpl/tip-culoare'), $catalogMW);
$router->get('/catalog/finishes/create', fn() => Response::redirect('/hpl/tip-culoare/create'), $catalogMW);
$router->get('/catalog/finishes/{id}/edit', fn($p) => Response::redirect('/hpl/tip-culoare/' . (int)$p['id'] . '/edit'), $catalogMW);

// Plăci HPL: Texturi
// (Pagina separată Texturi a fost eliminată; texturile se gestionează din Tip culoare)
$router->post('/hpl/tip-culoare/texturi/create', fn() => InlineTexturesController::create(), $catalogMW);
$router->post('/hpl/tip-culoare/texturi/{id}/edit', fn($p) => InlineTexturesController::update($p), $catalogMW);
$router->post('/hpl/tip-culoare/texturi/{id}/delete', fn($p) => InlineTexturesController::delete($p), $catalogMW);

// Compat: vechiul link "Texturi"
$router->get('/hpl/texturi', fn() => Response::redirect('/hpl/tip-culoare#texturi'), $catalogMW);

// (Materiale + Variante) au fost înlocuite de modulul Stoc (plăci + piese)
$router->get('/catalog/materials', fn() => Response::redirect('/stock'), $catalogMW);
$router->get('/catalog/variants', fn() => Response::redirect('/stock'), $catalogMW);

// ---- Rute cu middleware pe roluri (placeholder până implementăm modulele)
$router->get('/users', fn() => print View::render('system/placeholder', ['title' => 'Utilizatori']), [
    Auth::requireRole([Auth::ROLE_ADMIN])
]);

$router->get('/audit', fn() => print View::render('system/placeholder', ['title' => 'Jurnal activitate']), [
    Auth::requireRole([Auth::ROLE_ADMIN])
]);

$router->get('/projects', fn() => print View::render('system/placeholder', ['title' => 'Proiecte']), [
    Auth::requireRole([Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR])
]);

// ---- Stoc (Admin/Gestionar/Operator). Operator = read-only (nu poate crea plăci/piese)
$stockReadMW = [Auth::requireRole([Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR])];
$stockWriteMW = [Auth::requireRole([Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR])];

$router->get('/stock', fn() => StockController::index(), $stockReadMW);
$router->get('/stock/boards/create', fn() => StockController::createBoardForm(), $stockWriteMW);
$router->post('/stock/boards/create', fn() => StockController::createBoard(), $stockWriteMW);
$router->get('/stock/boards/{id}', fn($p) => StockController::boardDetails($p), $stockReadMW);
$router->post('/stock/boards/{id}/pieces/add', fn($p) => StockController::addPiece($p), $stockWriteMW);
$router->get('/stock/boards/{id}/edit', fn($p) => StockController::editBoardForm($p), $stockWriteMW);
$router->post('/stock/boards/{id}/edit', fn($p) => StockController::updateBoard($p), $stockWriteMW);
$router->post('/stock/boards/{id}/delete', fn($p) => StockController::deleteBoard($p), $stockWriteMW);
$router->post('/stock/boards/{boardId}/pieces/{pieceId}/delete', fn($p) => StockController::deletePiece($p), [
    Auth::requireRole([Auth::ROLE_ADMIN])
]);

// API placeholder
$router->get('/api/health', function () {
    Response::json(['ok' => true, 'env' => Env::get('APP_ENV', 'local')]);
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', $basePath);

