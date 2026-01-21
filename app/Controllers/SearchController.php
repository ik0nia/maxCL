<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Response;
use App\Core\Url;
use PDO;

final class SearchController
{
    public static function global(): void
    {
        $qRaw = trim((string)($_GET['q'] ?? ''));
        $len = function_exists('mb_strlen') ? mb_strlen($qRaw) : strlen($qRaw);
        if ($qRaw === '' || $len < 2) {
            Response::json(['ok' => true, 'query' => $qRaw, 'results' => []]);
        }

        $limit = (int)($_GET['limit'] ?? 5);
        $limit = max(1, min(10, $limit));
        $like = '%' . $qRaw . '%';

        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $user = Auth::user();
        $role = (string)($user['role'] ?? '');

        $results = [];
        $opsRoles = [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR];
        $hplReadRoles = [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR, Auth::ROLE_VIEW];

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchOffers($pdo, $like, $limit);
            if ($items) $results[] = ['category' => 'Oferte', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchProjects($pdo, $like, $limit);
            if ($items) $results[] = ['category' => 'Proiecte', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchProducts($pdo, $like, $limit);
            if ($items) $results[] = ['category' => 'Produse', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchLabels($pdo, $like, $limit);
            if ($items) $results[] = ['category' => 'Etichete', 'items' => $items];
        }

        if (in_array($role, $hplReadRoles, true)) {
            $items = self::searchFinishes($pdo, $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Tip culoare', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchHplBoards($pdo, $like, $limit);
            if ($items) $results[] = ['category' => 'Stoc HPL', 'items' => $items];
        }

        Response::json(['ok' => true, 'query' => $qRaw, 'results' => $results]);
    }

    /** @return array<int, array{label:string,sub?:string,href:string}> */
    private static function searchOffers(PDO $pdo, string $like, int $limit): array
    {
        $st = $pdo->prepare("
            SELECT id, code, name, description, notes, technical_notes
            FROM offers
            WHERE code LIKE :q
               OR name LIKE :q
               OR description LIKE :q
               OR notes LIKE :q
               OR technical_notes LIKE :q
            ORDER BY updated_at DESC
            LIMIT " . (int)$limit
        );
        $st->execute([':q' => $like]);
        $rows = $st->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $label = $code !== '' ? ('Oferta ' . $code . ' · ' . $name) : ('Oferta #' . $id);
            $desc = self::firstNonEmpty($r['description'] ?? null, $r['notes'] ?? null, $r['technical_notes'] ?? null);
            $items[] = [
                'label' => $label,
                'sub' => self::snippet($desc),
                'href' => Url::to('/offers/' . $id),
            ];
        }
        return $items;
    }

    /** @return array<int, array{label:string,sub?:string,href:string}> */
    private static function searchProjects(PDO $pdo, string $like, int $limit): array
    {
        $st = $pdo->prepare("
            SELECT id, code, name, description, notes, technical_notes
            FROM projects
            WHERE code LIKE :q
               OR name LIKE :q
               OR description LIKE :q
               OR notes LIKE :q
               OR technical_notes LIKE :q
            ORDER BY updated_at DESC
            LIMIT " . (int)$limit
        );
        $st->execute([':q' => $like]);
        $rows = $st->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $label = $code !== '' ? ('Proiect ' . $code . ' · ' . $name) : ('Proiect #' . $id);
            $desc = self::firstNonEmpty($r['description'] ?? null, $r['notes'] ?? null, $r['technical_notes'] ?? null);
            $items[] = [
                'label' => $label,
                'sub' => self::snippet($desc),
                'href' => Url::to('/projects/' . $id),
            ];
        }
        return $items;
    }

    /** @return array<int, array{label:string,sub?:string,href:string}> */
    private static function searchProducts(PDO $pdo, string $like, int $limit): array
    {
        $st = $pdo->prepare("
            SELECT id, code, name, notes
            FROM products
            WHERE code LIKE :q
               OR name LIKE :q
               OR notes LIKE :q
            ORDER BY updated_at DESC
            LIMIT " . (int)$limit
        );
        $st->execute([':q' => $like]);
        $rows = $st->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            $label = $code !== '' ? ('Produs ' . $code . ' · ' . $name) : ('Produs ' . $name);
            $q = $code !== '' ? $code : $name;
            $items[] = [
                'label' => $label,
                'sub' => self::snippet($r['notes'] ?? null),
                'href' => Url::to('/products') . '?q=' . rawurlencode($q),
            ];
        }
        return $items;
    }

    /** @return array<int, array{label:string,sub?:string,href:string}> */
    private static function searchLabels(PDO $pdo, string $like, int $limit): array
    {
        $st = $pdo->prepare("
            SELECT id, name
            FROM labels
            WHERE name LIKE :q
            ORDER BY name ASC
            LIMIT " . (int)$limit
        );
        $st->execute([':q' => $like]);
        $rows = $st->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $name = trim((string)($r['name'] ?? ''));
            if ($name === '') continue;
            $items[] = [
                'label' => 'Etichetă: ' . $name,
                'sub' => 'Filtrează produsele după etichetă',
                'href' => Url::to('/products') . '?label=' . rawurlencode($name),
            ];
        }
        return $items;
    }

    /** @return array<int, array{label:string,sub?:string,href:string}> */
    private static function searchFinishes(PDO $pdo, string $like, int $limit, string $role): array
    {
        $st = $pdo->prepare("
            SELECT id, code, color_name, color_code
            FROM finishes
            WHERE code LIKE :q
               OR color_name LIKE :q
               OR color_code LIKE :q
            ORDER BY updated_at DESC
            LIMIT " . (int)$limit
        );
        $st->execute([':q' => $like]);
        $rows = $st->fetchAll();
        $items = [];
        $canEdit = in_array($role, [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = trim((string)($r['code'] ?? ''));
            $name = trim((string)($r['color_name'] ?? ''));
            $colorCode = trim((string)($r['color_code'] ?? ''));
            $label = 'Tip culoare: ' . ($code !== '' ? ($code . ' · ' . $name) : $name);
            $sub = $colorCode !== '' ? ('Cod culoare: ' . $colorCode) : '';
            $href = $canEdit ? Url::to('/hpl/tip-culoare/' . $id . '/edit') : Url::to('/hpl/catalog');
            $items[] = [
                'label' => $label,
                'sub' => $sub,
                'href' => $href,
            ];
        }
        return $items;
    }

    /** @return array<int, array{label:string,sub?:string,href:string}> */
    private static function searchHplBoards(PDO $pdo, string $like, int $limit): array
    {
        $st = $pdo->prepare("
            SELECT id, code, name, brand, thickness_mm
            FROM hpl_boards
            WHERE code LIKE :q
               OR name LIKE :q
               OR brand LIKE :q
            ORDER BY updated_at DESC
            LIMIT " . (int)$limit
        );
        $st->execute([':q' => $like]);
        $rows = $st->fetchAll();
        $items = [];
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
            $items[] = [
                'label' => $label,
                'sub' => $subParts ? implode(' · ', $subParts) : '',
                'href' => Url::to('/stock/boards/' . $id),
            ];
        }
        return $items;
    }

    private static function firstNonEmpty(?string ...$vals): string
    {
        foreach ($vals as $v) {
            $v = trim((string)($v ?? ''));
            if ($v !== '') return $v;
        }
        return '';
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
}
