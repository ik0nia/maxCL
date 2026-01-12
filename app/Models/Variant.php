<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Variant
{
    /** @return array<int, array<string,mixed>> */
    public static function allWithJoins(): array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $sql = "
            SELECT
              v.id,
              v.material_id,
              v.finish_face_id,
              v.finish_back_id,
              m.code AS material_code,
              m.name AS material_name,
              m.brand AS material_brand,
              m.thickness_mm,
              f1.code AS face_code,
              f1.color_name AS face_color_name,
              f1.texture_name AS face_texture_name,
              f1.thumb_path AS face_thumb_path,
              f2.code AS back_code,
              f2.color_name AS back_color_name,
              f2.texture_name AS back_texture_name,
              f2.thumb_path AS back_thumb_path
            FROM material_variants v
            JOIN materials m ON m.id = v.material_id
            JOIN finishes f1 ON f1.id = v.finish_face_id
            LEFT JOIN finishes f2 ON f2.id = v.finish_back_id
            ORDER BY m.brand ASC, m.name ASC, f1.color_name ASC, f1.texture_name ASC
        ";
        return $pdo->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT * FROM material_variants WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare(
            'INSERT INTO material_variants (material_id, finish_face_id, finish_back_id)
             VALUES (:material_id,:finish_face_id,:finish_back_id)'
        );
        $st->execute([
            ':material_id' => (int)$data['material_id'],
            ':finish_face_id' => (int)$data['finish_face_id'],
            ':finish_back_id' => $data['finish_back_id'] !== null ? (int)$data['finish_back_id'] : null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare(
            'UPDATE material_variants
             SET material_id=:material_id, finish_face_id=:finish_face_id, finish_back_id=:finish_back_id
             WHERE id=:id'
        );
        $st->execute([
            ':id' => $id,
            ':material_id' => (int)$data['material_id'],
            ':finish_face_id' => (int)$data['finish_face_id'],
            ':finish_back_id' => $data['finish_back_id'] !== null ? (int)$data['finish_back_id'] : null,
        ]);
    }

    public static function delete(int $id): void
    {
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM material_variants WHERE id = ?');
        $st->execute([$id]);
    }
}

