<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class SearchIndex
{
    /** @return array{total:int,byType:array<string,int>} */
    public static function rebuild(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        self::ensureTable($pdo);

        $pdo->exec('DELETE FROM search_index');
        $now = date('Y-m-d H:i:s');

        $counts = [
            'offer' => self::indexOffers($pdo, $now),
            'project' => self::indexProjects($pdo, $now),
            'product' => self::indexProducts($pdo, $now),
            'project_product' => self::indexProjectProducts($pdo, $now),
            'label' => self::indexLabels($pdo, $now),
            'finish' => self::indexFinishes($pdo, $now),
            'hpl_board' => self::indexHplBoards($pdo, $now),
            'magazie_item' => self::indexMagazieItems($pdo, $now),
        ];

        $total = 0;
        foreach ($counts as $c) $total += (int)$c;
        return ['total' => $total, 'byType' => $counts];
    }

    private static function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS search_index (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              entity_type VARCHAR(32) NOT NULL,
              entity_id BIGINT UNSIGNED NULL,
              label VARCHAR(255) NOT NULL,
              sub VARCHAR(255) NULL,
              href VARCHAR(255) NOT NULL,
              search_text TEXT NOT NULL,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_search_entity (entity_type, entity_id),
              KEY idx_search_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private static function insertRow(PDO $pdo, array $row): void
    {
        static $stmt = null;
        if ($stmt === null) {
            $stmt = $pdo->prepare("
                INSERT INTO search_index
                  (entity_type, entity_id, label, sub, href, search_text, updated_at)
                VALUES
                  (:entity_type, :entity_id, :label, :sub, :href, :search_text, :updated_at)
            ");
        }
        $stmt->execute([
            ':entity_type' => $row['entity_type'],
            ':entity_id' => $row['entity_id'],
            ':label' => $row['label'],
            ':sub' => $row['sub'],
            ':href' => $row['href'],
            ':search_text' => $row['search_text'],
            ':updated_at' => $row['updated_at'],
        ]);
    }

    private static function indexOffers(PDO $pdo, string $now): int
    {
        $rows = self::fetchAllSafe($pdo, "
            SELECT id, code, name, description, notes, technical_notes
            FROM offers
            ORDER BY id DESC
        ");
        if (!$rows) {
            $rows = self::fetchAllSafe($pdo, "
                SELECT id, code, name
                FROM offers
                ORDER BY id DESC
            ");
        }
        if (!$rows) return 0;
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $label = $code !== '' ? ('Oferta ' . $code . ' · ' . $name) : ('Oferta #' . $id);
            $desc = self::firstNonEmpty($r['description'] ?? null, $r['notes'] ?? null, $r['technical_notes'] ?? null);
            $searchText = self::joinText([$code, $name, $desc]);
            self::insertRow($pdo, [
                'entity_type' => 'offer',
                'entity_id' => $id,
                'label' => $label,
                'sub' => self::snippet($desc),
                'href' => \App\Core\Url::to('/offers/' . $id),
                'search_text' => $searchText,
                'updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexProjects(PDO $pdo, string $now): int
    {
        $rows = self::fetchAllSafe($pdo, "
            SELECT
              pr.id, pr.code, pr.name, pr.description, pr.notes, pr.technical_notes,
              GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') AS labels
            FROM projects pr
            LEFT JOIN entity_labels el ON el.entity_type = 'projects' AND el.entity_id = pr.id
            LEFT JOIN labels l ON l.id = el.label_id
            GROUP BY pr.id
            ORDER BY pr.id DESC
        ");
        if (!$rows) {
            $rows = self::fetchAllSafe($pdo, "
                SELECT id, code, name
                FROM projects
                ORDER BY id DESC
            ");
        }
        if (!$rows) return 0;
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $label = $code !== '' ? ('Proiect ' . $code . ' · ' . $name) : ('Proiect #' . $id);
            $desc = self::firstNonEmpty($r['description'] ?? null, $r['notes'] ?? null, $r['technical_notes'] ?? null);
            $labels = trim((string)($r['labels'] ?? ''));
            $searchText = self::joinText([$code, $name, $desc, $labels]);
            self::insertRow($pdo, [
                'entity_type' => 'project',
                'entity_id' => $id,
                'label' => $label,
                'sub' => self::snippet($labels !== '' ? ('Etichete: ' . $labels) : $desc),
                'href' => \App\Core\Url::to('/projects/' . $id),
                'search_text' => $searchText,
                'updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexProducts(PDO $pdo, string $now): int
    {
        $rows = self::fetchAllSafe($pdo, "
            SELECT id, code, name, notes
            FROM products
            ORDER BY id DESC
        ");
        if (!$rows) {
            $rows = self::fetchAllSafe($pdo, "
                SELECT id, code, name
                FROM products
                ORDER BY id DESC
            ");
        }
        if (!$rows) return 0;
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $label = $code !== '' ? ('Produs ' . $code . ' · ' . $name) : ('Produs ' . $name);
            $q = $code !== '' ? $code : $name;
            $desc = (string)($r['notes'] ?? '');
            $searchText = self::joinText([$code, $name, $desc]);
            self::insertRow($pdo, [
                'entity_type' => 'product',
                'entity_id' => $id,
                'label' => $label,
                'sub' => self::snippet($desc),
                'href' => \App\Core\Url::to('/products') . '?q=' . rawurlencode($q),
                'search_text' => $searchText,
                'updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexProjectProducts(PDO $pdo, string $now): int
    {
        $rows = self::fetchAllSafe($pdo, "
            SELECT
              pp.id AS pp_id, pp.project_id, pp.notes,
              p.code AS product_code, p.name AS product_name,
              pr.code AS project_code, pr.name AS project_name,
              GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') AS labels
            FROM project_products pp
            INNER JOIN products p ON p.id = pp.product_id
            INNER JOIN projects pr ON pr.id = pp.project_id
            LEFT JOIN entity_labels el ON el.entity_type = 'project_products' AND el.entity_id = pp.id
            LEFT JOIN labels l ON l.id = el.label_id
            GROUP BY pp.id
            ORDER BY pp.id DESC
        ");
        if (!$rows) {
            $rows = self::fetchAllSafe($pdo, "
                SELECT
                  pp.id AS pp_id, pp.project_id, pp.notes,
                  p.code AS product_code, p.name AS product_name,
                  pr.code AS project_code, pr.name AS project_name
                FROM project_products pp
                INNER JOIN products p ON p.id = pp.product_id
                INNER JOIN projects pr ON pr.id = pp.project_id
                ORDER BY pp.id DESC
            ");
        }
        if (!$rows) return 0;
        $count = 0;
        foreach ($rows as $r) {
            $ppId = (int)($r['pp_id'] ?? 0);
            $projectId = (int)($r['project_id'] ?? 0);
            $code = trim((string)($r['product_code'] ?? ''));
            $name = trim((string)($r['product_name'] ?? ''));
            $projCode = trim((string)($r['project_code'] ?? ''));
            $projName = trim((string)($r['project_name'] ?? ''));
            $labels = trim((string)($r['labels'] ?? ''));
            $label = $code !== '' ? ('Produs: ' . $code . ' · ' . $name) : ('Produs: ' . $name);
            $subParts = [];
            if ($projName !== '' || $projCode !== '') {
                $subParts[] = 'Proiect: ' . trim(($projCode !== '' ? $projCode : '') . ' ' . $projName);
            }
            if ($labels !== '') $subParts[] = 'Etichete: ' . $labels;
            $searchText = self::joinText([$code, $name, $projCode, $projName, (string)($r['notes'] ?? ''), $labels]);
            self::insertRow($pdo, [
                'entity_type' => 'project_product',
                'entity_id' => $ppId,
                'label' => $label,
                'sub' => $subParts ? implode(' · ', $subParts) : '',
                'href' => \App\Core\Url::to('/projects/' . $projectId . '?tab=products#pp-' . $ppId),
                'search_text' => $searchText,
                'updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexLabels(PDO $pdo, string $now): int
    {
        $rows = self::fetchAllSafe($pdo, "
            SELECT id, name
            FROM labels
            ORDER BY name ASC
        ");
        if (!$rows) return 0;
        $count = 0;
        foreach ($rows as $r) {
            $name = trim((string)($r['name'] ?? ''));
            if ($name === '') continue;
            self::insertRow($pdo, [
                'entity_type' => 'label',
                'entity_id' => (int)($r['id'] ?? 0),
                'label' => 'Etichetă: ' . $name,
                'sub' => 'Filtrează produsele după etichetă',
                'href' => \App\Core\Url::to('/products') . '?label=' . rawurlencode($name),
                'search_text' => $name,
                'updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexFinishes(PDO $pdo, string $now): int
    {
        $rows = self::fetchAllSafe($pdo, "
            SELECT id, code, color_name, color_code
            FROM finishes
            ORDER BY id DESC
        ");
        if (!$rows) {
            $rows = self::fetchAllSafe($pdo, "
                SELECT id, code, color_name
                FROM finishes
                ORDER BY id DESC
            ");
        }
        if (!$rows) return 0;
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['color_name'] ?? ''));
            $colorCode = trim((string)($r['color_code'] ?? ''));
            $label = 'Tip culoare: ' . ($code !== '' ? ($code . ' · ' . $name) : $name);
            $sub = $colorCode !== '' ? ('Cod culoare: ' . $colorCode) : '';
            $searchText = self::joinText([$code, $name, $colorCode]);
            self::insertRow($pdo, [
                'entity_type' => 'finish',
                'entity_id' => $id,
                'label' => $label,
                'sub' => $sub,
                'href' => \App\Core\Url::to('/hpl/catalog'),
                'search_text' => $searchText,
                'updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexHplBoards(PDO $pdo, string $now): int
    {
        $rows = self::fetchAllSafe($pdo, "
            SELECT id, code, name, brand, thickness_mm
            FROM hpl_boards
            ORDER BY id DESC
        ");
        if (!$rows) {
            $rows = self::fetchAllSafe($pdo, "
                SELECT id, code, name
                FROM hpl_boards
                ORDER BY id DESC
            ");
        }
        if (!$rows) return 0;
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $brand = trim((string)($r['brand'] ?? ''));
            $th = (int)($r['thickness_mm'] ?? 0);
            $label = $code !== '' ? ('Material HPL: ' . $code . ' · ' . $name) : ('Material HPL: ' . $name);
            $subParts = [];
            if ($brand !== '') $subParts[] = $brand;
            if ($th > 0) $subParts[] = $th . ' mm';
            $searchText = self::joinText([$code, $name, $brand, (string)$th]);
            self::insertRow($pdo, [
                'entity_type' => 'hpl_board',
                'entity_id' => $id,
                'label' => $label,
                'sub' => $subParts ? implode(' · ', $subParts) : '',
                'href' => \App\Core\Url::to('/stock/boards/' . $id),
                'search_text' => $searchText,
                'updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexMagazieItems(PDO $pdo, string $now): int
    {
        $rows = self::fetchAllSafe($pdo, "
            SELECT id, winmentor_code, name
            FROM magazie_items
            ORDER BY id DESC
        ");
        if (!$rows) {
            $rows = self::fetchAllSafe($pdo, "
                SELECT id, name
                FROM magazie_items
                ORDER BY id DESC
            ");
        }
        if (!$rows) return 0;
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['winmentor_code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $label = $code !== '' ? ('Accesoriu: ' . $code . ' · ' . $name) : ('Accesoriu: ' . $name);
            $searchText = self::joinText([$code, $name]);
            self::insertRow($pdo, [
                'entity_type' => 'magazie_item',
                'entity_id' => $id,
                'label' => $label,
                'sub' => '',
                'href' => \App\Core\Url::to('/magazie/stoc/' . $id),
                'search_text' => $searchText,
                'updated_at' => $now,
            ]);
            $count++;
        }
        return $count;
    }

    private static function firstNonEmpty(?string ...$vals): string
    {
        foreach ($vals as $v) {
            $v = trim((string)($v ?? ''));
            if ($v !== '') return $v;
        }
        return '';
    }

    /** @param array<int, string> $parts */
    private static function joinText(array $parts): string
    {
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string)$p);
            if ($p !== '') $out[] = $p;
        }
        return implode(' ', $out);
    }

    private static function snippet(?string $text, int $max = 120): string
    {
        $clean = trim((string)($text ?? ''));
        if ($clean === '') return '';
        $clean = preg_replace('/\s+/', ' ', $clean) ?: $clean;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($clean) > $max) {
                return mb_substr($clean, 0, $max - 1) . '…';
            }
            return $clean;
        }
        if (strlen($clean) > $max) {
            return substr($clean, 0, $max - 1) . '…';
        }
        return $clean;
    }

    /** @return array<int, array<string,mixed>> */
    private static function fetchAllSafe(PDO $pdo, string $sql): array
    {
        try {
            $st = $pdo->query($sql);
            return $st ? $st->fetchAll() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
