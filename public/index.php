<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
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

// API placeholder
$router->get('/api/health', function () {
    Response::json(['ok' => true, 'env' => Env::get('APP_ENV', 'local')]);
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

