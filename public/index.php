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
use App\Core\View;

require __DIR__ . '/../vendor_stub.php';

Env::load(__DIR__ . '/../.env');
Session::start();

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

    if (!Auth::attempt($email, $password)) {
        Session::flash('toast_error', 'Date de autentificare incorecte.');
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

// API placeholder
$router->get('/api/health', function () {
    Response::json(['ok' => true, 'env' => Env::get('APP_ENV', 'local')]);
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

