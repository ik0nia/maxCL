<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Migrări DB "safe-by-default" (idempotente) pentru hosting.
 *
 * Scop:
 * - după update din Git, aplicația își aduce singură schema la zi
 * - fără să fie nevoie să rulezi manual Setup/Update DB
 *
 * IMPORTANT:
 * - migrările sunt "best effort": dacă nu există permisiuni, nu blocăm aplicația
 * - rulăm rapid: fiecare migrare se execută o singură dată (schema_migrations)
 */
final class DbMigrations
{
    private const MIGRATIONS_TABLE = 'schema_migrations';
    private const TOAST_EMAIL = 'sacodrut@ikonia.ro';

    /** Rulează migrările la pornirea aplicației (best-effort). */
    public static function runAuto(): void
    {
        // Nu rula dacă DB nu e configurat / nu putem conecta.
        try {
            $pdo = DB::pdo();
        } catch (\Throwable $e) {
            return;
        }

        try {
            self::ensureMigrationsTable($pdo);
        } catch (\Throwable $e) {
            return;
        }

        $applied = 0;
        $failed = 0;
        $ran = [];

        foreach (self::migrations() as $m) {
            $id = $m['id'];
            $label = $m['label'];
            $fn = $m['fn'];

            try {
                if (self::isApplied($pdo, $id)) {
                    continue;
                }

                $fn($pdo);
                self::markApplied($pdo, $id);
                $applied++;
                $ran[] = $label;
            } catch (\Throwable $e) {
                $failed++;
                // nu blocăm aplicația
            }
        }

        // Feedback discret pentru userul tău (doar dacă e logat).
        try {
            $u = Auth::user();
            if ($u && strtolower((string)($u['email'] ?? '')) === self::TOAST_EMAIL && $applied > 0) {
                Session::flash('toast_success', 'Update DB aplicat automat (' . $applied . ' schimbări).');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Audit (dacă tabela audit există).
        try {
            if ($applied > 0 || $failed > 0) {
                Audit::log('DB_AUTO_MIGRATE', 'system', null, null, null, [
                    'applied_count' => $applied,
                    'failed_count' => $failed,
                    'applied' => $ran,
                ]);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /** @return array<int, array{id:string,label:string,fn:callable(PDO):void}> */
    private static function migrations(): array
    {
        return [
            [
                'id' => '2026-01-13_01_create_textures',
                'label' => 'CREATE TABLE textures',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'textures')) return;
                    $pdo->exec("
                        CREATE TABLE textures (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          code VARCHAR(64) NULL,
                          name VARCHAR(190) NOT NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          UNIQUE KEY uq_textures_code (code),
                          KEY idx_textures_name (name)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-13_02_create_hpl_boards',
                'label' => 'CREATE TABLE hpl_boards',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'hpl_boards')) return;
                    // depinde de finishes + textures
                    if (!self::tableExists($pdo, 'finishes')) return;
                    if (!self::tableExists($pdo, 'textures')) return;
                    $pdo->exec("
                        CREATE TABLE hpl_boards (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          code VARCHAR(64) NOT NULL,
                          name VARCHAR(190) NOT NULL,
                          brand VARCHAR(190) NOT NULL,
                          thickness_mm INT NOT NULL,
                          std_width_mm INT NOT NULL,
                          std_height_mm INT NOT NULL,
                          sale_price DECIMAL(12,2) NULL,
                          face_color_id INT UNSIGNED NOT NULL,
                          face_texture_id INT UNSIGNED NOT NULL,
                          back_color_id INT UNSIGNED NULL,
                          back_texture_id INT UNSIGNED NULL,
                          notes TEXT NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          UNIQUE KEY uq_hpl_boards_code (code),
                          KEY idx_hpl_brand (brand),
                          KEY idx_hpl_thickness (thickness_mm),
                          KEY idx_hpl_face_color (face_color_id),
                          KEY idx_hpl_face_texture (face_texture_id),
                          KEY idx_hpl_back_color (back_color_id),
                          KEY idx_hpl_back_texture (back_texture_id),
                          CONSTRAINT fk_hpl_face_color FOREIGN KEY (face_color_id) REFERENCES finishes(id),
                          CONSTRAINT fk_hpl_face_texture FOREIGN KEY (face_texture_id) REFERENCES textures(id),
                          CONSTRAINT fk_hpl_back_color FOREIGN KEY (back_color_id) REFERENCES finishes(id),
                          CONSTRAINT fk_hpl_back_texture FOREIGN KEY (back_texture_id) REFERENCES textures(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-13_03_create_hpl_stock_pieces',
                'label' => 'CREATE TABLE hpl_stock_pieces',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'hpl_stock_pieces')) return;
                    if (!self::tableExists($pdo, 'hpl_boards')) return;
                    $pdo->exec("
                        CREATE TABLE hpl_stock_pieces (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          board_id INT UNSIGNED NOT NULL,
                          is_accounting TINYINT(1) NOT NULL DEFAULT 1,
                          piece_type ENUM('FULL','OFFCUT') NOT NULL,
                          status ENUM('AVAILABLE','RESERVED','CONSUMED','SCRAP') NOT NULL DEFAULT 'AVAILABLE',
                          width_mm INT NOT NULL,
                          height_mm INT NOT NULL,
                          qty INT NOT NULL DEFAULT 1,
                          location VARCHAR(190) NOT NULL DEFAULT '',
                          notes TEXT NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          area_per_piece_m2 DECIMAL(12,4) AS ((width_mm * height_mm) / 1000000.0) STORED,
                          area_total_m2 DECIMAL(12,4) AS (((width_mm * height_mm) / 1000000.0) * qty) STORED,
                          PRIMARY KEY (id),
                          KEY idx_hpl_stock_board (board_id),
                          KEY idx_hpl_stock_accounting (is_accounting),
                          KEY idx_hpl_stock_status (status),
                          KEY idx_hpl_stock_piece_type (piece_type),
                          KEY idx_hpl_stock_location (location),
                          CONSTRAINT fk_hpl_stock_board FOREIGN KEY (board_id) REFERENCES hpl_boards(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_01_add_hpl_stock_pieces_is_accounting',
                'label' => 'ALTER TABLE hpl_stock_pieces ADD is_accounting',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'hpl_stock_pieces')) return;
                    if (!self::columnExists($pdo, 'hpl_stock_pieces', 'is_accounting')) {
                        $pdo->exec("ALTER TABLE hpl_stock_pieces ADD COLUMN is_accounting TINYINT(1) NOT NULL DEFAULT 1");
                    }
                    // index best-effort
                    try {
                        $pdo->exec("ALTER TABLE hpl_stock_pieces ADD INDEX idx_hpl_stock_accounting (is_accounting)");
                    } catch (\Throwable $e) {
                        // ignore (poate exista deja)
                    }
                },
            ],
            [
                'id' => '2026-01-13_04_add_sale_price',
                'label' => 'ALTER TABLE hpl_boards ADD sale_price',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'hpl_boards')) return;
                    if (self::columnExists($pdo, 'hpl_boards', 'sale_price')) return;
                    $pdo->exec("ALTER TABLE hpl_boards ADD COLUMN sale_price DECIMAL(12,2) NULL");
                },
            ],
            [
                'id' => '2026-01-13_05_create_clients',
                'label' => 'CREATE TABLE clients',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'clients')) return;
                    $pdo->exec("
                        CREATE TABLE clients (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          type ENUM('PERSOANA_FIZICA','FIRMA') NOT NULL DEFAULT 'PERSOANA_FIZICA',
                          name VARCHAR(190) NOT NULL,
                          cui VARCHAR(32) NULL,
                          contact_person VARCHAR(190) NULL,
                          phone VARCHAR(64) NULL,
                          email VARCHAR(190) NULL,
                          address TEXT NULL,
                          notes TEXT NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          KEY idx_clients_type (type),
                          KEY idx_clients_name (name),
                          KEY idx_clients_email (email)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-13_06_create_projects',
                'label' => 'CREATE TABLE projects',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'projects')) return;
                    if (!self::tableExists($pdo, 'clients')) return;
                    if (!self::tableExists($pdo, 'users')) return;
                    $pdo->exec("
                        CREATE TABLE projects (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          code VARCHAR(64) NOT NULL,
                          name VARCHAR(190) NOT NULL,
                          client_id INT UNSIGNED NULL,
                          status ENUM('NOU','IN_LUCRU','IN_ASTEPTARE','FINALIZAT','ARHIVAT') NOT NULL DEFAULT 'NOU',
                          start_date DATE NULL,
                          due_date DATE NULL,
                          notes TEXT NULL,
                          created_by INT UNSIGNED NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          UNIQUE KEY uq_projects_code (code),
                          KEY idx_projects_client (client_id),
                          KEY idx_projects_status (status),
                          KEY idx_projects_created (created_at),
                          CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id),
                          CONSTRAINT fk_projects_user FOREIGN KEY (created_by) REFERENCES users(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_02_create_client_groups',
                'label' => 'CREATE TABLE client_groups',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'client_groups')) return;
                    $pdo->exec("
                        CREATE TABLE client_groups (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          name VARCHAR(190) NOT NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          UNIQUE KEY uq_client_groups_name (name),
                          KEY idx_client_groups_name (name)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_03_add_clients_group_id',
                'label' => 'ALTER TABLE clients ADD client_group_id',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'clients')) return;
                    if (!self::tableExists($pdo, 'client_groups')) return;
                    if (!self::columnExists($pdo, 'clients', 'client_group_id')) {
                        $pdo->exec("ALTER TABLE clients ADD COLUMN client_group_id INT UNSIGNED NULL");
                    }
                    // index + FK best-effort
                    try { $pdo->exec("ALTER TABLE clients ADD INDEX idx_clients_group (client_group_id)"); } catch (\Throwable $e) {}
                    try { $pdo->exec("ALTER TABLE clients ADD CONSTRAINT fk_clients_group FOREIGN KEY (client_group_id) REFERENCES client_groups(id)"); } catch (\Throwable $e) {}
                },
            ],
            [
                'id' => '2026-01-14_04_create_client_addresses',
                'label' => 'CREATE TABLE client_addresses',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'client_addresses')) return;
                    if (!self::tableExists($pdo, 'clients')) return;
                    $pdo->exec("
                        CREATE TABLE client_addresses (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          client_id INT UNSIGNED NOT NULL,
                          label VARCHAR(190) NULL,
                          address TEXT NOT NULL,
                          notes TEXT NULL,
                          is_default TINYINT(1) NOT NULL DEFAULT 0,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          KEY idx_client_addr_client (client_id),
                          KEY idx_client_addr_default (client_id, is_default),
                          CONSTRAINT fk_client_addr_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
        ];
    }

    private static function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS " . self::MIGRATIONS_TABLE . " (
              id VARCHAR(64) NOT NULL,
              applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private static function isApplied(PDO $pdo, string $id): bool
    {
        $st = $pdo->prepare("SELECT id FROM " . self::MIGRATIONS_TABLE . " WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return (bool)$st->fetch();
    }

    private static function markApplied(PDO $pdo, string $id): void
    {
        $st = $pdo->prepare("INSERT INTO " . self::MIGRATIONS_TABLE . " (id) VALUES (?)");
        $st->execute([$id]);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        // Compat hosting: unele conturi nu au acces la information_schema.
        // Folosim SHOW TABLES (mai permisiv) și doar ca fallback information_schema.
        try {
            $st = $pdo->prepare("SHOW TABLES LIKE ?");
            $st->execute([$table]);
            return (bool)$st->fetch();
        } catch (\Throwable $e) {
            // fallback best-effort
            try {
                $st = $pdo->prepare("
                    SELECT COUNT(*) AS c
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                ");
                $st->execute([$table]);
                $r = $st->fetch();
                return ((int)($r['c'] ?? 0)) > 0;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $col): bool
    {
        // Compat hosting: SHOW COLUMNS nu depinde de information_schema.
        try {
            $tbl = str_replace('`', '``', $table);
            $st = $pdo->prepare("SHOW COLUMNS FROM `{$tbl}` LIKE ?");
            $st->execute([$col]);
            return (bool)$st->fetch();
        } catch (\Throwable $e) {
            // fallback best-effort
            try {
                $st = $pdo->prepare("
                    SELECT COUNT(*) AS c
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND COLUMN_NAME = ?
                ");
                $st->execute([$table, $col]);
                $r = $st->fetch();
                return ((int)($r['c'] ?? 0)) > 0;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }
}

