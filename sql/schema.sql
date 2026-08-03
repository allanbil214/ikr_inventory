-- IKR Material Inventory Mockup
-- Canonical schema (post-Phase-9)
-- Database: ikr_inventory
-- Engine: InnoDB (required for FK support), utf8mb4
--
-- NOTE: if you already have a database seeded from before Phase 4,
-- do NOT re-run this file. Use sql/migration_phase4_categories.sql
-- against your existing DB instead. This file is for brand-new setups only.
--
-- Includes materials.low_stock_threshold (added manually mid-Phase 6,
-- now folded into this file) and the audit_log table (Phase 9, unused
-- until Phase 9 -- no rows are seeded for it, per the "going forward
-- only" decision: there's no real history to backfill for Phases 4-8).

CREATE DATABASE IF NOT EXISTS ikr_inventory
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ikr_inventory;

-- ------------------------------------------------------
-- users
-- ------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL, -- plain text for mockup, no hashing per spec
  role ENUM('admin', 'teknisi') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------
-- categories
-- Added in Phase 4 so admins can add new material categories
-- (e.g. "Splitter") without a code change -- each category owns its
-- own item-code prefix.
-- ------------------------------------------------------
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  code_prefix VARCHAR(10) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------
-- materials
-- ------------------------------------------------------
CREATE TABLE materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(30) NOT NULL UNIQUE,
  category_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  merk VARCHAR(50),
  tracking_type ENUM('serial', 'quantity') NOT NULL,
  unit VARCHAR(20) NOT NULL, -- "pcs" or "meter"
  stock_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
  low_stock_threshold DECIMAL(10,2) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_materials_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------
-- material_serials
-- ------------------------------------------------------
CREATE TABLE material_serials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  material_id INT NOT NULL,
  serial_number VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('available', 'used') NOT NULL DEFAULT 'available',
  used_in_log_id INT NULL,
  CONSTRAINT fk_material_serials_material
    FOREIGN KEY (material_id) REFERENCES materials(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------
-- work_orders
-- ------------------------------------------------------
CREATE TABLE work_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wo_no VARCHAR(50) NOT NULL UNIQUE,
  wo_date DATE NOT NULL,
  technician_id INT NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_address VARCHAR(255),
  port_fat VARCHAR(100),
  status ENUM('open', 'completed') NOT NULL DEFAULT 'open',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_work_orders_technician
    FOREIGN KEY (technician_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------
-- usage_logs
-- ------------------------------------------------------
CREATE TABLE usage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wo_id INT NOT NULL,
  technician_id INT NOT NULL,
  material_id INT NOT NULL,
  serial_number VARCHAR(100) NULL,   -- filled if material is serial-tracked
  qty_used DECIMAL(10,2) NULL,       -- filled if material is quantity-tracked
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usage_logs_wo
    FOREIGN KEY (wo_id) REFERENCES work_orders(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_usage_logs_technician
    FOREIGN KEY (technician_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_usage_logs_material
    FOREIGN KEY (material_id) REFERENCES materials(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Now that usage_logs exists, add the deferred FK from material_serials
ALTER TABLE material_serials
  ADD CONSTRAINT fk_material_serials_usage_log
    FOREIGN KEY (used_in_log_id) REFERENCES usage_logs(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- ------------------------------------------------------
-- audit_log
-- ------------------------------------------------------
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(20) NOT NULL, -- create / update / delete
  table_name VARCHAR(50) NOT NULL,
  record_id INT NOT NULL,
  old_value TEXT NULL, -- JSON snapshot
  new_value TEXT NULL, -- JSON snapshot
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_log_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;