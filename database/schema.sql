-- HPL Manager (atelier producție) - schema MVP
-- MySQL 8.0+ / 8.4

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(190) NOT NULL,
  role ENUM('ADMIN','GESTIONAR','OPERATOR','VIZUALIZARE') NOT NULL DEFAULT 'VIZUALIZARE',
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finishes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  color_name VARCHAR(190) NOT NULL,
  color_code VARCHAR(64) NULL,
  -- NOTĂ: câmpurile de textură sunt păstrate pentru compatibilitate,
  -- dar în noua structură „Tip culoare” NU mai gestionează textura.
  texture_name VARCHAR(190) NOT NULL,
  texture_code VARCHAR(64) NULL,
  thumb_path VARCHAR(255) NOT NULL,
  image_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_finishes_code (code),
  KEY idx_finishes_color (color_name),
  KEY idx_finishes_texture (texture_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS textures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NULL,
  name VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_textures_code (code),
  KEY idx_textures_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hpl_boards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  brand VARCHAR(190) NOT NULL,
  thickness_mm INT NOT NULL,
  std_width_mm INT NOT NULL,
  std_height_mm INT NOT NULL,
  sale_price DECIMAL(12,2) NULL,
  sale_price_per_m2 DECIMAL(12,2) AS (
    CASE
      WHEN sale_price IS NULL OR std_width_mm <= 0 OR std_height_mm <= 0 THEN NULL
      ELSE CAST(ROUND((sale_price / ((std_width_mm * std_height_mm) / 1000000.0)), 2) AS DECIMAL(12,2))
    END
  ) STORED,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrare idempotentă (pentru instalări existente)
ALTER TABLE hpl_boards
  ADD COLUMN IF NOT EXISTS sale_price DECIMAL(12,2) NULL;

ALTER TABLE hpl_boards
  ADD COLUMN IF NOT EXISTS sale_price_per_m2 DECIMAL(12,2) AS (
    CASE
      WHEN sale_price IS NULL OR std_width_mm <= 0 OR std_height_mm <= 0 THEN NULL
      ELSE CAST(ROUND((sale_price / ((std_width_mm * std_height_mm) / 1000000.0)), 2) AS DECIMAL(12,2))
    END
  ) STORED;

CREATE TABLE IF NOT EXISTS hpl_stock_pieces (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id INT UNSIGNED NOT NULL,
  -- 1 = intră în contabilitate / totaluri stoc; 0 = piese interne (nestocabile), nu intră în totaluri
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Best-effort: adaugă coloana dacă lipsește (MySQL 8+).
ALTER TABLE hpl_stock_pieces
  ADD COLUMN IF NOT EXISTS is_accounting TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS materials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  brand VARCHAR(190) NOT NULL,
  thickness_mm INT NOT NULL,
  notes TEXT NULL,
  track_stock TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_materials_code (code),
  KEY idx_materials_brand (brand),
  KEY idx_materials_track (track_stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS material_variants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  material_id INT UNSIGNED NOT NULL,
  finish_face_id INT UNSIGNED NOT NULL,
  finish_back_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_variant_combo (material_id, finish_face_id, finish_back_id),
  KEY idx_variant_material (material_id),
  KEY idx_variant_face (finish_face_id),
  KEY idx_variant_back (finish_back_id),
  CONSTRAINT fk_variant_material FOREIGN KEY (material_id) REFERENCES materials(id),
  CONSTRAINT fk_variant_face FOREIGN KEY (finish_face_id) REFERENCES finishes(id),
  CONSTRAINT fk_variant_back FOREIGN KEY (finish_back_id) REFERENCES finishes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_pieces (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id INT UNSIGNED NOT NULL,
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
  KEY idx_stock_variant (variant_id),
  KEY idx_stock_status (status),
  KEY idx_stock_piece_type (piece_type),
  KEY idx_stock_location (location),
  CONSTRAINT fk_stock_variant FOREIGN KEY (variant_id) REFERENCES material_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_adjustments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  stock_piece_id INT UNSIGNED NOT NULL,
  delta_qty INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stock_adj_piece (stock_piece_id),
  KEY idx_stock_adj_created (created_at),
  CONSTRAINT fk_stock_adj_piece FOREIGN KEY (stock_piece_id) REFERENCES stock_pieces(id),
  CONSTRAINT fk_stock_adj_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type ENUM('PERSOANA_FIZICA','FIRMA') NOT NULL DEFAULT 'PERSOANA_FIZICA',
  name VARCHAR(190) NOT NULL,
  client_group_id INT UNSIGNED NULL,
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
  KEY idx_clients_email (email),
  KEY idx_clients_group (client_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_client_groups_name (name),
  KEY idx_client_groups_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS client_group_id INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS client_addresses (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Magazie (accesorii)
CREATE TABLE IF NOT EXISTS magazie_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS magazie_movements (
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
  CONSTRAINT fk_mag_mov_item FOREIGN KEY (item_id) REFERENCES magazie_items(id),
  CONSTRAINT fk_mag_mov_project FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT fk_mag_mov_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_stock_inputs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  stock_piece_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  qty_taken INT NOT NULL,
  width_mm INT NOT NULL,
  height_mm INT NOT NULL,
  piece_type ENUM('FULL','OFFCUT') NOT NULL,
  location VARCHAR(190) NOT NULL DEFAULT '',
  area_taken_m2 DECIMAL(12,4) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proj_in_project (project_id),
  KEY idx_proj_in_piece (stock_piece_id),
  CONSTRAINT fk_proj_in_project FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT fk_proj_in_piece FOREIGN KEY (stock_piece_id) REFERENCES stock_pieces(id),
  CONSTRAINT fk_proj_in_variant FOREIGN KEY (variant_id) REFERENCES material_variants(id),
  CONSTRAINT fk_proj_in_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_outputs_offcuts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  stock_piece_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  width_mm INT NOT NULL,
  height_mm INT NOT NULL,
  location VARCHAR(190) NOT NULL DEFAULT '',
  area_offcut_m2 DECIMAL(12,4) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_proj_off_project (project_id),
  KEY idx_proj_off_piece (stock_piece_id),
  CONSTRAINT fk_proj_off_project FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT fk_proj_off_piece FOREIGN KEY (stock_piece_id) REFERENCES stock_pieces(id),
  CONSTRAINT fk_proj_off_variant FOREIGN KEY (variant_id) REFERENCES material_variants(id),
  CONSTRAINT fk_proj_off_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_non_stock_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit VARCHAR(32) NOT NULL DEFAULT 'buc',
  unit_price DECIMAL(12,2) NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_non_stock_project (project_id),
  CONSTRAINT fk_non_stock_project FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT fk_non_stock_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entity_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type VARCHAR(64) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notes_entity (entity_type, entity_id),
  KEY idx_notes_created (created_at),
  CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entity_comments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT UNSIGNED NULL,
  entity_type VARCHAR(64) NULL,
  entity_id BIGINT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  meta_json JSON NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_actor (actor_user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

