<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ProjectProduct
{
    /** @return array<int, array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        try {
            $st = $pdo->prepare('
                SELECT
                  pp.*,
                  p.code AS product_code,
                  p.name AS product_name,
                  p.notes AS product_notes,
                  p.sale_price AS product_sale_price,
                  hb.code AS hpl_board_code,
                  hb.name AS hpl_board_name
                FROM project_products pp
                INNER JOIN products p ON p.id = pp.product_id
                LEFT JOIN hpl_boards hb ON hb.id = pp.hpl_board_id
                WHERE pp.project_id = ?
                ORDER BY pp.id DESC
            ');
            $st->execute([(int)$projectId]);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            // Compat: schema veche fără products.sale_price
            $m = strtolower($e->getMessage());
            if (!(str_contains($m, 'unknown column') && str_contains($m, 'sale_price'))) {
                throw $e;
            }
            $st = $pdo->prepare('
                SELECT
                  pp.*,
                  p.code AS product_code,
                  p.name AS product_name,
                  p.notes AS product_notes,
                  hb.code AS hpl_board_code,
                  hb.name AS hpl_board_name
                FROM project_products pp
                INNER JOIN products p ON p.id = pp.product_id
                LEFT JOIN hpl_boards hb ON hb.id = pp.hpl_board_id
                WHERE pp.project_id = ?
                ORDER BY pp.id DESC
            ');
            $st->execute([(int)$projectId]);
            return $st->fetchAll();
        }
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM project_products WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function addToProject(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            INSERT INTO project_products (
              project_id, product_id, qty, unit,
              m2_per_unit, surface_type, surface_value,
              production_status, hpl_board_id, delivered_qty, notes, cnc_override_json
            )
            VALUES (
              :project_id, :product_id, :qty, :unit,
              :m2, :surface_type, :surface_value,
              :st, :hpl_board_id, :del, :notes, :cnc
            )
        ');
        $st->execute([
            ':project_id' => (int)$data['project_id'],
            ':product_id' => (int)$data['product_id'],
            ':qty' => (float)($data['qty'] ?? 1),
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':m2' => (float)($data['m2_per_unit'] ?? 0),
            ':surface_type' => $data['surface_type'] ?? null,
            ':surface_value' => $data['surface_value'] ?? null,
            ':st' => (string)($data['production_status'] ?? 'CREAT'),
            ':hpl_board_id' => $data['hpl_board_id'] ?? null,
            ':del' => (float)($data['delivered_qty'] ?? 0),
            ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
            ':cnc' => $data['cnc_override_json'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function updateFields(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            UPDATE project_products
            SET qty=:qty, unit=:unit,
                m2_per_unit=:m2, surface_type=:surface_type, surface_value=:surface_value,
                production_status=:st, hpl_board_id=:hpl_board_id,
                delivered_qty=:del, notes=:notes
            WHERE id=:id
        ');
        $st->execute([
            ':id' => $id,
            ':qty' => (float)($data['qty'] ?? 1),
            ':unit' => (string)($data['unit'] ?? 'buc'),
            ':m2' => (float)($data['m2_per_unit'] ?? 0),
            ':surface_type' => $data['surface_type'] ?? null,
            ':surface_value' => $data['surface_value'] ?? null,
            ':st' => (string)($data['production_status'] ?? 'CREAT'),
            ':hpl_board_id' => $data['hpl_board_id'] ?? null,
            ':del' => (float)($data['delivered_qty'] ?? 0),
            ':notes' => (isset($data['notes']) && trim((string)$data['notes']) !== '') ? trim((string)$data['notes']) : null,
        ]);
    }

    public static function updateBilling(int $id, ?int $invoiceClientId, ?int $deliveryAddressId): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('
            UPDATE project_products
            SET invoice_client_id = :inv,
                delivery_address_id = :addr
            WHERE id = :id
        ');
        $st->execute([
            ':id' => $id,
            ':inv' => ($invoiceClientId !== null && $invoiceClientId > 0) ? $invoiceClientId : null,
            ':addr' => ($deliveryAddressId !== null && $deliveryAddressId > 0) ? $deliveryAddressId : null,
        ]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $status = trim($status);
        // finalized_at: setăm când trece la GATA_DE_LIVRARE (sau după), iar dacă revine înapoi înainte de asta, resetăm.
        $isFinal = in_array($status, ['GATA_DE_LIVRARE','AVIZAT','LIVRAT'], true);
        $isBeforeFinal = in_array($status, ['CREAT','PROIECTARE','CNC','MONTAJ'], true);
        try {
            if ($isFinal) {
                $st = $pdo->prepare("
                    UPDATE project_products
                    SET production_status = :st,
                        finalized_at = COALESCE(finalized_at, NOW())
                    WHERE id = :id
                ");
            } elseif ($isBeforeFinal) {
                $st = $pdo->prepare("
                    UPDATE project_products
                    SET production_status = :st,
                        finalized_at = NULL
                    WHERE id = :id
                ");
            } else {
                $st = $pdo->prepare("
                    UPDATE project_products
                    SET production_status = :st
                    WHERE id = :id
                ");
            }
            $st->execute([
                ':id' => $id,
                ':st' => $status,
            ]);
        } catch (\Throwable $e) {
            // Compat: dacă finalized_at nu există încă
            $st = $pdo->prepare('UPDATE project_products SET production_status = :st WHERE id = :id');
            $st->execute([
                ':id' => $id,
                ':st' => $status,
            ]);
        }
    }

    public static function updateAvizData(int $id, ?string $avizNumber, ?string $avizDate): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $num = $avizNumber !== null && trim($avizNumber) !== '' ? trim($avizNumber) : null;
        $date = $avizDate !== null && trim($avizDate) !== '' ? trim($avizDate) : null;
        try {
            $st = $pdo->prepare("
                UPDATE project_products
                SET aviz_number = :num,
                    aviz_date = :dt
                WHERE id = :id
            ");
            $st->execute([
                ':id' => $id,
                ':num' => $num,
                ':dt' => $date,
            ]);
        } catch (\Throwable $e) {
            // compat: coloane pot lipsi
        }
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM project_products WHERE id = ?');
        $st->execute([$id]);
    }
}

