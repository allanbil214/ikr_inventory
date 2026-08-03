# IKR Material Inventory Mockup — Project Handoff Document

**Project type:** Local mockup/demo (PHP + MySQL + XAMPP)
**Primary use case:** Track material (ONT, router, cable) stock consumed by field technicians during home installation jobs (IKR — Instalasi Kabel Rumah / Wi-Fi), based on real Work Order documents from Oxygen.id Home.
**Design direction:** Mobile-first, white/elegant theme, bottom navbar (see Section 5).

**Build status:** Phase 1 (Environment & Schema) — ✅ Done. Phase 2 (Auth & Base Layout) — ✅ Done. Phase 3 (Seeders) — ✅ Done. Phase 4 (Materials Module, Admin) — ✅ Done & tested, including the mid-phase `categories` schema addition (see Section 3). Phase 5 (Work Orders Module, Admin) — ✅ Done & tested, `getAllTeknisi()` bug fixed (see Section 8); a possible CSS gap was flagged but not yet confirmed. Phase 6 (Usage Logging, Teknisi) — ✅ Done & tested; includes a mid-phase `materials.low_stock_threshold` column added manually (not in `sql/`, see Section 8). Phase 7 (Edit/Soft-Delete & Stock Correction) — ✅ Done & tested. Phase 8 (History & Logs Views) — ✅ Done & tested. Phase 9 (Admin Dashboard & Audit Log) — ✅ Done & tested; `sql/schema.sql` was folded up to canonical/current (see Section 8) after a DB loss, so it's now the single source of truth for a fresh install. Post-Phase-9 cleanup: Log Usage's material picker reworked from one-material-per-submit to multi-select with inline per-card inputs (UX fix, not a numbered phase); the teknisi `Profile` navbar link (present since Phase 2, never routed) was finally wired up. Currently paused before Phase 10 per Ground Rule #1.

---

## 0. Ground Rules

1. **Always confirm before coding, fixing, or making any change.** No unsolicited code changes, refactors, or "while I was in there" fixes — propose first, wait for a go-ahead.
2. **Every phase below is a checkpoint.** Each phase ends in something testable. Nothing moves to the next phase until it's been tried and marked pass/fail.
3. **Output delivery rule:** never re-zip the full project after the first delivery. Every delivery after Phase 1 is a **small zip containing only new/modified files**, preserving the relative folder path (e.g. `app/controllers/MaterialController.php`) so files can be dropped directly into the existing project. Each zip is named uniquely and traceably, e.g.:
   - `phase1-setup.zip`
   - `phase2-auth.zip`
   - `phase3-materials-fix1.zip`
   - `phase4-usagelogs.zip`
   - **One-off exception:** after Phase 9, a full project re-zip (`ikr-inventory-full-phase9.zip`) was made because the local copy was lost and only a Phase 6 build remained on hand. This isn't a new pattern -- future deliveries go back to small diffs per this rule.
4. If a fix only touches 1–2 files, the zip (or even just the raw file) should reflect that — no bundling in untouched files "just in case."
5. Placeholder/dummy material data is fine per seeders (Section 7) — no real client data beyond what's already in the two Work Order reference images.

---

## 1. Background Summary

- Tias coordinates field technicians doing home internet/cable installs (IKR) for an ISP (Oxygen.id Home, via PT Ekamas Mora Republik / PT Biru Sistem Perkasa).
- Each job is driven by a **Work Order (WO)** — confirmed from real WO document: contains WO No/Date, Sales rep, Technician, Port FAT, full Customer info block, Package, Device Info, Materials table (Item Code / Description / Serial Number), Test Parameters, and signatures.
- Materials fall into two behaviors:
  - **Serial-tracked** (ONT, routers) — discrete units, each with a unique Serial Number (SN), decremented one at a time.
  - **Quantity-tracked** (cable) — consumed by length (meters), decremented by amount used.
- Technicians pick materials semi-randomly from stock (not pre-assigned per customer), log what they used against a WO, and stock should auto-decrement. Mistakes happen often in the field, so edits/deletes must be supported without corrupting stock counts.
- Only 2 real user roles: **Admin (Tias)** and **Teknisi**. Customer is a data field only, not a user.

---

## 2. Roles

