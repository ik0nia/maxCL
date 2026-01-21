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

        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM search_index');

            $counts = [
                'offer' => self::indexOffers($pdo),
                'project' => self::indexProjects($pdo),
                'product' => self::indexProducts($pdo),
                'label' => self::indexLabels($pdo),
                'finish' => self::indexFinishes($pdo),
                'hpl_board' => self::indexHplBoards($pdo),
                'magazie_item' => self::indexMagazieItems($pdo),
            ];

            $pdo->commit();
        } catch (\Throwable $e) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (\Throwable $e2) {}
            throw $e;
        }

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

    private static function indexOffers(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'offers')) return 0;
        $rows = $pdo->query("
            SELECT id, code, name, description, notes, technical_notes, updated_at
            FROM offers
            ORDER BY updated_at DESC
        ")->fetchAll();
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
                'updated_at' => (string)($r['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexProjects(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'projects')) return 0;
        $rows = $pdo->query("
            SELECT id, code, name, description, notes, technical_notes, updated_at
            FROM projects
            ORDER BY updated_at DESC
        ")->fetchAll();
        $count = 0;
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $label = $code !== '' ? ('Proiect ' . $code . ' · ' . $name) : ('Proiect #' . $id);
            $desc = self::firstNonEmpty($r['description'] ?? null, $r['notes'] ?? null, $r['technical_notes'] ?? null);
            $searchText = self::joinText([$code, $name, $desc]);
            self::insertRow($pdo, [
                'entity_type' => 'project',
                'entity_id' => $id,
                'label' => $label,
                'sub' => self::snippet($desc),
                'href' => \App\Core\Url::to('/projects/' . $id),
                'search_text' => $searchText,
                'updated_at' => (string)($r['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexProducts(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'products')) return 0;
        $rows = $pdo->query("
            SELECT id, code, name, notes, updated_at
            FROM products
            ORDER BY updated_at DESC
        ")->fetchAll();
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
                'updated_at' => (string)($r['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexLabels(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'labels')) return 0;
        $rows = $pdo->query("
            SELECT id, name, updated_at
            FROM labels
            ORDER BY name ASC
        ")->fetchAll();
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
                'updated_at' => (string)($r['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexFinishes(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'finishes')) return 0;
        $rows = $pdo->query("
            SELECT id, code, color_name, color_code, updated_at
            FROM finishes
            ORDER BY updated_at DESC
        ")->fetchAll();
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
                'updated_at' => (string)($r['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexHplBoards(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'hpl_boards')) return 0;
        $rows = $pdo->query("
            SELECT id, code, name, brand, thickness_mm, updated_at
            FROM hpl_boards
            ORDER BY updated_at DESC
        ")->fetchAll();
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
                'updated_at' => (string)($r['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $count++;
        }
        return $count;
    }

    private static function indexMagazieItems(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'magazie_items')) return 0;
        $rows = $pdo->query("
            SELECT id, winmentor_code, name, updated_at
            FROM magazie_items
            ORDER BY updated_at DESC
        ")->fetchAll();
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
                'updated_at' => (string)($r['updated_at'] ?? date('Y-m-d H:i:s')),
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

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare("SHOW TABLES LIKE ?");
            $st->execute([$table]);
            return (bool)$st->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
