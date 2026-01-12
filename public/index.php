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
$router->get('/', function () {
    echo View::render('dashboard/index', ['title' => 'Panou']);
}, [Auth::requireLogin()]);

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

$router->get('/catalog/finishes', fn() => FinishesController::index(), $catalogMW);
$router->get('/catalog/finishes/create', fn() => FinishesController::createForm(), $catalogMW);
$router->post('/catalog/finishes/create', fn() => FinishesController::create(), $catalogMW);
$router->get('/catalog/finishes/{id}/edit', fn($p) => FinishesController::editForm($p), $catalogMW);
$router->post('/catalog/finishes/{id}/edit', fn($p) => FinishesController::update($p), $catalogMW);
$router->post('/catalog/finishes/{id}/delete', fn($p) => FinishesController::delete($p), $catalogMW);

$router->get('/catalog/materials', fn() => MaterialsController::index(), $catalogMW);
$router->get('/catalog/materials/create', fn() => MaterialsController::createForm(), $catalogMW);
$router->post('/catalog/materials/create', fn() => MaterialsController::create(), $catalogMW);
$router->get('/catalog/materials/{id}/edit', fn($p) => MaterialsController::editForm($p), $catalogMW);
$router->post('/catalog/materials/{id}/edit', fn($p) => MaterialsController::update($p), $catalogMW);
$router->post('/catalog/materials/{id}/delete', fn($p) => MaterialsController::delete($p), $catalogMW);

$router->get('/catalog/variants', fn() => VariantsController::index(), $catalogMW);
$router->get('/catalog/variants/create', fn() => VariantsController::createForm(), $catalogMW);
$router->post('/catalog/variants/create', fn() => VariantsController::create(), $catalogMW);
$router->get('/catalog/variants/{id}/edit', fn($p) => VariantsController::editForm($p), $catalogMW);
$router->post('/catalog/variants/{id}/edit', fn($p) => VariantsController::update($p), $catalogMW);
$router->post('/catalog/variants/{id}/delete', fn($p) => VariantsController::delete($p), $catalogMW);

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

$router->get('/stock', fn() => print View::render('system/placeholder', ['title' => 'Stoc']), [
    Auth::requireRole([Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR])
]);

// API placeholder
$router->get('/api/health', function () {
    Response::json(['ok' => true, 'env' => Env::get('APP_ENV', 'local')]);
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', $basePath);