| Role | Description |
|---|---|
| **Admin** | Tias. Full visibility across all technicians, materials, WOs, and logs. Manages material master data, monitors stock, reviews/corrects technician entries. |
| **Teknisi** | Field technician. Sees assigned WOs, logs material usage against a WO, sees live stock counts, can edit/soft-delete their own logs. |

---

## 3. Entity / Table Design

### `users`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| name | VARCHAR | |
| username | VARCHAR UNIQUE | |
| password | VARCHAR | plain text for mockup (no hashing per your instruction) |
| role | ENUM('admin','teknisi') | |
| created_at | DATETIME | |

### `categories`
**Added mid-Phase 4** (deviation from original plan, confirmed with you). `materials.category` started as a free-text VARCHAR; to let each category own its own item-code prefix (so a new category like "Splitter" doesn't need a code change), it became a proper table with a FK from `materials`. Migrated via `sql/migration_phase4_categories.sql` for the already-seeded DB; `sql/schema.sql` and `sql/seed_data.sql` were also updated for fresh installs.

| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| name | VARCHAR UNIQUE | e.g. "ONT", "Router", "Cable" |
| code_prefix | VARCHAR UNIQUE | e.g. "AONT", "ARTR", "ICAB" |
| created_at | DATETIME | |

New categories (e.g. "Splitter") are added **inline from the material form** rather than a separate Categories screen, to keep Phase 4 from growing extra scope — see Section 6.

### `materials`
Master list, one row per material *type*.

| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| item_code | VARCHAR UNIQUE | auto-generated, e.g. `AONT00000011`, `ICAB00000133` — sequence derived from existing codes under that prefix, not a stored counter |
| category_id | INT FK → categories.id | was a free-text `category` VARCHAR before Phase 4 |
| description | VARCHAR | e.g. "ZTE-ONT ZXHN-F672Y (DUAL BAND)" |
| merk | VARCHAR | e.g. ZTE, Nextfiber, Fiber Media |
| tracking_type | ENUM('serial','quantity') | drives which input the teknisi sees; not editable after creation |
| unit | VARCHAR | "pcs" or "meter" |
| stock_qty | INT/DECIMAL | current remaining count/length — for `serial` materials this is derived from `material_serials` (available count), not directly editable |
| low_stock_threshold | DECIMAL(10,2) NULLABLE | **added mid-Phase 6**, via manual `ALTER TABLE` per your request (not added to `sql/schema.sql`/`seed_data.sql`, so a fresh install needs it added by hand: `ALTER TABLE materials ADD COLUMN low_stock_threshold DECIMAL(10,2) NULL DEFAULT NULL AFTER stock_qty;`). NULL = no low-stock warning shown for that material; otherwise powers the teknisi Home snapshot (`stock_qty <= low_stock_threshold`). |
| created_at | DATETIME | |

### `material_serials`
Only populated for `tracking_type = serial` materials.

| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| material_id | INT FK → materials.id | |
| serial_number | VARCHAR UNIQUE | |
| status | ENUM('available','used') | |
| used_in_log_id | INT NULLABLE FK → usage_logs.id | |

### `work_orders` (light reference version)
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| wo_no | VARCHAR UNIQUE | e.g. WO-24072026-3419124 |
| wo_date | DATE | |
| technician_id | INT FK → users.id | confirmed FK (not free text) |
| customer_name | VARCHAR | |
| customer_address | VARCHAR | |
| port_fat | VARCHAR | e.g. JKT-MKS-D11-S02-H01-A12/5 |
| status | ENUM('open','completed') | |
| notes | TEXT NULLABLE | |
| created_at | DATETIME | |

*(Sales rep, package, test parameters, and signature fields are intentionally out of scope — WO is a lightweight reference table for tying usage logs to a customer/technician, not a full replica of the paper form.)*

### `usage_logs` (core transaction table)
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| wo_id | INT FK → work_orders.id | |
| technician_id | INT FK → users.id | |
| material_id | INT FK → materials.id | |
| serial_number | VARCHAR NULLABLE | filled if material is serial-tracked |
| qty_used | DECIMAL NULLABLE | filled if material is quantity-tracked (meters) |
| is_deleted | BOOLEAN DEFAULT 0 | soft delete flag |
| deleted_at | DATETIME NULLABLE | |
| created_at | DATETIME | |

**Stock behavior rule:**
- On create: `materials.stock_qty` decrements by 1 (serial) or by `qty_used` (quantity); if serial, matching `material_serials.status` → `used`.
- On soft-delete: `materials.stock_qty` re-increments accordingly; if serial, `material_serials.status` → `available` again.
- Derived "total used" reporting is always possible later via `SUM(qty_used) WHERE is_deleted = 0` against `usage_logs` — no schema change needed if this is wanted post-mockup.

### `audit_log`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| user_id | INT FK → users.id | |
| action | VARCHAR | create / update / delete |
| table_name | VARCHAR | |
| record_id | INT | |
| old_value | TEXT NULLABLE | JSON snapshot |
| new_value | TEXT NULLABLE | JSON snapshot |
| created_at | DATETIME | |

---

## 4. PHP Project Structure (lightweight MVC)

```
/app
  /controllers        → AuthController.php, MaterialController.php, UsageLogController.php,
                         WorkOrderController.php, DashboardController.php, AuditController.php
  /models              → User.php, Material.php, MaterialSerial.php, WorkOrder.php,
                         UsageLog.php, AuditLog.php  (plain PHP classes wrapping PDO)
  /views
    /auth
    /admin
    /teknisi
    /partials          → navbar.php, header.php, footer.php
  /core                → Database.php (PDO connection), Router.php (simple query-param or
                         .htaccess based routing), Auth.php (session/role helper)
/public
  index.php            → single entry point
  /assets              → css/, js/, images/
/seeders                → seed_data.sql (single raw SQL file, all tables — see Section 7)
/sql                    → schema.sql (CREATE TABLE statements)
```

- No external framework, no ORM — models are plain classes with methods like `getAll()`, `find($id)`, `create($data)`, `update($id,$data)`, `softDelete($id)`.
- Routing: simple `?page=material&action=edit&id=5` style is acceptable for a mockup; clean URLs via `.htaccess` optional, flag if wanted.

---

## 5. Visual/UX Direction (reference for implementation)

- **Theme:** white background dominant, single accent color (blue/teal) for CTAs, active nav state, and status highlights. Dark charcoal text (not pure black). Muted amber/red for low-stock warnings (not harsh red).
- **Style references:** Notion mobile × Jenius/Bank Jago — soft shadows, rounded cards, generous padding, no dense enterprise-admin-panel look even on admin screens.
- **Bottom navbar (mobile):**
  - Teknisi: Home / Log Usage / History / Profile
  - Admin: Home / Materials / Logs / WOs (Audit Log nested inside Home/Dashboard; Profile/Logout via top-corner icon, not a navbar slot)
- **Patterns to reuse:** stock summary cards (bold number + label, like account balance cards), transaction-list pattern for History/Logs (icon-left, info-middle, action-right), category filter chips for material pickers, card-per-row instead of dense tables everywhere, including admin.

---

## 6. Screens Overview

### Teknisi
1. **Login**
2. **Home** — greeting, live stock snapshot cards, list of assigned open WOs, "Log Usage" CTA
3. **Log Usage** — select WO → material picker (category chips + search) → SN picker (serial) or meter input (quantity) → submit
4. **History** — own usage logs, filter by date/WO, edit/soft-delete
5. **Profile** — name, logout

### Admin (Tias)
1. **Login**
2. **Dashboard (Home)** — stock overview cards (grid), recent activity feed, link to full Audit Log
3. **Materials** — list/search/filter, add/edit (auto item-code generation by category prefix), view SN inventory per material, manual stock adjustment
4. **Logs** — all technicians' usage logs, filter by technician/WO/date, edit/soft-delete
5. **Work Orders** — list/create/edit, assign technician, view materials logged per WO
6. **Audit Log** — accessed from Dashboard, full change history

---

## 7. Seeders (required for every table)

| Table | Seed content |
|---|---|
| `users` | 1 admin (Tias), 2–3 teknisi accounts |
| `materials` | Realistic mix matching real WO data: ZTE ONT ZXHN-F672Y, a router model, Nextfiber cable, Fiber Media cable — 5–8 rows total, mixed serial/quantity tracking |
| `material_serials` | 5–10 SNs per serial-tracked material, mostly `available`, a few `used` |
| `work_orders` | 4–6 sample WOs, mix of `open`/`completed`, using the real customer example (Bimbay Prastyo Adi Nugroho) plus a couple of invented ones |
| `usage_logs` | 6–10 sample logs tied to the above WOs/materials, including at least one soft-deleted entry to demo the audit trail |

**Deviation from original plan (confirmed with you):** instead of PHP seeder classes (`/seeders/*.php` + `run_all.php`), seed data was delivered as a single plain SQL file, `/seeders/seed_data.sql`, importable directly via phpMyAdmin or `mysql -u root ikr_inventory < seed_data.sql`. No PHP seeder scripts will be built — this file is the permanent seeding approach for the mockup. It contains all 5 tables above in dependency order, with `stock_qty` / `material_serials.status` pre-computed to be internally consistent with the seeded `usage_logs` (including two soft-deleted entries to demo both the serial-revert and quantity-revert cases ahead of Phase 7).

---

## 8. Build Phases (each is a testable checkpoint)

### Phase 1 — Environment & Schema — ✅ DONE (2026-07-28)
- Set up XAMPP project folder, `/sql/schema.sql` with all tables from Section 3, DB connection (`core/Database.php`).
- **Test:** import schema into MySQL via phpMyAdmin, confirm all tables/columns/FKs exist correctly.
- **Result:** Pass — all 6 tables imported correctly, `db-test.php` confirmed connection and full table list.
- **Deliverable:** `phase1-setup.zip`

### Phase 2 — Auth & Base Layout — ✅ DONE (2026-07-28)
- `AuthController`, login view, session handling, role-based redirect (admin → dashboard, teknisi → home).
- Base layout partials: navbar (bottom, role-aware), header, footer — white/elegant theme applied here first since it's shared everywhere. Theme: white background, terracotta/orange accent (`#D97757`).
- Added `asset()` cache-busting helper (`app/core/helpers.php`) so CSS/JS changes are picked up on normal refresh, no hard refresh needed — not in the original doc, added per your request.
- **Test:** log in as admin and teknisi (2 separate browsers), confirm correct landing screen and navbar per role, logout works.
- **Result:** Pass.
- **Deliverable:** `phase2-auth.zip`

### Phase 3 — Seeders — ✅ DONE (delivered as raw SQL, 2026-07-28)
- Seed data for all 5 tables per Section 7, delivered as `/seeders/seed_data.sql` instead of PHP seeder scripts (see Section 7 deviation note).
- **Test:** import `seed_data.sql` via phpMyAdmin, confirm data appears correctly in DB (spot-check a few rows per table).
- **Deliverable:** `seed_data.sql`

### Phase 4 — Materials Module (Admin) — ✅ DONE (2026-07-28)
- `MaterialController` CRUD, auto item-code generation, material list/search UI, SN inventory sub-view, manual stock adjustment.
- Mid-phase addition: `categories` table + FK (see Section 3), with inline "add new category" on the material form instead of a separate Categories screen.
- Delivered: `app/models/Category.php`, `app/models/Material.php`, `app/models/MaterialSerial.php`, `app/controllers/MaterialController.php`, `app/views/admin/materials/{list,form,serials}.php`, `public/assets/js/materials.js`, routes wired into `Router.php`, and CSS additions to `style.css`. `materials` navbar link no longer 404s.
- Note: material **delete** was not built — the doc's Phase 4 test criteria and Section 6 only call for add/edit/view SN/adjust stock, not delete, and deleting a material with existing `usage_logs`/`material_serials` history would need FK-safety rules not yet scoped. Flag if you want a (likely soft-)delete added.
- **Bug found & fixed (fix1):** search threw a fatal `PDOException: Invalid parameter number`. Cause: `Material::getAll()`'s search clause reused the same named placeholder (`:search`) three times in one query — real (non-emulated) prepared statements, which this project uses (`Database.php`'s `PDO::ATTR_EMULATE_PREPARES => false`), don't allow that. Fixed by giving each `LIKE` clause its own placeholder bound to the same value. Only `app/models/Material.php` was touched.
- **Test:** add a new material → confirm `item_code` auto-generates with the right prefix; edit a material's fields; manually adjust stock on a quantity-tracked material; view the SN list for a serial-tracked material and add a new SN to it; search/filter the material list by category; add a brand-new category inline from the form.
- **Result:** Pass (search bug found and fixed via `phase4-materials-fix1.zip`, re-tested clean).
- **Deliverable:** `phase4-materials.zip`, `phase4-materials-fix1.zip`

