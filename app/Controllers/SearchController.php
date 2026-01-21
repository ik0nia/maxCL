<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Response;
use App\Core\Url;
use App\Models\SearchIndex;
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

        try {
            /** @var PDO $pdo */
            $pdo = DB::pdo();
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => 'db_unavailable']);
        }
        $user = Auth::user();
        $role = (string)($user['role'] ?? '');

        $results = [];
        $opsRoles = [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR];
        $hplReadRoles = [Auth::ROLE_ADMIN, Auth::ROLE_MANAGER, Auth::ROLE_GESTIONAR, Auth::ROLE_OPERATOR, Auth::ROLE_VIEW];

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchIndex($pdo, 'offer', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Oferte', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchIndex($pdo, 'project', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Proiecte', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchIndex($pdo, 'project_product', $like, $limit, $role);
            $extra = self::searchIndex($pdo, 'product', $like, $limit, $role);
            $merged = array_slice(array_merge($items, $extra), 0, $limit);
            if ($merged) $results[] = ['category' => 'Produse', 'items' => $merged];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchIndex($pdo, 'client', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Clienți', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchIndex($pdo, 'client_group', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Grupuri clienți', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchIndex($pdo, 'label', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Etichete', 'items' => $items];
        }

        if (in_array($role, $hplReadRoles, true)) {
            $items = self::searchIndex($pdo, 'finish', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Tip culoare', 'items' => $items];
        }

        if (in_array($role, $hplReadRoles, true)) {
            $items = self::searchIndex($pdo, 'hpl_board', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Stoc HPL', 'items' => $items];
        }

        if (in_array($role, $hplReadRoles, true)) {
            $items = self::searchIndex($pdo, 'hpl_thickness', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Grosimi HPL', 'items' => $items];
        }

        if (in_array($role, $opsRoles, true)) {
            $items = self::searchIndex($pdo, 'magazie_item', $like, $limit, $role);
            if ($items) $results[] = ['category' => 'Magazie', 'items' => $items];
        }

        Response::json(['ok' => true, 'query' => $qRaw, 'results' => $results]);
    }

    public static function reindexIfNeeded(): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        try {
            $res = SearchIndex::rebuildIfDue(900, Auth::id());
            Response::json(['ok' => true, 'ran' => (bool)($res['ran'] ?? false), 'total' => (int)($res['total'] ?? 0)]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** @return array<int, array{label:string,sub?:string,href:string}> */
    private static function searchIndex(PDO $pdo, string $type, string $like, int $limit, string $role): array
    {
        try {
            $st = $pdo->prepare("
                SELECT entity_id, label, sub, href
                FROM search_index
                WHERE entity_type = :t
                  AND search_text LIKE :q
                ORDER BY updated_at DESC, id DESC
                LIMIT " . (int)$limit
            );
            $st->execute([':t' => $type, ':q' => $like]);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $items = [];
        $canEditFinish = in_array($role, [Auth::ROLE_ADMIN, Auth::ROLE_GESTIONAR], true);
        foreach ($rows as $r) {
            $href = (string)($r['href'] ?? '#');
            $entityId = (int)($r['entity_id'] ?? 0);
            if ($type === 'finish') {
                $href = $canEditFinish && $entityId > 0
                    ? Url::to('/hpl/tip-culoare/' . $entityId . '/edit')
                    : Url::to('/hpl/catalog');
            }
            if ($type === 'hpl_board' && $role === Auth::ROLE_VIEW) {
                $href = Url::to('/hpl/catalog');
            }
            if ($type === 'hpl_thickness' && $role === Auth::ROLE_VIEW) {
                $href = Url::to('/hpl/catalog');
            }
            $items[] = [
                'label' => (string)($r['label'] ?? ''),
                'sub' => (string)($r['sub'] ?? ''),
                'href' => $href,
            ];
        }
        return $items;
    }
}
