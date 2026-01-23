-- HPL Manager (atelier producție) - schema MVP
-- Auto-run on first MySQL container init

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(190) NOT NULL,
  role ENUM('ADMIN','MANAGER','GESTIONAR','OPERATOR','VIZUALIZARE') NOT NULL DEFAULT 'VIZUALIZARE',
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
  mentor_stock DECIMAL(12,2) NULL,
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

CREATE TABLE IF NOT EXISTS hpl_stock_pieces (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NULL,
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
  KEY idx_hpl_stock_project (project_id),
  KEY idx_hpl_stock_accounting (is_accounting),
  KEY idx_hpl_stock_status (status),
  KEY idx_hpl_stock_piece_type (piece_type),
  KEY idx_hpl_stock_location (location),
  CONSTRAINT fk_hpl_stock_board FOREIGN KEY (board_id) REFERENCES hpl_boards(id),
  CONSTRAINT fk_hpl_stock_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  client_group_id INT UNSIGNED NULL,
  source_offer_id INT UNSIGNED NULL,
  status ENUM(
    'DRAFT','CONFIRMAT','IN_PRODUCTIE','IN_ASTEPTARE','FINALIZAT_TEHNIC',
    'LIVRAT_PARTIAL','LIVRAT_COMPLET','ANULAT',
    'NOU','IN_LUCRU','FINALIZAT','ARHIVAT'
  ) NOT NULL DEFAULT 'DRAFT',
  priority INT NOT NULL DEFAULT 0,
  category VARCHAR(190) NULL,
  description TEXT NULL,
  start_date DATE NULL,
  due_date DATE NULL,
  days_remaining_locked INT NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  notes TEXT NULL,
  technical_notes TEXT NULL,
  tags TEXT NULL,
  allocation_mode ENUM('by_qty','by_area','manual') NOT NULL DEFAULT 'by_area',
  allocations_locked TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  deleted_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_projects_code (code),
  KEY idx_projects_client (client_id),
  KEY idx_projects_group (client_group_id),
  KEY idx_projects_source_offer (source_offer_id),
  KEY idx_projects_status (status),
  KEY idx_projects_created (created_at),
  KEY idx_projects_deleted (deleted_at),
  CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_projects_group FOREIGN KEY (client_group_id) REFERENCES client_groups(id),
  CONSTRAINT fk_projects_user FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_projects_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  client_id INT UNSIGNED NULL,
  client_group_id INT UNSIGNED NULL,
  status ENUM('DRAFT','TRIMISA','ACCEPTATA','RESPINSA','ANULATA') NOT NULL DEFAULT 'DRAFT',
  category VARCHAR(190) NULL,
  description TEXT NULL,
  due_date DATE NULL,
  validity_days INT UNSIGNED NULL,
  notes TEXT NULL,
  technical_notes TEXT NULL,
  tags TEXT NULL,
  converted_project_id INT UNSIGNED NULL,
  converted_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_offers_code (code),
  KEY idx_offers_client (client_id),
  KEY idx_offers_group (client_group_id),
  KEY idx_offers_status (status),
  KEY idx_offers_created (created_at),
  KEY idx_offers_converted (converted_project_id),
  CONSTRAINT fk_offers_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_offers_group FOREIGN KEY (client_group_id) REFERENCES client_groups(id),
  CONSTRAINT fk_offers_converted_project FOREIGN KEY (converted_project_id) REFERENCES projects(id),
  CONSTRAINT fk_offers_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  offer_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit VARCHAR(32) NOT NULL DEFAULT 'buc',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_offer_prod (offer_id, product_id),
  KEY idx_offer_prod_offer (offer_id),
  KEY idx_offer_prod_product (product_id),
  CONSTRAINT fk_offer_prod_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_product_hpl (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  offer_id INT UNSIGNED NOT NULL,
  offer_product_id BIGINT UNSIGNED NOT NULL,
  board_id INT UNSIGNED NOT NULL,
  consume_mode ENUM('FULL','HALF') NOT NULL DEFAULT 'FULL',
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  width_mm INT NULL,
  height_mm INT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_offer_hpl_offer (offer_id),
  KEY idx_offer_hpl_offer_product (offer_product_id),
  KEY idx_offer_hpl_board (board_id),
  CONSTRAINT fk_offer_hpl_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_offer_hpl_offer_product FOREIGN KEY (offer_product_id) REFERENCES offer_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_product_accessories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  offer_id INT UNSIGNED NOT NULL,
  offer_product_id BIGINT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 1,
  unit VARCHAR(32) NOT NULL DEFAULT 'buc',
  unit_price DECIMAL(12,2) NULL,
  include_in_deviz TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_offer_acc_offer (offer_id),
  KEY idx_offer_acc_offer_product (offer_product_id),
  KEY idx_offer_acc_item (item_id),
  CONSTRAINT fk_offer_acc_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_offer_acc_offer_product FOREIGN KEY (offer_product_id) REFERENCES offer_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_work_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  offer_id INT UNSIGNED NOT NULL,
  offer_product_id BIGINT UNSIGNED NULL,
  work_type ENUM('CNC','ATELIER') NOT NULL,
  hours_estimated DECIMAL(10,2) NULL,
  cost_per_hour DECIMAL(12,2) NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_offer_work_offer (offer_id),
  KEY idx_offer_work_product (offer_product_id),
  CONSTRAINT fk_offer_work_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_offer_work_product FOREIGN KEY (offer_product_id) REFERENCES offer_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS search_index (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type VARCHAR(32) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  label VARCHAR(255) NOT NULL,
  sub VARCHAR(255) NULL,
  href VARCHAR(255) NOT NULL,
  thumb_url VARCHAR(255) NULL,
  thumb_url2 VARCHAR(255) NULL,
  search_text TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_search_entity (entity_type, entity_id),
  KEY idx_search_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Produse (piesele din proiect)
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NULL,
  name VARCHAR(190) NOT NULL,
  sale_price DECIMAL(12,2) NULL,
  width_mm INT NULL,
  height_mm INT NULL,
  notes TEXT NULL,
  cnc_settings_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_code (code),
  KEY idx_products_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit VARCHAR(32) NOT NULL DEFAULT 'buc',
  m2_per_unit DECIMAL(12,4) NOT NULL DEFAULT 0,
  surface_type ENUM('BOARD','M2') NULL,
  surface_value DECIMAL(12,2) NULL,
  production_status ENUM('CREAT','PROIECTARE','CNC','MONTAJ','GATA_DE_LIVRARE','AVIZAT','LIVRAT_PARTIAL','LIVRAT') NOT NULL DEFAULT 'CREAT',
  hpl_board_id INT UNSIGNED NULL,
  delivered_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  cnc_override_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  finalized_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proj_prod (project_id, product_id),
  KEY idx_proj_prod_project (project_id),
  KEY idx_proj_prod_status (production_status),
  KEY idx_proj_prod_hpl_board (hpl_board_id),
  CONSTRAINT fk_proj_prod_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_proj_prod_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_proj_prod_hpl_board FOREIGN KEY (hpl_board_id) REFERENCES hpl_boards(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Etichete (labels)
CREATE TABLE IF NOT EXISTS labels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(64) NOT NULL,
  color VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_labels_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entity_labels (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Consumuri Magazie (accesorii)
CREATE TABLE IF NOT EXISTS project_magazie_consumptions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Consumuri HPL (m2) + alocare pe produse
CREATE TABLE IF NOT EXISTS project_hpl_consumptions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_hpl_allocations (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_product_hpl_consumptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  project_product_id BIGINT UNSIGNED NOT NULL,
  board_id INT UNSIGNED NOT NULL,
  stock_piece_id INT UNSIGNED NULL,
  consumed_piece_id INT UNSIGNED NULL,
  source ENUM('PROJECT','REST') NOT NULL DEFAULT 'PROJECT',
  consume_mode ENUM('FULL','HALF') NOT NULL DEFAULT 'FULL',
  status ENUM('RESERVED','CONSUMED') NOT NULL DEFAULT 'RESERVED',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  consumed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_pphc_project (project_id),
  KEY idx_pphc_pp (project_product_id),
  KEY idx_pphc_piece (stock_piece_id),
  KEY idx_pphc_consumed_piece (consumed_piece_id),
  KEY idx_pphc_status (status),
  CONSTRAINT fk_pphc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pphc_pp FOREIGN KEY (project_product_id) REFERENCES project_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pphc_board FOREIGN KEY (board_id) REFERENCES hpl_boards(id),
  CONSTRAINT fk_pphc_piece FOREIGN KEY (stock_piece_id) REFERENCES hpl_stock_pieces(id) ON DELETE SET NULL,
  CONSTRAINT fk_pphc_consumed_piece FOREIGN KEY (consumed_piece_id) REFERENCES hpl_stock_pieces(id) ON DELETE SET NULL,
  CONSTRAINT fk_pphc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Livrări
CREATE TABLE IF NOT EXISTS project_deliveries (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_delivery_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Fișiere pe entități (proiect/produs/consum etc.)
CREATE TABLE IF NOT EXISTS entity_files (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Ore CNC / Atelier
CREATE TABLE IF NOT EXISTS project_work_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Magazie (accesorii)
CREATE TABLE IF NOT EXISTS magazie_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  winmentor_code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  unit VARCHAR(16) NOT NULL DEFAULT 'buc',
  unit_price DECIMAL(12,2) NULL,
  stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
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
  qty DECIMAL(12,3) NOT NULL,
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

CREATE TABLE IF NOT EXISTS app_settings (
  `key` VARCHAR(64) NOT NULL,
  value VARCHAR(255) NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`),
  KEY idx_app_settings_updated (updated_at),
  CONSTRAINT fk_app_settings_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

