<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class SqlInstaller
{
    /**
     * Rulează un fișier .sql prin PDO, split pe statement-uri.
     * @return array{statements:int}
     */
    public static function runFile(PDO $pdo, string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Nu există fișierul SQL: ' . $path);
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException('Nu pot citi fișierul SQL.');
        }

        $statements = self::splitSql($sql);
        $count = 0;
        foreach ($statements as $stmt) {
            $trim = trim($stmt);
            if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '/*')) {
                continue;
            }
            $pdo->exec($stmt);
            $count++;
        }

        return ['statements' => $count];
    }

    /**
     * Split simplu SQL pe ';' (ignoră ';' din string-uri).
     * @return array<int, string>
     */
    private static function splitSql(string $sql): array
    {
        // Normalize line endings
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);

        // Remove full-line comments starting with --
        $lines = explode("\n", $sql);
        $clean = [];
        foreach ($lines as $line) {
            $t = ltrim($line);
            if (str_starts_with($t, '--')) {
                continue;
            }
            $clean[] = $line;
        }
        $sql = implode("\n", $clean);

        $out = [];
        $buf = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';

            if ($ch === "'" && !$inDouble && $prev !== '\\') {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && $prev !== '\\') {
                $inDouble = !$inDouble;
            }

            if ($ch === ';' && !$inSingle && !$inDouble) {
                $out[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $out[] = $buf;
        }

        return $out;
    }
}

