-- IKR Material Inventory Mockup
-- Seed Data (Section 7 of handoff doc) -- Phase 4 revision (adds categories)
-- Run AFTER schema.sql has been imported (fresh installs only --
-- if migrating an existing DB, use migration_phase4_categories.sql instead,
-- your seed data is already in place).
--
-- Insert order respects FK dependencies:
-- users -> categories -> materials -> material_serials -> work_orders -> usage_logs

USE ikr_inventory;

-- ------------------------------------------------------
-- users
-- id 1 = admin (Tias), id 2-4 = teknisi
-- ------------------------------------------------------
INSERT INTO users (id, name, username, password, role, created_at) VALUES
(1, 'Tias', 'tias', 'admin123', 'admin', '2026-07-20 08:00:00'),
(2, 'Budi Santoso', 'budi', 'teknisi123', 'teknisi', '2026-07-20 08:05:00'),
(3, 'Rudi Hartono', 'rudi', 'teknisi123', 'teknisi', '2026-07-20 08:06:00'),
(4, 'Agus Wijaya', 'agus', 'teknisi123', 'teknisi', '2026-07-20 08:07:00');

-- ------------------------------------------------------
-- categories
-- ------------------------------------------------------
INSERT INTO categories (id, name, code_prefix, created_at) VALUES
(1, 'ONT', 'AONT', '2026-07-20 08:09:00'),
(2, 'Router', 'ARTR', '2026-07-20 08:09:00'),
(3, 'Cable', 'ICAB', '2026-07-20 08:09:00');

-- ------------------------------------------------------
-- materials
-- stock_qty reflects state AFTER the active (non-deleted) usage_logs below
-- low_stock_threshold: NULL means no alert; set thresholds for demo purposes
-- ------------------------------------------------------
INSERT INTO materials (id, item_code, category_id, description, merk, tracking_type, unit, stock_qty, low_stock_threshold, created_at) VALUES
(1, 'AONT00000011', 1, 'ZTE-ONT ZXHN-F672Y (DUAL BAND)',       'ZTE',         'serial',   'pcs',   7,    2,    '2026-07-20 08:10:00'),
(2, 'AONT00000012', 1, 'Huawei HG8145V5',                       'Huawei',      'serial',   'pcs',   5,    2,    '2026-07-20 08:11:00'),
(3, 'ARTR00000021', 2, 'TP-Link Archer C6 AC1200',              'TP-Link',     'serial',   'pcs',   5,    2,    '2026-07-20 08:12:00'),
(4, 'ICAB00000133', 3, 'Fiber Optic Drop Cable 1-Core',         'Nextfiber',   'quantity', 'meter', 455,  100,  '2026-07-20 08:13:00'),
(5, 'ICAB00000134', 3, 'Fiber Optic Drop Cable 1-Core',         'Fiber Media', 'quantity', 'meter', 318,  100,  '2026-07-20 08:14:00');

-- ------------------------------------------------------
-- material_serials
-- material 1 (ONT ZTE): 8 SNs -> 1 currently used, rest available
-- material 2 (ONT Huawei): 6 SNs -> 1 currently used, rest available
-- material 3 (Router TP-Link): 6 SNs -> 1 currently used, rest available
-- used_in_log_id left NULL here; updated below once usage_logs exist
-- ------------------------------------------------------
INSERT INTO material_serials (id, material_id, serial_number, status, used_in_log_id) VALUES
-- material 1: ZTE ONT
(1, 1, 'ZTE23070001', 'available', NULL),
(2, 1, 'ZTE23070002', 'available', NULL),
(3, 1, 'ZTE23070003', 'available', NULL),
(4, 1, 'ZTE23070004', 'available', NULL),
(5, 1, 'ZTE23070005', 'available', NULL),
(6, 1, 'ZTE23070006', 'available', NULL),
(7, 1, 'ZTE23070007', 'used',      NULL), -- used in log 1
(8, 1, 'ZTE23070008', 'available', NULL), -- was used in log 7, soft-deleted -> reverted

-- material 2: Huawei ONT
(9,  2, 'HW23070101', 'available', NULL),
(10, 2, 'HW23070102', 'available', NULL),
(11, 2, 'HW23070103', 'available', NULL),
(12, 2, 'HW23070104', 'available', NULL),
(13, 2, 'HW23070105', 'available', NULL),
(14, 2, 'HW23070106', 'used',      NULL), -- used in log 3

