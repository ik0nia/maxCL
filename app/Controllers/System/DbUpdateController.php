<?php
declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use PDO;

final class DbUpdateController
{
    private const ALLOWED_EMAIL = 'sacodrut@ikonia.ro';

    private static function requireAllowed(): void
    {
        $u = Auth::user();
        if (!$u) {
            Session::flash('toast_error', 'Te rugăm să te autentifici.');
            Response::redirect('/login');
        }
        if (strtolower((string)($u['email'] ?? '')) !== self::ALLOWED_EMAIL) {
            http_response_code(403);
            echo View::render('errors/403', ['title' => 'Acces interzis']);
            exit;
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare("
            SELECT COUNT(*) AS c
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $st->execute([$table]);
        $r = $st->fetch();
        return ((int)($r['c'] ?? 0)) > 0;
    }

    private static function columnExists(PDO $pdo, string $table, string $col): bool
    {
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
    }

    /**
     * @return array<int, array{key:string, label:string, needed:bool}>
     */
    private static function plan(PDO $pdo): array
    {
        $needs = [];

        $needs[] = [
            'key' => 'textures_table',
            'label' => 'Tabelă `textures`',
            'needed' => !self::tableExists($pdo, 'textures'),
        ];

        $needs[] = [
            'key' => 'hpl_boards_table',
            'label' => 'Tabelă `hpl_boards`',
            'needed' => !self::tableExists($pdo, 'hpl_boards'),
        ];

        $needs[] = [
            'key' => 'hpl_stock_pieces_table',
            'label' => 'Tabelă `hpl_stock_pieces`',
            'needed' => !self::tableExists($pdo, 'hpl_stock_pieces'),
        ];

        $needs[] = [
            'key' => 'clients_table',
            'label' => 'Tabelă `clients`',
            'needed' => !self::tableExists($pdo, 'clients'),
        ];

        $needs[] = [
            'key' => 'projects_table',
            'label' => 'Tabelă `projects`',
            'needed' => !self::tableExists($pdo, 'projects'),
        ];

        $needs[] = [
            'key' => 'magazie_items_table',
            'label' => 'Tabelă `magazie_items` (Magazie)',
            'needed' => !self::tableExists($pdo, 'magazie_items'),
        ];

        $needs[] = [
            'key' => 'magazie_movements_table',
            'label' => 'Tabelă `magazie_movements` (istoric Magazie)',
            'needed' => !self::tableExists($pdo, 'magazie_movements'),
        ];

        $needs[] = [
            'key' => 'hpl_boards_sale_price',
            'label' => 'Coloană `hpl_boards.sale_price`',
            'needed' => self::tableExists($pdo, 'hpl_boards') && !self::columnExists($pdo, 'hpl_boards', 'sale_price'),
        ];

        return $needs;
    }

    /**
     * @return array{applied: array<int,string>, skipped: array<int,string>, errors: array<int,string>}
     */
    private static function run(PDO $pdo): array
    {
        $applied = [];
        $skipped = [];
        $errors = [];

        // 1) textures
        if (!self::tableExists($pdo, 'textures')) {
            try {
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
                $applied[] = 'CREATE TABLE textures';
            } catch (\Throwable $e) {
                $errors[] = 'textures: ' . $e->getMessage();
            }
        } else {
            $skipped[] = 'textures (există deja)';
        }

        // 2) hpl_boards
        if (!self::tableExists($pdo, 'hpl_boards')) {
            try {
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
                $applied[] = 'CREATE TABLE hpl_boards';
            } catch (\Throwable $e) {
                $errors[] = 'hpl_boards: ' . $e->getMessage();
            }
        } else {
            $skipped[] = 'hpl_boards (există deja)';
        }

        // 3) hpl_stock_pieces
        if (!self::tableExists($pdo, 'hpl_stock_pieces')) {
            try {
                $pdo->exec("
                    CREATE TABLE hpl_stock_pieces (
                      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      board_id INT UNSIGNED NOT NULL,
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
                      KEY idx_hpl_stock_status (status),
                      KEY idx_hpl_stock_piece_type (piece_type),
                      KEY idx_hpl_stock_location (location),
                      CONSTRAINT fk_hpl_stock_board FOREIGN KEY (board_id) REFERENCES hpl_boards(id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $applied[] = 'CREATE TABLE hpl_stock_pieces';
            } catch (\Throwable $e) {
                $errors[] = 'hpl_stock_pieces: ' . $e->getMessage();
            }
        } else {
            $skipped[] = 'hpl_stock_pieces (există deja)';
        }

        // 4) clients
        if (!self::tableExists($pdo, 'clients')) {
            try {
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
                $applied[] = 'CREATE TABLE clients';
            } catch (\Throwable $e) {
                $errors[] = 'clients: ' . $e->getMessage();
            }
        } else {
            $skipped[] = 'clients (există deja)';
        }

        // 5) projects
        if (!self::tableExists($pdo, 'projects')) {
            try {
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
                $applied[] = 'CREATE TABLE projects';
            } catch (\Throwable $e) {
                $errors[] = 'projects: ' . $e->getMessage();
            }
        } else {
            $skipped[] = 'projects (există deja)';
        }

        // 5b) magazie_items
        if (!self::tableExists($pdo, 'magazie_items')) {
            try {
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
                $applied[] = 'CREATE TABLE magazie_items';
            } catch (\Throwable $e) {
                $errors[] = 'magazie_items: ' . $e->getMessage();
            }
        } else {
            $skipped[] = 'magazie_items (există deja)';
        }

        // 5c) magazie_movements
        if (!self::tableExists($pdo, 'magazie_movements')) {
            try {
                // depinde de magazie_items; FK-urile către projects/users sunt best-effort
                if (!self::tableExists($pdo, 'magazie_items')) {
                    throw new \RuntimeException('lipsește magazie_items');
                }
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
                // best-effort FK către projects/users
                try { if (self::tableExists($pdo, 'projects')) $pdo->exec("ALTER TABLE magazie_movements ADD CONSTRAINT fk_mag_mov_project FOREIGN KEY (project_id) REFERENCES projects(id)"); } catch (\Throwable $e) {}
                try { if (self::tableExists($pdo, 'users')) $pdo->exec("ALTER TABLE magazie_movements ADD CONSTRAINT fk_mag_mov_user FOREIGN KEY (created_by) REFERENCES users(id)"); } catch (\Throwable $e) {}
                $applied[] = 'CREATE TABLE magazie_movements';
            } catch (\Throwable $e) {
                $errors[] = 'magazie_movements: ' . $e->getMessage();
            }
        } else {
            $skipped[] = 'magazie_movements (există deja)';
        }

        // 6) sale_price column for older installs
        if (self::tableExists($pdo, 'hpl_boards') && !self::columnExists($pdo, 'hpl_boards', 'sale_price')) {
            try {
                $pdo->exec("ALTER TABLE hpl_boards ADD COLUMN sale_price DECIMAL(12,2) NULL");
                $applied[] = 'ALTER TABLE hpl_boards ADD sale_price';
            } catch (\Throwable $e) {
                $errors[] = 'hpl_boards.sale_price: ' . $e->getMessage();
            }
        } else {
            $skipped[] = 'hpl_boards.sale_price (există deja)';
        }

        return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors];
    }

    public static function index(): void
    {
        self::requireAllowed();
        /** @var PDO $pdo */
        $pdo = DB::pdo();
        $plan = self::plan($pdo);
        echo View::render('system/db_update', [
            'title' => 'Update DB',
            'plan' => $plan,
        ]);
    }

    public static function runNow(): void
    {
        self::requireAllowed();
        Csrf::verify($_POST['_csrf'] ?? null);
        /** @var PDO $pdo */
        $pdo = DB::pdo();

        $res = self::run($pdo);
        Audit::log('DB_UPDATE_RUN', 'system', null, null, null, [
            'message' => 'A rulat update DB (migrări).',
            'applied' => $res['applied'],
            'skipped' => $res['skipped'],
            'errors' => $res['errors'],
        ]);

        if ($res['errors']) {
            Session::flash('toast_error', 'Update DB finalizat cu erori. Vezi lista de erori.');
        } else {
            Session::flash('toast_success', 'Update DB finalizat.');
        }
        // păstrăm rezultatele în sesiune ca să le afișăm după redirect
        Session::flash('db_update_result', json_encode($res, JSON_UNESCAPED_UNICODE));
        Response::redirect('/system/db-update');
    }
}

