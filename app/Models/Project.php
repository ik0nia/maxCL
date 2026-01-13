<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Project
{
    /** @return array<int, array<string,mixed>> */
    public static function forClient(int $clientId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC');
        $st->execute([$clientId]);
        return $st->fetchAll();
    }
}

