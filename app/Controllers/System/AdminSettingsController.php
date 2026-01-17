<?php
declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class AdminSettingsController
{
    private static function requireAdmin(): void
    {
        $u = Auth::user();
        $role = $u ? (string)($u['role'] ?? '') : '';
        if ($role !== Auth::ROLE_ADMIN) {
            Session::flash('toast_error', 'Acces interzis.');
            Response::redirect('/');
        }
    }

    private static function backupDir(): string
    {
        $dir = __DIR__ . '/../../../storage/db_backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function listSnapshots(string $dir): array
    {
        $files = glob(rtrim($dir, '/') . '/*.sql') ?: [];
        usort($files, fn(string $a, string $b) => filemtime($b) <=> filemtime($a));
        $out = [];
        foreach ($files as $f) {
            $out[] = [
                'name' => basename($f),
                'path' => $f,
                'mtime' => filemtime($f) ?: 0,
                'size' => filesize($f) ?: 0,
            ];
        }
        return $out;
    }

    private static function canExec(): bool
    {
        return function_exists('exec');
    }

    private static function binaryExists(string $bin): bool
    {
        $out = [];
        $code = 1;
        @exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $code);
        return $code === 0;
    }

    private static function runCmd(string $cmd, ?array &$out = null): int
    {
        $out = [];
        $code = 1;
        @exec($cmd . ' 2>&1', $out, $code);
        return $code;
    }

    private static function dbEnv(): array
    {
        return [
            'host' => (string)Env::get('DB_HOST', '127.0.0.1'),
            'port' => (string)Env::get('DB_PORT', '3306'),
            'name' => (string)Env::get('DB_NAME', ''),
            'user' => (string)Env::get('DB_USER', ''),
            'pass' => (string)Env::get('DB_PASS', ''),
        ];
    }

    private static function dumpDatabase(string $path, string &$error): bool
    {
        $error = '';
        if (!self::canExec()) {
            $error = 'Funcțiile exec sunt dezactivate pe hosting.';
            return false;
        }
        if (!self::binaryExists('mysqldump')) {
            $error = 'mysqldump nu este disponibil pe server.';
            return false;
        }
        $db = self::dbEnv();
        if ($db['name'] === '' || $db['user'] === '') {
            $error = 'DB_NAME/DB_USER lipsesc în .env.';
            return false;
        }

        $cmd = sprintf(
            'MYSQL_PWD=%s mysqldump --single-transaction --skip-lock-tables --add-drop-table --routines --triggers --events --default-character-set=utf8mb4 -h %s -P %s -u %s %s > %s',
            escapeshellarg($db['pass']),
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['user']),
            escapeshellarg($db['name']),
            escapeshellarg($path)
        );
        $out = [];
        $code = self::runCmd($cmd, $out);
        if ($code !== 0) {
            $error = 'Eroare dump: ' . trim(implode("\n", $out));
            return false;
        }
        return true;
    }

    private static function restoreDatabase(string $path, string &$error): bool
    {
        $error = '';
        if (!self::canExec()) {
            $error = 'Funcțiile exec sunt dezactivate pe hosting.';
            return false;
        }
        if (!self::binaryExists('mysql')) {
            $error = 'mysql client nu este disponibil pe server.';
            return false;
        }
        $db = self::dbEnv();
        if ($db['name'] === '' || $db['user'] === '') {
            $error = 'DB_NAME/DB_USER lipsesc în .env.';
            return false;
        }

        $cmd = sprintf(
            'MYSQL_PWD=%s mysql --default-character-set=utf8mb4 -h %s -P %s -u %s %s < %s',
            escapeshellarg($db['pass']),
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['user']),
            escapeshellarg($db['name']),
            escapeshellarg($path)
        );
        $out = [];
        $code = self::runCmd($cmd, $out);
        if ($code !== 0) {
            $error = 'Eroare restore: ' . trim(implode("\n", $out));
            return false;
        }
        return true;
    }

    public static function index(): void
    {
        self::requireAdmin();
        $dir = self::backupDir();
        $snapshots = self::listSnapshots($dir);
        $canExec = self::canExec();
        $hasDump = $canExec && self::binaryExists('mysqldump');
        $hasMysql = $canExec && self::binaryExists('mysql');
        echo View::render('system/admin_settings', [
            'title' => 'Setări admin',
            'snapshots' => $snapshots,
            'canExec' => $canExec,
            'hasDump' => $hasDump,
            'hasMysql' => $hasMysql,
        ]);
    }

    public static function createSnapshot(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        self::requireAdmin();

        $dir = self::backupDir();
        $name = 'db-' . date('Ymd-His') . '.sql';
        $path = $dir . '/' . $name;
        $err = '';
        if (self::dumpDatabase($path, $err)) {
            Session::flash('toast_success', 'Snapshot creat: ' . $name);
        } else {
            Session::flash('toast_error', 'Nu pot crea snapshot: ' . $err);
        }
        Response::redirect('/system/admin-settings');
    }

    public static function restoreSnapshot(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        self::requireAdmin();

        $name = basename((string)($_POST['snapshot'] ?? ''));
        if ($name === '') {
            Session::flash('toast_error', 'Snapshot invalid.');
            Response::redirect('/system/admin-settings');
        }
        $dir = self::backupDir();
        $path = realpath($dir . '/' . $name);
        $realDir = realpath($dir);
        if (!$path || !$realDir || !str_starts_with($path, $realDir)) {
            Session::flash('toast_error', 'Snapshot inexistent.');
            Response::redirect('/system/admin-settings');
        }
        $err = '';
        if (self::restoreDatabase($path, $err)) {
            Session::flash('toast_success', 'Snapshot restaurat: ' . $name);
        } else {
            Session::flash('toast_error', 'Nu pot restaura snapshot: ' . $err);
        }
        Response::redirect('/system/admin-settings');
    }
}
