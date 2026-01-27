<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectTimeLog
{
    public const CATEGORY_PROJECT = 'PROIECTARE';
    public const CATEGORY_PREP_CNC = 'PREGATIRE_CNC';
    public const CATEGORY_CUT_CNC = 'DEBITARE_CNC';
    public const CATEGORY_ATELIER = 'ATELIER';

    /** @return array<int, array{value:string,label:string}> */
    public static function categories(): array
    {
        return [
            ['value' => self::CATEGORY_PROJECT, 'label' => 'Proiectare'],
            ['value' => self::CATEGORY_PREP_CNC, 'label' => 'Pregatire CNC'],
            ['value' => self::CATEGORY_CUT_CNC, 'label' => 'Debitare CNC'],
            ['value' => self::CATEGORY_ATELIER, 'label' => 'Atelier'],
        ];
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO project_time_logs
              (project_id, project_product_id, category, person, description, minutes, created_by)
            VALUES
              (:project_id, :pp_id, :category, :person, :description, :minutes, :created_by)
        ');
        $desc = isset($data['description']) ? trim((string)$data['description']) : '';
        $st->execute([
            ':project_id' => (int)$data['project_id'],
            ':pp_id' => $data['project_product_id'] ?? null,
            ':category' => (string)$data['category'],
            ':person' => trim((string)$data['person']),
            ':description' => $desc !== '' ? $desc : null,
            ':minutes' => (int)$data['minutes'],
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId, int $limit = 500): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = '
            SELECT
              t.*,
              u.name AS user_name,
              u.email AS user_email,
              p.name AS product_name,
              pr.code AS project_code,
              pr.name AS project_name
            FROM project_time_logs t
            INNER JOIN projects pr ON pr.id = t.project_id
            LEFT JOIN project_products pp ON pp.id = t.project_product_id
            LEFT JOIN products p ON p.id = pp.product_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.project_id = ?
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT ' . (int)$limit;
        $st = $pdo->prepare($sql);
        $st->execute([(int)$projectId]);
        return $st->fetchAll();
    }

    /** @return array<int, string> */
    public static function searchPeople(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') return [];
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            SELECT DISTINCT person
            FROM project_time_logs
            WHERE person LIKE ?
            ORDER BY person ASC
            LIMIT ' . (int)$limit
        );
        $st->execute(['%' . $q . '%']);
        $rows = $st->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $name = trim((string)($r['person'] ?? ''));
            if ($name !== '') $out[] = $name;
        }
        return $out;
    }

    /** @return array<int, string> */
    public static function searchDescriptions(string $q, ?string $category = null, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') return [];
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $where = ['description IS NOT NULL', "description <> ''", 'description LIKE ?'];
        $params = ['%' . $q . '%'];
        if ($category !== null && $category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }
        $sql = '
            SELECT DISTINCT description
            FROM project_time_logs
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY description ASC
            LIMIT ' . (int)$limit;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $desc = trim((string)($r['description'] ?? ''));
            if ($desc !== '') $out[] = $desc;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int, array<string,mixed>>
     */
    public static function search(array $filters, int $limit = 5000): array
    {
        [$where, $params] = self::buildFilters($filters);
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = '
            SELECT
              t.*,
              pr.code AS project_code,
              pr.name AS project_name,
              p.name AS product_name
            FROM project_time_logs t
            INNER JOIN projects pr ON pr.id = t.project_id
            LEFT JOIN project_products pp ON pp.id = t.project_product_id
            LEFT JOIN products p ON p.id = pp.product_id
        ';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . (int)$limit;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @param array<string,mixed> $filters */
    public static function sumMinutes(array $filters): int
    {
        [$where, $params] = self::buildFilters($filters);
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = 'SELECT SUM(t.minutes) AS total_minutes FROM project_time_logs t';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $sum = $st->fetchColumn();
        return $sum !== null ? (int)$sum : 0;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    private static function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];
        $category = isset($filters['category']) ? trim((string)$filters['category']) : '';
        if ($category !== '') {
            $where[] = 't.category = :category';
            $params[':category'] = $category;
        }
        $person = isset($filters['person']) ? trim((string)$filters['person']) : '';
        if ($person !== '') {
            $where[] = 't.person = :person';
            $params[':person'] = $person;
        }
        $dateFrom = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
        if ($dateFrom !== '') {
            $where[] = 't.created_at >= :df';
            $params[':df'] = $dateFrom . ' 00:00:00';
        }
        $dateTo = isset($filters['date_to']) ? trim((string)$filters['date_to']) : '';
        if ($dateTo !== '') {
            $where[] = 't.created_at <= :dt';
            $params[':dt'] = $dateTo . ' 23:59:59';
        }
        return [$where, $params];
    }
}

