<?php
declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
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

    private static function canPhpSnapshot(): bool
    {
        $db = self::dbEnv();
        return $db['name'] !== '' && $db['user'] !== '';
    }

    private static function sqlQuote(string $value): string
    {
        $value = str_replace(
            ["\\", "\0", "\n", "\r", "\t", "\x1a", "'"],
            ["\\\\", "\\0", "\\n", "\\r", "\\t", "\\Z", "\\'"],
            $value
        );
        return "'" . $value . "'";
    }

    private static function sqlValues(array $row): string
    {
        $vals = [];
        foreach ($row as $v) {
            if ($v === null) {
                $vals[] = 'NULL';
            } else {
                $vals[] = self::sqlQuote((string)$v);
            }
        }
        return '(' . implode(',', $vals) . ')';
    }

    private static function dumpDatabasePhp(string $path, string &$error): bool
    {
        $error = '';
        if (!self::canPhpSnapshot()) {
            $error = 'DB_NAME/DB_USER lipsesc în .env.';
            return false;
        }
        $fh = @fopen($path, 'wb');
        if (!$fh) {
            $error = 'Nu pot crea fișierul de snapshot.';
            return false;
        }

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        fwrite($fh, "-- Snapshot DB\n");
        fwrite($fh, "SET NAMES utf8mb4;\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");

        $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $table = (string)$table;
            if ($table === '') continue;
            $st = $pdo->query("SHOW CREATE TABLE `" . str_replace('`', '``', $table) . "`");
            $row = $st ? $st->fetch(\PDO::FETCH_ASSOC) : null;
            $create = $row && isset($row['Create Table']) ? (string)$row['Create Table'] : '';
            if ($create === '') continue;
            fwrite($fh, "\nDROP TABLE IF EXISTS `" . $table . "`;\n");
            fwrite($fh, $create . ";\n");

            $stRows = $pdo->query("SELECT * FROM `" . str_replace('`', '``', $table) . "`");
            if (!$stRows) continue;
            $batch = [];
            while ($r = $stRows->fetch(\PDO::FETCH_ASSOC)) {
                $batch[] = self::sqlValues($r);
                if (count($batch) >= 200) {
                    fwrite($fh, "INSERT INTO `" . $table . "` VALUES " . implode(',', $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($fh, "INSERT INTO `" . $table . "` VALUES " . implode(',', $batch) . ";\n");
            }
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        return true;
    }

    private static function restoreDatabasePhp(string $path, string &$error): bool
    {
        $error = '';
        if (!self::canPhpSnapshot()) {
            $error = 'DB_NAME/DB_USER lipsesc în .env.';
            return false;
        }
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            $error = 'Nu pot deschide fișierul snapshot.';
            return false;
        }

        /** @var \PDO $pdo */
        $pdo = DB::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $sql = '';
        while (($line = fgets($fh)) !== false) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            if (str_starts_with($trim, '/*')) {
                continue;
            }
            $sql .= $line;
            if (str_ends_with(trim($line), ';')) {
                try {
                    $pdo->exec($sql);
                } catch (\Throwable $e) {
                    fclose($fh);
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                    $error = 'Eroare SQL: ' . $e->getMessage();
                    return false;
                }
                $sql = '';
            }
        }
        fclose($fh);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        return true;
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
        $hasPhpDump = self::canPhpSnapshot();
        $hasPhpRestore = self::canPhpSnapshot();
        $isWritable = is_writable($dir);
        echo View::render('system/admin_settings', [
            'title' => 'Setări admin',
            'snapshots' => $snapshots,
            'canExec' => $canExec,
            'hasDump' => $hasDump,
            'hasMysql' => $hasMysql,
            'hasPhpDump' => $hasPhpDump,
            'hasPhpRestore' => $hasPhpRestore,
            'isWritable' => $isWritable,
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
        if (!is_writable($dir)) {
            Session::flash('toast_error', 'Directorul de snapshot nu este accesibil la scriere.');
        } elseif (self::canExec() && self::binaryExists('mysqldump')) {
            if (self::dumpDatabase($path, $err)) {
                Session::flash('toast_success', 'Snapshot creat: ' . $name);
            } else {
                Session::flash('toast_error', 'Nu pot crea snapshot: ' . $err);
            }
        } elseif (self::dumpDatabasePhp($path, $err)) {
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
        if (self::canExec() && self::binaryExists('mysql')) {
            if (self::restoreDatabase($path, $err)) {
                Session::flash('toast_success', 'Snapshot restaurat: ' . $name);
            } else {
                Session::flash('toast_error', 'Nu pot restaura snapshot: ' . $err);
            }
        } elseif (self::restoreDatabasePhp($path, $err)) {
            Session::flash('toast_success', 'Snapshot restaurat: ' . $name);
        } else {
            Session::flash('toast_error', 'Nu pot restaura snapshot: ' . $err);
        }
        Response::redirect('/system/admin-settings');
    }
}