-- material 3: TP-Link Router
(15, 3, 'TPL23070201', 'available', NULL),
(16, 3, 'TPL23070202', 'available', NULL),
(17, 3, 'TPL23070203', 'available', NULL),
(18, 3, 'TPL23070204', 'available', NULL),
(19, 3, 'TPL23070205', 'available', NULL),
(20, 3, 'TPL23070206', 'used',      NULL); -- used in log 5

-- ------------------------------------------------------
-- work_orders
-- wo 1 uses the real reference customer (Bimbay Prastyo Adi Nugroho); rest invented
-- ------------------------------------------------------
INSERT INTO work_orders (id, wo_no, wo_date, technician_id, customer_name, customer_address, port_fat, status, notes, created_at) VALUES
(1, 'WO-24072026-3419124', '2026-07-24', 2, 'Bimbay Prastyo Adi Nugroho', 'Jl. Melati Raya No. 12, Jakarta Selatan', 'JKT-MKS-D11-S02-H01-A12/5', 'completed', NULL, '2026-07-24 09:00:00'),
(2, 'WO-25072026-3419201', '2026-07-25', 2, 'Siti Marlina',               'Jl. Anggrek No. 45, Jakarta Selatan',     'JKT-MKS-D11-S02-H01-A15/3', 'completed', NULL, '2026-07-25 09:30:00'),
(3, 'WO-26072026-3419305', '2026-07-26', 3, 'Hendra Gunawan',             'Jl. Kenanga No. 8, Jakarta Timur',        'JKT-MKS-D12-S01-H02-A08/2', 'completed', NULL, '2026-07-26 10:00:00'),
(4, 'WO-27072026-3419412', '2026-07-27', 4, 'Dewi Anggraini',             'Jl. Mawar No. 21, Jakarta Timur',         'JKT-MKS-D12-S01-H02-A11/6', 'open',      'SN salah dicatat di lapangan, perlu koreksi teknisi.', '2026-07-27 11:00:00'),
(5, 'WO-27072026-3419455', '2026-07-27', 3, 'Fajar Ramadhan',             'Jl. Dahlia No. 3, Jakarta Selatan',       'JKT-MKS-D11-S03-H01-A04/1', 'open',      NULL, '2026-07-27 13:15:00'),
(6, 'WO-28072026-3419501', '2026-07-28', 4, 'Lina Kusuma',                'Jl. Flamboyan No. 17, Jakarta Timur',     'JKT-MKS-D13-S02-H01-A07/4', 'open',      NULL, '2026-07-28 08:30:00');
-- note: wo 6 has no usage_logs yet -- demonstrates an "assigned open WO" with nothing logged

-- ------------------------------------------------------
-- usage_logs
-- logs 7 and 8 are soft-deleted (field mistakes), demonstrating both
-- the serial-revert case (log 7) and the quantity-revert case (log 8).
-- materials.stock_qty and material_serials.status above already reflect
-- the state AFTER these two are excluded.
-- ------------------------------------------------------
INSERT INTO usage_logs (id, wo_id, technician_id, material_id, serial_number, qty_used, is_deleted, deleted_at, created_at) VALUES
(1, 1, 2, 1, 'ZTE23070007', NULL, 0, NULL,                  '2026-07-24 09:20:00'),
(2, 1, 2, 4, NULL,          25.00, 0, NULL,                 '2026-07-24 09:25:00'),
(3, 2, 2, 2, 'HW23070106', NULL, 0, NULL,                  '2026-07-25 09:45:00'),
(4, 2, 2, 4, NULL,          20.00, 0, NULL,                 '2026-07-25 09:50:00'),
(5, 3, 3, 3, 'TPL23070206', NULL, 0, NULL,                 '2026-07-26 10:20:00'),
(6, 3, 3, 5, NULL,          20.00, 0, NULL,                 '2026-07-26 10:25:00'),
(7, 4, 4, 1, 'ZTE23070008', NULL, 1, '2026-07-27 12:00:00', '2026-07-27 11:15:00'),
(8, 4, 4, 4, NULL,          15.00, 1, '2026-07-27 12:05:00', '2026-07-27 11:20:00'),
(9, 5, 3, 5, NULL,          12.00, 0, NULL,                 '2026-07-27 13:30:00');

-- ------------------------------------------------------
-- Back-fill material_serials.used_in_log_id for currently-used serials
-- (log 7's serial was soft-deleted and reverted, so it stays NULL/available)
-- ------------------------------------------------------
UPDATE material_serials SET used_in_log_id = 1 WHERE id = 7;  -- ZTE23070007 -> log 1
UPDATE material_serials SET used_in_log_id = 3 WHERE id = 14; -- HW23070106 -> log 3
UPDATE material_serials SET used_in_log_id = 5 WHERE id = 20; -- TPL23070206 -> log 5