### Phase 5 — Work Orders Module — ✅ DONE (2026-07-28)
- `WorkOrder.php` (`getAll` filter by status/technician, `find`, `findByWoNo`, `create`, `update` incl. status, `getAssignedOpen`, `toggleStatus`), `WorkOrderController` (list, form, save, detail, `toggleStatus`), routes wired into `Router.php`, `workorders` navbar link no longer 404s.
- Decisions locked in before build: (1) added a minimal "assigned open WOs" preview to `teknisi/home.php` now, so the Phase 5 test criterion is actually checkable ahead of Phase 6; (2) admin can manually toggle a WO between `open`/`completed` in this phase rather than waiting for Phase 6/7 usage-logging to drive it.
- Views: `admin/workorders/list.php` (card-per-row, status chips, search/filter by technician), `form.php` (create/edit + technician dropdown), `detail.php` (WO info + status toggle + "materials logged" section, reading existing seeded `usage_logs` — empty state is expected pre-Phase 6).
- **Bug found & fixed (fix1):** fatal `Error: Call to undefined method User::getAllTeknisi()` on the WO list/form screens. Cause: build got cut off mid-session before the planned `User.php` teknisi-list helper was actually written, even though `WorkOrderController` already called it. Fixed by adding `User::getAllTeknisi()` (returns `id`/`name`/`username` for `role = 'teknisi'`, ordered by name) to `app/models/User.php`. Rest of the Phase 5 batch (WorkOrder model, controller, routes, `DashboardController::teknisiHome`, views) was cross-checked call-by-call against definitions and found intact.
- **Flagged, unconfirmed:** user suspects a possible missing CSS rule somewhere in the Phase 5 UI (not specified which element). Audited `style.css` against every class used in `admin/workorders/*.php` and `teknisi/home.php`, including the `/* Phase 5: Work Order detail */` additions (`.wo-info-card`, `.wo-info-row`, `.wo-info-label`, `.wo-info-value`) — all referenced classes currently resolve and the stylesheet isn't truncated. Leaving this open pending a screenshot/repro since nothing concrete turned up yet; not treating it as a confirmed bug.
- **Test:** create a WO, assign to a teknisi → shows correctly in admin WO list; log in as that teknisi → WO appears in Home's "assigned open WOs" preview; toggle a WO to `completed` as admin → drops out of teknisi's open list, still visible/editable from admin; filter/search admin WO list by status and technician.
- **Result:** Pass after fix1 (`getAllTeknisi()` bug found and fixed, re-tested clean). CSS concern noted above, unresolved/unconfirmed.
- **Deliverable:** `phase5-workorders.zip`, `phase5-workorders-fix1.zip`

