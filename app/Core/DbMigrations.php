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
            [
                'id' => '2026-01-14_05_create_magazie_items',
                'label' => 'CREATE TABLE magazie_items',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'magazie_items')) return;
                    $pdo->exec("
                        CREATE TABLE magazie_items (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          winmentor_code VARCHAR(64) NOT NULL,
                          name VARCHAR(190) NOT NULL,
                          unit_price DECIMAL(12,2) NULL,
                          stock_qty INT NOT NULL DEFAULT 0,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          UNIQUE KEY uq_magazie_items_winmentor (winmentor_code),
                          KEY idx_magazie_items_name (name),
                          KEY idx_magazie_items_stock (stock_qty)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_06_create_magazie_movements',
                'label' => 'CREATE TABLE magazie_movements',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'magazie_movements')) return;
                    if (!self::tableExists($pdo, 'magazie_items')) return;
                    // projects/users pot lipsi pe instalări vechi; păstrăm FK-urile best-effort.
                    $pdo->exec("
                        CREATE TABLE magazie_movements (
                          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                          item_id INT UNSIGNED NOT NULL,
                          direction ENUM('IN','OUT','ADJUST') NOT NULL,
                          qty INT NOT NULL,
                          unit_price DECIMAL(12,2) NULL,
                          project_id INT UNSIGNED NULL,
                          project_code VARCHAR(64) NULL,
                          note VARCHAR(255) NULL,
                          created_by INT UNSIGNED NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          KEY idx_mag_mov_item (item_id),
                          KEY idx_mag_mov_dir (direction),
                          KEY idx_mag_mov_created (created_at),
                          KEY idx_mag_mov_project (project_id),
                          KEY idx_mag_mov_project_code (project_code),
                          CONSTRAINT fk_mag_mov_item FOREIGN KEY (item_id) REFERENCES magazie_items(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");

                    // FK-uri best-effort
                    try { if (self::tableExists($pdo, 'projects')) $pdo->exec("ALTER TABLE magazie_movements ADD CONSTRAINT fk_mag_mov_project FOREIGN KEY (project_id) REFERENCES projects(id)"); } catch (\Throwable $e) {}
                    try { if (self::tableExists($pdo, 'users')) $pdo->exec("ALTER TABLE magazie_movements ADD CONSTRAINT fk_mag_mov_user FOREIGN KEY (created_by) REFERENCES users(id)"); } catch (\Throwable $e) {}
                },
            ],
            [
                'id' => '2026-01-14_07_extend_projects_for_production',
                'label' => 'ALTER TABLE projects add production fields',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'projects')) return;

                    // câmpuri generale proiect producție
                    if (!self::columnExists($pdo, 'projects', 'description')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN description TEXT NULL");
                    }
                    if (!self::columnExists($pdo, 'projects', 'category')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN category VARCHAR(190) NULL");
                    }
                    if (!self::columnExists($pdo, 'projects', 'priority')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN priority INT NOT NULL DEFAULT 0");
                    }
                    if (!self::columnExists($pdo, 'projects', 'completed_at')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN completed_at DATETIME NULL");
                    }
                    if (!self::columnExists($pdo, 'projects', 'cancelled_at')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN cancelled_at DATETIME NULL");
                    }
                    if (!self::columnExists($pdo, 'projects', 'technical_notes')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN technical_notes TEXT NULL");
                    }
                    if (!self::columnExists($pdo, 'projects', 'tags')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN tags TEXT NULL");
                    }
                    if (!self::columnExists($pdo, 'projects', 'client_group_id')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN client_group_id INT UNSIGNED NULL");
                    }
                    if (!self::columnExists($pdo, 'projects', 'allocation_mode')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN allocation_mode ENUM('by_qty','by_area','manual') NOT NULL DEFAULT 'by_area'");
                    }
                    if (!self::columnExists($pdo, 'projects', 'allocations_locked')) {
                        $pdo->exec("ALTER TABLE projects ADD COLUMN allocations_locked TINYINT(1) NOT NULL DEFAULT 0");
                    }

                    // index + FK best-effort
                    try { $pdo->exec("ALTER TABLE projects ADD INDEX idx_projects_group (client_group_id)"); } catch (\Throwable $e) {}
                    try { if (self::tableExists($pdo, 'client_groups')) $pdo->exec("ALTER TABLE projects ADD CONSTRAINT fk_projects_group FOREIGN KEY (client_group_id) REFERENCES client_groups(id)"); } catch (\Throwable $e) {}

                    // Extinde ENUM status (best-effort) păstrând valorile vechi.
                    try {
                        $pdo->exec("
                          ALTER TABLE projects
                          MODIFY status ENUM(
                            'DRAFT','CONFIRMAT','IN_PRODUCTIE','IN_ASTEPTARE','FINALIZAT_TEHNIC',
                            'LIVRAT_PARTIAL','LIVRAT_COMPLET','ANULAT',
                            'NOU','IN_LUCRU','FINALIZAT','ARHIVAT'
                          ) NOT NULL DEFAULT 'DRAFT'
                        ");
                    } catch (\Throwable $e) {
                        // ignore (hosting / permisiuni / DB vechi)
                    }
                },
            ],
            [
                'id' => '2026-01-14_08_create_products',
                'label' => 'CREATE TABLE products',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'products')) return;
                    $pdo->exec("
                        CREATE TABLE products (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          code VARCHAR(64) NULL,
                          name VARCHAR(190) NOT NULL,
                          width_mm INT NULL,
                          height_mm INT NULL,
                          notes TEXT NULL,
                          cnc_settings_json JSON NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          UNIQUE KEY uq_products_code (code),
                          KEY idx_products_name (name)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_09_create_project_products',
                'label' => 'CREATE TABLE project_products',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'project_products')) return;
                    if (!self::tableExists($pdo, 'projects')) return;
                    if (!self::tableExists($pdo, 'products')) return;
                    $pdo->exec("
                        CREATE TABLE project_products (
                          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                          project_id INT UNSIGNED NOT NULL,
                          product_id INT UNSIGNED NOT NULL,
                          qty DECIMAL(12,2) NOT NULL DEFAULT 1,
                          unit VARCHAR(32) NOT NULL DEFAULT 'buc',
                          production_status ENUM('CREAT','PROIECTARE','CNC','MONTAJ','GATA_DE_LIVRARE','AVIZAT','LIVRAT') NOT NULL DEFAULT 'CREAT',
                          delivered_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
                          notes TEXT NULL,
                          cnc_override_json JSON NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          UNIQUE KEY uq_proj_prod (project_id, product_id),
                          KEY idx_proj_prod_project (project_id),
                          KEY idx_proj_prod_status (production_status),
                          CONSTRAINT fk_proj_prod_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                          CONSTRAINT fk_proj_prod_product FOREIGN KEY (product_id) REFERENCES products(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_10_create_labels',
                'label' => 'CREATE TABLE labels + entity_labels',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'labels')) {
                        $pdo->exec("
                            CREATE TABLE labels (
                              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                              name VARCHAR(64) NOT NULL,
                              color VARCHAR(32) NULL,
                              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                              PRIMARY KEY (id),
                              UNIQUE KEY uq_labels_name (name)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                    }
                    if (!self::tableExists($pdo, 'entity_labels')) {
                        $pdo->exec("
                            CREATE TABLE entity_labels (
                              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                              entity_type VARCHAR(64) NOT NULL,
                              entity_id BIGINT UNSIGNED NOT NULL,
                              label_id INT UNSIGNED NOT NULL,
                              source ENUM('DIRECT','INHERITED') NOT NULL DEFAULT 'DIRECT',
                              created_by INT UNSIGNED NULL,
                              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                              PRIMARY KEY (id),
                              UNIQUE KEY uq_entity_label (entity_type, entity_id, label_id, source),
                              KEY idx_entity_labels_entity (entity_type, entity_id),
                              KEY idx_entity_labels_label (label_id),
                              CONSTRAINT fk_entity_labels_label FOREIGN KEY (label_id) REFERENCES labels(id),
                              CONSTRAINT fk_entity_labels_user FOREIGN KEY (created_by) REFERENCES users(id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                    }
                },
            ],
            [
                'id' => '2026-01-14_11_create_project_deliveries',
                'label' => 'CREATE TABLE project_deliveries + items',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'project_deliveries')) {
                        if (!self::tableExists($pdo, 'projects')) return;
                        $pdo->exec("
                            CREATE TABLE project_deliveries (
                              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                              project_id INT UNSIGNED NOT NULL,
                              delivery_date DATE NOT NULL,
                              note VARCHAR(255) NULL,
                              created_by INT UNSIGNED NULL,
                              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                              PRIMARY KEY (id),
                              KEY idx_deliv_project (project_id),
                              KEY idx_deliv_date (delivery_date),
                              CONSTRAINT fk_deliv_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                              CONSTRAINT fk_deliv_user FOREIGN KEY (created_by) REFERENCES users(id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                    }
                    if (!self::tableExists($pdo, 'project_delivery_items')) {
                        if (!self::tableExists($pdo, 'project_deliveries')) return;
                        if (!self::tableExists($pdo, 'project_products')) return;
                        $pdo->exec("
                            CREATE TABLE project_delivery_items (
                              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                              delivery_id BIGINT UNSIGNED NOT NULL,
                              project_product_id BIGINT UNSIGNED NOT NULL,
                              qty DECIMAL(12,2) NOT NULL,
                              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                              PRIMARY KEY (id),
                              UNIQUE KEY uq_delivery_item (delivery_id, project_product_id),
                              KEY idx_delivery_items_delivery (delivery_id),
                              KEY idx_delivery_items_pp (project_product_id),
                              CONSTRAINT fk_delivery_items_delivery FOREIGN KEY (delivery_id) REFERENCES project_deliveries(id) ON DELETE CASCADE,
                              CONSTRAINT fk_delivery_items_pp FOREIGN KEY (project_product_id) REFERENCES project_products(id) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                    }
                },
            ],
            [
                'id' => '2026-01-14_12_create_entity_files',
                'label' => 'CREATE TABLE entity_files',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'entity_files')) return;
                    $pdo->exec("
                        CREATE TABLE entity_files (
                          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                          entity_type VARCHAR(64) NOT NULL,
                          entity_id BIGINT UNSIGNED NOT NULL,
                          category VARCHAR(64) NULL,
                          original_name VARCHAR(255) NOT NULL,
                          stored_name VARCHAR(255) NOT NULL,
                          mime VARCHAR(128) NULL,
                          size_bytes BIGINT UNSIGNED NULL,
                          uploaded_by INT UNSIGNED NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          KEY idx_entity_files_entity (entity_type, entity_id),
                          KEY idx_entity_files_created (created_at),
                          CONSTRAINT fk_entity_files_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_13_create_project_work_logs',
                'label' => 'CREATE TABLE project_work_logs',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'project_work_logs')) return;
                    if (!self::tableExists($pdo, 'projects')) return;
                    $pdo->exec("
                        CREATE TABLE project_work_logs (
                          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                          project_id INT UNSIGNED NOT NULL,
                          project_product_id BIGINT UNSIGNED NULL,
                          work_type ENUM('CNC','ATELIER') NOT NULL,
                          hours_estimated DECIMAL(10,2) NULL,
                          hours_actual DECIMAL(10,2) NULL,
                          cost_per_hour DECIMAL(12,2) NULL,
                          note VARCHAR(255) NULL,
                          created_by INT UNSIGNED NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          KEY idx_work_project (project_id),
                          KEY idx_work_pp (project_product_id),
                          KEY idx_work_type (work_type),
                          CONSTRAINT fk_work_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                          CONSTRAINT fk_work_pp FOREIGN KEY (project_product_id) REFERENCES project_products(id) ON DELETE SET NULL,
                          CONSTRAINT fk_work_user FOREIGN KEY (created_by) REFERENCES users(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_14_create_project_magazie_consumptions',
                'label' => 'CREATE TABLE project_magazie_consumptions',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'project_magazie_consumptions')) return;
                    if (!self::tableExists($pdo, 'projects')) return;
                    if (!self::tableExists($pdo, 'magazie_items')) return;
                    $pdo->exec("
                        CREATE TABLE project_magazie_consumptions (
                          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                          project_id INT UNSIGNED NOT NULL,
                          project_product_id BIGINT UNSIGNED NULL,
                          item_id INT UNSIGNED NOT NULL,
                          qty DECIMAL(12,3) NOT NULL,
                          unit VARCHAR(32) NOT NULL DEFAULT 'buc',
                          mode ENUM('RESERVED','CONSUMED') NOT NULL DEFAULT 'CONSUMED',
                          note VARCHAR(255) NULL,
                          created_by INT UNSIGNED NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          KEY idx_pmc_project (project_id),
                          KEY idx_pmc_pp (project_product_id),
                          KEY idx_pmc_item (item_id),
                          KEY idx_pmc_mode (mode),
                          CONSTRAINT fk_pmc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                          CONSTRAINT fk_pmc_pp FOREIGN KEY (project_product_id) REFERENCES project_products(id) ON DELETE SET NULL,
                          CONSTRAINT fk_pmc_item FOREIGN KEY (item_id) REFERENCES magazie_items(id),
                          CONSTRAINT fk_pmc_user FOREIGN KEY (created_by) REFERENCES users(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-14_15_create_project_hpl_consumptions',
                'label' => 'CREATE TABLE project_hpl_consumptions + allocations',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'project_hpl_consumptions')) {
                        if (!self::tableExists($pdo, 'projects')) return;
                        if (!self::tableExists($pdo, 'hpl_boards')) return;
                        $pdo->exec("
                            CREATE TABLE project_hpl_consumptions (
                              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                              project_id INT UNSIGNED NOT NULL,
                              board_id INT UNSIGNED NOT NULL,
                              qty_boards INT NOT NULL DEFAULT 0,
                              qty_m2 DECIMAL(12,4) NOT NULL,
                              mode ENUM('RESERVED','CONSUMED') NOT NULL DEFAULT 'RESERVED',
                              note VARCHAR(255) NULL,
                              created_by INT UNSIGNED NULL,
                              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                              PRIMARY KEY (id),
                              KEY idx_phc_project (project_id),
                              KEY idx_phc_board (board_id),
                              KEY idx_phc_mode (mode),
                              CONSTRAINT fk_phc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                              CONSTRAINT fk_phc_board FOREIGN KEY (board_id) REFERENCES hpl_boards(id),
                              CONSTRAINT fk_phc_user FOREIGN KEY (created_by) REFERENCES users(id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                    }
                    if (!self::tableExists($pdo, 'project_hpl_allocations')) {
                        if (!self::tableExists($pdo, 'project_hpl_consumptions')) return;
                        if (!self::tableExists($pdo, 'project_products')) return;
                        $pdo->exec("
                            CREATE TABLE project_hpl_allocations (
                              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                              consumption_id BIGINT UNSIGNED NOT NULL,
                              project_product_id BIGINT UNSIGNED NOT NULL,
                              qty_m2 DECIMAL(12,4) NOT NULL,
                              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                              PRIMARY KEY (id),
                              UNIQUE KEY uq_ph_alloc (consumption_id, project_product_id),
                              KEY idx_ph_alloc_cons (consumption_id),
                              KEY idx_ph_alloc_pp (project_product_id),
                              CONSTRAINT fk_ph_alloc_cons FOREIGN KEY (consumption_id) REFERENCES project_hpl_consumptions(id) ON DELETE CASCADE,
                              CONSTRAINT fk_ph_alloc_pp FOREIGN KEY (project_product_id) REFERENCES project_products(id) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                    }
                },
            ],
            [
                'id' => '2026-01-14_18_project_hpl_qty_boards',
                'label' => 'ALTER project_hpl_consumptions ADD qty_boards',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'project_hpl_consumptions')) return;
                    if (!self::columnExists($pdo, 'project_hpl_consumptions', 'qty_boards')) {
                        try {
                            $pdo->exec("ALTER TABLE project_hpl_consumptions ADD COLUMN qty_boards INT NOT NULL DEFAULT 0 AFTER board_id");
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                },
            ],
            [
                'id' => '2026-01-14_16_magazie_decimal_qty',
                'label' => 'ALTER magazie qty columns to DECIMAL',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'magazie_items') && self::columnExists($pdo, 'magazie_items', 'stock_qty')) {
                        try {
                            $pdo->exec("ALTER TABLE magazie_items MODIFY stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0");
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                    if (self::tableExists($pdo, 'magazie_movements') && self::columnExists($pdo, 'magazie_movements', 'qty')) {
                        try {
                            $pdo->exec("ALTER TABLE magazie_movements MODIFY qty DECIMAL(12,3) NOT NULL");
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                },
            ],
            [
                'id' => '2026-01-14_17_magazie_item_unit',
                'label' => 'ALTER magazie_items ADD unit',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'magazie_items')) return;
                    if (!self::columnExists($pdo, 'magazie_items', 'unit')) {
                        try {
                            $pdo->exec("ALTER TABLE magazie_items ADD COLUMN unit VARCHAR(16) NOT NULL DEFAULT 'buc' AFTER name");
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                },
            ],
            [
                'id' => '2026-01-15_01_app_settings',
                'label' => 'CREATE TABLE app_settings',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'app_settings')) return;
                    $pdo->exec("
                        CREATE TABLE app_settings (
                          `key` VARCHAR(64) NOT NULL,
                          value VARCHAR(255) NULL,
                          updated_by INT UNSIGNED NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (`key`),
                          KEY idx_app_settings_updated (updated_at),
                          CONSTRAINT fk_app_settings_user FOREIGN KEY (updated_by) REFERENCES users(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-15_02_project_products_m2',
                'label' => 'ALTER project_products ADD m2_per_unit',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'project_products')) return;
                    if (!self::columnExists($pdo, 'project_products', 'm2_per_unit')) {
                        try {
                            $pdo->exec("ALTER TABLE project_products ADD COLUMN m2_per_unit DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER unit");
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                },
            ],
            [
                'id' => '2026-01-15_03_entity_comments',
                'label' => 'CREATE TABLE entity_comments',
                'fn' => function (PDO $pdo): void {
                    if (self::tableExists($pdo, 'entity_comments')) return;
                    if (!self::tableExists($pdo, 'users')) return;
                    $pdo->exec("
                        CREATE TABLE entity_comments (
                          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                          entity_type VARCHAR(64) NOT NULL,
                          entity_id INT UNSIGNED NOT NULL,
                          comment TEXT NOT NULL,
                          user_id INT UNSIGNED NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          PRIMARY KEY (id),
                          KEY idx_comments_entity (entity_type, entity_id),
                          KEY idx_comments_created (created_at),
                          CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                },
            ],
            [
                'id' => '2026-01-15_04_project_products_statuses_v2',
                'label' => 'ALTER project_products.production_status (v2) + data migrate',
                'fn' => function (PDO $pdo): void {
                    if (!self::tableExists($pdo, 'project_products')) return;
                    if (!self::columnExists($pdo, 'project_products', 'production_status')) return;

                    // 1) Extinde enum-ul (include vechile valori) ca să putem migra datele.
                    try {
                        $pdo->exec("
                            ALTER TABLE project_products
                            MODIFY production_status ENUM(
                              'DE_PREGATIT','CNC','ATELIER','FINISARE','GATA','LIVRAT_PARTIAL','LIVRAT_COMPLET','REBUT',
                              'CREAT','PROIECTARE','MONTAJ','GATA_DE_LIVRARE','AVIZAT','LIVRAT'
                            ) NOT NULL DEFAULT 'CREAT'
                        ");
                    } catch (\Throwable $e) {
                        // ignore (poate e deja extins)
                    }

                    // 2) Migrare best-effort a valorilor vechi către noile statusuri.
                    try {
                        $pdo->exec("
                            UPDATE project_products
                            SET production_status = CASE production_status
                              WHEN 'DE_PREGATIT' THEN 'CREAT'
                              WHEN 'ATELIER' THEN 'MONTAJ'
                              WHEN 'FINISARE' THEN 'MONTAJ'
                              WHEN 'GATA' THEN 'GATA_DE_LIVRARE'
                              WHEN 'LIVRAT_PARTIAL' THEN 'LIVRAT'
                              WHEN 'LIVRAT_COMPLET' THEN 'LIVRAT'
                              WHEN 'REBUT' THEN 'CREAT'
                              ELSE production_status
                            END
                        ");
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    // 3) Strânge enum-ul la lista finală.
                    try {
                        $pdo->exec("
                            ALTER TABLE project_products
                            MODIFY production_status ENUM('CREAT','PROIECTARE','CNC','MONTAJ','GATA_DE_LIVRARE','AVIZAT','LIVRAT')
                            NOT NULL DEFAULT 'CREAT'
                        ");
                    } catch (\Throwable $e) {
                        // ignore
                    }
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