### Phase 6 — Usage Logging (Teknisi core flow) — ✅ DONE (2026-07-28)
- Mid-phase addition: `materials.low_stock_threshold` (nullable DECIMAL(10,2), per-material, added via a manual `ALTER TABLE` rather than a migration file per your request — not reflected in `sql/schema.sql`/`seed_data.sql`, so a fresh install would need the column added by hand too). Optional field added to the admin material form (`MaterialController`/`form.php`); NULL = no low-stock warning for that material.
- `app/models/UsageLog.php` (new) — `create()` wraps the log insert, stock decrement, and (for serial materials) SN status flip in one transaction; re-checks stock/SN availability server-side (row-locked via `FOR UPDATE`) rather than trusting client input. `getByWorkOrder()` promoted here from a private query that used to live in `WorkOrderController` (Phase 5), now shared by both the admin WO detail view and this phase.
- `app/controllers/UsageLogController.php` (new) — `form` (Log Usage screen: WO picker + material picker with available SNs attached), `save` (server-side re-validates the WO actually belongs to this teknisi and is still open before logging).
- `app/models/Material.php` — added `getLowStock()` for the Home snapshot; `create()`/`update()` extended to carry the new threshold field.
- `app/models/MaterialSerial.php` — added `getAvailableByMaterial()` and `markUsed()`.
- `app/views/teknisi/log-usage.php` (new) — WO select, client-side-filtered material picker (category chips + search, via `public/assets/js/log-usage.js`), dynamic SN dropdown (serial materials) or qty input (quantity materials) depending on the selected material.
- `app/views/teknisi/home.php` — added the live low-stock snapshot section (materials at/under their threshold), above the existing Phase 5 assigned-open-WOs preview.
- `workorder-toggle-status`... no change; `log-usage` / `usage-log-save` routes wired into `Router.php`, matching the `log-usage` navbar link that was already present but unwired.
- **Test:** log a serial-tracked item → SN marked `used`, stock -1, that SN no longer appears in the picker; log a quantity-tracked item → stock reduced by meters used; try to log more than remaining stock → blocked with a clear error, nothing partially written; teknisi Home stock snapshot shows only materials at/under threshold and updates after a log; Log Usage WO picker only offers that teknisi's own open WOs (re-verified server-side, not just hidden client-side).
- **Result:** Pass (confirmed during Phase 7 testing, which exercised the full create flow ahead of edit/delete).
- **Deliverable:** `phase6-usagelog.zip`

### Phase 7 — Edit/Soft-Delete & Stock Correction — ✅ DONE (2026-07-28)
- Decisions locked in before build: (1) since the full filterable History/Logs screens are Phase 8 scope, teknisi edit/delete for now lives in a small "Log Terbaru" panel added to the existing Log Usage screen, shown per selected WO; admin edit/delete extends the existing WO detail page's "Material Terpakai" list instead of a new screen; (2) editing a serial-tracked log allows swapping to a different SN (technician grabbed the wrong physical unit), not just delete-and-relog; (3) teknisi lose edit/delete rights once their WO is marked `completed` (admin can always override); `audit_log` writes were deliberately left out of this phase, deferred to Phase 9.
- `app/models/UsageLog.php` — added `find()` (single log + material + WO info, for permission checks and edit-form prefill), `delete()` (soft-delete + stock/SN revert, transactional with row locks), `updateQuantity()` (reverts the old amount into stock before re-validating the new amount, so shrinking or growing a logged amount is always checked against true availability), `updateSerial()` (reverts the old SN to `available`, claims the new one, no stock_qty change since it's still exactly one unit either way; no-ops cleanly if resubmitted with the same SN).
- `app/models/MaterialSerial.php` — added `getSelectableForEdit()`, returning available SNs plus whichever SN is currently attached to the log being edited (so "keep the same SN" is always a valid dropdown option even though its status is `used`, not `available`).
- `app/controllers/UsageLogController.php` — added `editForm()`/`update()`/`delete()`, shared by both admin and teknisi entry points; a private `authorize()` enforces admin-any vs teknisi-own-and-WO-still-open; `form()` (Log Usage) now also attaches each assigned WO's active logs for the new mini-panel.
- `app/views/usage-logs/edit.php` (new, shared) — one edit form branching between a qty input (quantity materials) and an SN dropdown (serial materials), plus a delete button with a JS confirm dialog.
- `app/views/admin/workorders/detail.php` — Edit/Delete added to each row of the existing logged-materials list.
- `app/views/teknisi/log-usage.php` + `public/assets/js/log-usage.js` — new "Log Terbaru" panel per assigned WO, shown/hidden as the WO dropdown changes, each row with Edit/Delete.
- `public/assets/css/style.css` — `.btn-danger-small`, `.log-row`/`.log-row-actions`, `.wo-logs-panel`.
- Routes added to `Router.php`: `usage-log-edit`, `usage-log-update`, `usage-log-delete`.
- **Test:** soft-delete a log, confirm stock count reverts correctly and SN (if applicable) becomes `available` again; confirm deleted log no longer shows in active History/Logs but still exists in DB.
- **Result:** Pass.
- **Deliverable:** `phase7-softdelete.zip`

### Phase 8 — History & Logs Views — ✅ DONE (2026-07-28)
- Decisions locked in before build (confirmed via chat, not re-litigated here): (1) Teknisi History shows logs across **all** of a teknisi's WOs, open and completed, not just the open-only scope of the Phase 7 mini-panel; edit/delete still only works on open-WO logs, completed-WO logs render read-only with a "Selesai" badge instead of action buttons; (2) a "Tampilkan yang dihapus" toggle was added now to both History and Logs rather than deferring soft-deleted visibility to Phase 9's Audit Log — checking it includes soft-deleted logs in the list, greyed out, with a "Dihapus" badge and no edit/delete; (3) filters go beyond the doc's literal "date/WO" (teknisi) and "technician/WO/date" (admin) — a category dropdown (chip row, same pattern as the Materials list) and a free-text material search (code/description) were added to both screens per your "also add material/category filter" answer; (4) date filter is applied against `usage_logs.created_at` (when the log was actually recorded), not the WO's `wo_date` — flagged as an assumption, not yet contested.
- `app/models/UsageLog.php` — added `getFiltered(array $filters)`, one shared query backing both screens. All filters optional: `technician_id`, `wo_id`, `category_id`, `search` (matches `item_code`/`description`), `date_from`/`date_to` (`DATE(created_at)` range), `include_deleted`.
- `app/controllers/UsageLogController.php` — added `history()` (teknisi) and `adminLogs()` (admin) actions, plus a shared `buildFilters()` GET-param parser. Reused the Phase 7 edit/delete routes as planned: `sanitizeReturnTo()`/`setFlash()`/`redirectBack()` extended with two new whitelisted targets, `history` and `logs` (own flash keys `history_flash`/`admin_logs_flash`), so the confirm-and-redirect flow for Edit/Delete lands back on the right list either way.
- `app/views/teknisi/history.php` (new), `app/views/admin/logs/list.php` (new) — filter panel (search box, category chips, WO/technician dropdowns, date-range inputs, "show deleted" checkbox, single "Terapkan Filter" submit) above a `serial-list`/`log-row` list reusing the Phase 7 row pattern.
- `app/views/usage-logs/edit.php` — the "← Kembali" back-link only handled `workorder-detail` vs. a `log-usage` default; extended to also route back to `history`/`logs` correctly.
- `app/core/Router.php` — wired the `history` and `logs` routes (the navbar links were already present and pointing at these page keys, just unwired until now).
- `public/assets/css/style.css` — `.filter-panel`, `.filter-row`, `.filter-checkbox` for the new filter UI; `.badge-deleted`, `.log-row-deleted`, `.log-row-info` for the greyed-out deleted-log state.
- **Test:** filter by date and technician, confirm results match DB state.
- **Result:** Pass.
- **Deliverable:** `phase8-logsviews.zip`

### Phase 9 — Admin Dashboard & Audit Log — ✅ DONE (2026-08-03)
- Decisions locked in before build (confirmed via chat, not re-litigated here): (1) the doc's original test line said "confirm audit log shows entries for all CRUD actions performed so far" -- but Phases 4-7 never wrote to `audit_log`, so there was no real history to retroactively show. Went with **logging going forward only**, no fabricated/backfilled entries for seed data or past actions; the test line below is reworded accordingly. (2) Audit scope is **all three of** materials, work_orders, usage_logs -- not usage_logs only. (3) Dashboard stock overview cards show **both** an aggregate stat-card row (material types / open WOs / low-stock count) and a per-category stock breakdown, on top of a low-stock highlight section reusing the existing `badge-lowstock` pattern from teknisi Home.
- `app/models/AuditLog.php` (new) — `record()` (called from controllers, never from inside a model's own transaction, so a logging failure can't roll back the real mutation it's describing), `getRecent()` for the dashboard feed, `getFiltered()` (table/action/user/date-range, all optional) for the full Audit Log page.
- `app/models/Material.php` — added `getStockByCategory()` (SUM(stock_qty) grouped by category, with a distinct-units list rather than blindly summing incompatible units) and `countAll()`.
- `app/models/WorkOrder.php` — added `countByStatus()`.
- `app/models/User.php` — added `getAll()` (every user regardless of role, for the Audit Log's user filter -- unlike the existing `getAllTeknisi()`, admin actions need to be filterable too).
- `app/controllers/MaterialController.php` — `save()` (create/update), `adjustStock()`, and `addSerial()` now call `AuditLog::record()`. `addSerial()` is logged as a `materials` **update** (old/new stock_qty), not a 4th audited table, since its only meaningful effect is the material's stock going up by one SN -- flagged as a call made without a literal doc answer, not contested since delivery.
- `app/controllers/WorkOrderController.php` — `save()` and `toggleStatus()` now call `AuditLog::record()`.
- `app/controllers/UsageLogController.php` — `save()`, `update()`, `delete()` now call `AuditLog::record()` (delete logs old_value only; there's no meaningful "after" state beyond `is_deleted` flipping).
- `app/controllers/DashboardController.php` — real `index()` content replacing the Phase 2 placeholder.
- `app/controllers/AuditController.php` (new) — `index()`, admin-only, backs the full Audit Log page.
- `app/views/admin/dashboard.php` — stat-card row, per-category stock cards, low-stock section, "Aktivitas Terbaru" feed (latest 8 audit entries), link to the full Audit Log.
- `app/views/admin/audit/list.php` (new) — filter panel (table/action/user/date-range) + row list, each row with a collapsible "Detail perubahan" showing the old/new JSON snapshot; reachable only via the Dashboard link, not a bottom-navbar slot (Section 5 reserves the navbar for the 5 role-specific screens).
- `app/core/Router.php` — added `audit-log` route.
- `public/assets/css/style.css` — `.audit-detail`/`.audit-detail-block` for the collapsible diff view; everything else (stat cards, material cards, log rows, filter panel) reused existing Phase 4-8 classes as-is.
- Mid-phase cleanup, prompted by a local DB loss: `sql/schema.sql` had an invalid `AFTER stock_qty` clause left over from copy-pasting the Phase 6 manual `ALTER TABLE` directly into the `CREATE TABLE materials` statement -- `AFTER` isn't valid there and would throw a MySQL syntax error on any fresh import. Fixed in place; `schema.sql` is now the accurate canonical schema (`low_stock_threshold` + `audit_log` both included, matching what's actually been running since Phase 6/9) instead of drifting further from Phase 4's "post-Phase-4" label. `seed_data.sql` needed no changes -- it never had `audit_log` rows to begin with, consistent with "going forward only."
- **Test:** confirm dashboard numbers match actual DB stock, confirm low-stock item is visually flagged, confirm audit log shows entries for CRUD actions performed after this point (reworded from the doc's original "...for all CRUD actions performed so far" -- see decision (1) above).
- **Result:** Pass.
- **Deliverable:** `phase9-dashboard.zip`, full project re-zip after a local DB loss (see Section 0 note below).

### Phase 10 — Polish Pass
- Visual QA against the mobile/white/elegant direction (Section 5) across all screens, spacing/consistency pass, empty-state handling (e.g. no WOs assigned yet).
- **Test:** full click-through as both roles, visual sign-off.
- **Deliverable:** `phase10-polish.zip`

### Post-Phase-9 fixes (not numbered phases, done ahead of Phase 10)
- **Log Usage multi-material UX rework** — the original Phase 6 flow used a radio group (one material per submit) with a single shared qty/SN input block at the bottom of the whole card list, disconnected from whichever card was clicked. Reworked to checkboxes with an inline qty/SN input that expands directly under each selected card, and one submit now logs every checked material in one go (each still validated and written as its own transaction, so a stock hiccup on one item doesn't block the rest -- combined success/error feedback names exactly which item(s) had trouble). Out-of-stock/no-SN materials are now visibly disabled on the card itself instead of only failing after being picked. Touched: `app/views/teknisi/log-usage.php`, `public/assets/js/log-usage.js`, `UsageLogController::save()`, `style.css` (`.inline-usage-input`, `.log-usage-summary`, `.material-card-disabled`).
- **Profile page** — `navbar.php`'s teknisi nav has linked to `page=profile` since Phase 2, but no route/controller/view ever backed it (404). Built per the Section 6 spec ("Profile -- name, logout"): `app/controllers/ProfileController.php` (new), `app/views/profile.php` (new, reuses the existing `.wo-info-card` pattern), `profile` route added to `Router.php`. Deliberately minimal -- no edit/settings fields were ever spec'd.
- **`schema.sql` fix** — `low_stock_threshold`'s column definition carried an invalid `AFTER stock_qty` clause left over from the Phase 6 manual `ALTER TABLE`, which isn't valid inside `CREATE TABLE` and would throw a MySQL syntax error on a fresh import. Fixed; see Phase 9 entry above.

---

## 9. Open Items / Nice-to-Haves (explicitly out of scope for now, noted for later)

- Full WO replication (sales rep, package, test parameters, signatures) — currently scoped to a light reference table only.
- Password hashing / proper auth — intentionally skipped per your instruction (local testing via 2 browsers).
- Derived "total used" / point-in-time stock reporting — not needed now, achievable later via `SUM(qty_used)` query without schema changes.
- Barcode-style scan simulation for SN input on mobile — mentioned earlier as a demo flourish, not committed to a phase yet; flag if you want it added (likely fits into Phase 6).

---

**Next step:** confirm this document, then Phase 1 begins only after your explicit go-ahead per Ground Rule #1.