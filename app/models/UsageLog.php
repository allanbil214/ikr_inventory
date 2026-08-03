<?php
/**
 * UsageLog.php
 * Phase 6 -- core transaction table linking a Work Order to the
 * materials consumed on it (see handoff doc Section 3 `usage_logs`
 * and Section 8 Phase 6).
 *
 * create() is the important part: it re-checks stock server-side
 * inside a transaction (never trusts client-submitted stock numbers),
 * and for serial-tracked materials locks the specific SN row so two
 * technicians can't both claim the same serial in a race.
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../models/MaterialSerial.php';

class UsageLog
{
    /**
     * Active (non-deleted) usage_logs for a WO, joined with materials
     * for display. Promoted from WorkOrderController's private helper
     * now that Phase 6 needs the same query for the Log Usage flow too.
     */
    public static function getByWorkOrder(int $woId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT ul.id, ul.wo_id, ul.material_id, ul.serial_number, ul.qty_used, ul.created_at,
                    m.description AS material_description, m.unit, m.tracking_type
             FROM usage_logs ul
             JOIN materials m ON m.id = ul.material_id
             WHERE ul.wo_id = :wo_id AND ul.is_deleted = 0
             ORDER BY ul.created_at ASC"
        );
        $stmt->execute(['wo_id' => $woId]);
        return $stmt->fetchAll();
    }

    /**
     * Single log, joined with material + WO info needed for permission
     * checks (ownership, WO status) and for prefilling the Phase 7
     * edit form. Returns null for a non-existent id -- callers should
     * still separately guard against editing an already-soft-deleted
     * log (is_deleted is included in the row for that check).
     */
    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT ul.id, ul.wo_id, ul.technician_id, ul.material_id, ul.serial_number,
                    ul.qty_used, ul.is_deleted, ul.created_at,
                    m.description AS material_description, m.unit, m.tracking_type,
                    wo.wo_no, wo.status AS wo_status
             FROM usage_logs ul
             JOIN materials m ON m.id = ul.material_id
             JOIN work_orders wo ON wo.id = ul.wo_id
             WHERE ul.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Logs one material's usage against a WO, decrements stock, and
     * (for serial-tracked materials) flips the SN to 'used'. All in one
     * transaction so a failure partway through never leaves stock or
     * usage_logs out of sync.
     *
     * @param array $data keys: wo_id, technician_id, material_id,
     *                    serial_id (required if the material is
     *                    serial-tracked), qty_used (required if
     *                    quantity-tracked)
     * @throws InvalidArgumentException if the material doesn't exist
     * @throws RuntimeException if the requested SN/qty is no longer
     *                          available -- message is safe to show
     *                          the user directly
     * @return int the new usage_logs id
     */
    public static function create(array $data): int
    {
        $material = Material::find((int) $data['material_id']);
        if ($material === null) {
            throw new InvalidArgumentException('Material tidak ditemukan.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            if ($material['tracking_type'] === 'serial') {
                $logId = self::createSerialLog($pdo, $material, $data);
            } else {
                $logId = self::createQuantityLog($pdo, $material, $data);
            }

            $pdo->commit();
            return $logId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function createSerialLog(PDO $pdo, array $material, array $data): int
    {
        $serialId = (int) ($data['serial_id'] ?? 0);
        if ($serialId <= 0) {
            throw new RuntimeException('Pilih SN yang akan digunakan.');
        }

        // Lock the specific SN row so a concurrent submit can't also claim it.
        $stmt = $pdo->prepare(
            'SELECT * FROM material_serials WHERE id = :id AND material_id = :material_id FOR UPDATE'
        );
        $stmt->execute(['id' => $serialId, 'material_id' => $material['id']]);
        $serial = $stmt->fetch();

        if (!$serial || $serial['status'] !== 'available') {
            throw new RuntimeException('SN yang dipilih sudah tidak tersedia. Silakan pilih SN lain.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO usage_logs (wo_id, technician_id, material_id, serial_number, qty_used)
             VALUES (:wo_id, :technician_id, :material_id, :serial_number, NULL)'
        );
        $insert->execute([
            'wo_id'          => $data['wo_id'],
            'technician_id'  => $data['technician_id'],
            'material_id'    => $material['id'],
            'serial_number'  => $serial['serial_number'],
        ]);
        $logId = (int) $pdo->lastInsertId();

        MaterialSerial::markUsed($serialId, $logId);

        $pdo->prepare('UPDATE materials SET stock_qty = stock_qty - 1 WHERE id = :id')
            ->execute(['id' => $material['id']]);

        return $logId;
    }

    private static function createQuantityLog(PDO $pdo, array $material, array $data): int
    {
        $qtyUsed = isset($data['qty_used']) ? (float) $data['qty_used'] : 0;
        if ($qtyUsed <= 0) {
            throw new RuntimeException('Jumlah yang digunakan harus lebih dari 0.');
        }

        // Lock the material row and re-check stock server-side -- the
        // client-side max on the number input is a UX hint only, not
        // the source of truth.
        $stmt = $pdo->prepare('SELECT stock_qty FROM materials WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $material['id']]);
        $current = $stmt->fetch();

        if (!$current || (float) $current['stock_qty'] < $qtyUsed) {
            $remaining = rtrim(rtrim(number_format((float) ($current['stock_qty'] ?? 0), 2, '.', ''), '0'), '.');
            throw new RuntimeException("Stok tidak cukup. Sisa stok: {$remaining} {$material['unit']}.");
        }

        $insert = $pdo->prepare(
            'INSERT INTO usage_logs (wo_id, technician_id, material_id, serial_number, qty_used)
             VALUES (:wo_id, :technician_id, :material_id, NULL, :qty_used)'
        );
        $insert->execute([
            'wo_id'         => $data['wo_id'],
            'technician_id' => $data['technician_id'],
            'material_id'   => $material['id'],
            'qty_used'      => $qtyUsed,
        ]);
        $logId = (int) $pdo->lastInsertId();

        $pdo->prepare('UPDATE materials SET stock_qty = stock_qty - :qty WHERE id = :id')
            ->execute(['qty' => $qtyUsed, 'id' => $material['id']]);

        return $logId;
    }

    /**
     * Phase 7 -- soft-deletes a log and reverts its stock impact: for
     * quantity materials, adds qty_used back to stock_qty; for serial
     * materials, flips the consumed SN back to 'available' and bumps
     * stock_qty by 1. Row-locks both the log and the material (and, for
     * serial materials, the specific SN row) so this can't race with a
     * concurrent create/edit against the same material.
     *
     * Caller is responsible for authorization (admin vs teknisi-own-log,
     * WO status) -- this method only guards against double-deleting an
     * already-deleted log.
     *
     * @return bool false if the log doesn't exist or is already deleted
     */
    public static function delete(int $logId): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM usage_logs WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $logId]);
            $log = $stmt->fetch();

            if (!$log || (int) $log['is_deleted'] === 1) {
                $pdo->rollBack();
                return false;
            }

            $stmtM = $pdo->prepare('SELECT * FROM materials WHERE id = :id FOR UPDATE');
            $stmtM->execute(['id' => $log['material_id']]);
            $material = $stmtM->fetch();

            if ($material['tracking_type'] === 'serial') {
                $pdo->prepare(
                    "UPDATE material_serials SET status = 'available', used_in_log_id = NULL
                     WHERE used_in_log_id = :log_id"
                )->execute(['log_id' => $logId]);

                $pdo->prepare('UPDATE materials SET stock_qty = stock_qty + 1 WHERE id = :id')
                    ->execute(['id' => $material['id']]);
            } else {
                $pdo->prepare('UPDATE materials SET stock_qty = stock_qty + :qty WHERE id = :id')
                    ->execute(['qty' => $log['qty_used'], 'id' => $material['id']]);
            }

            $pdo->prepare(
                'UPDATE usage_logs SET is_deleted = 1, deleted_at = NOW() WHERE id = :id'
            )->execute(['id' => $logId]);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Phase 8 -- filterable History (teknisi, own logs) / Logs (admin,
     * all technicians) query. Every filter key is optional; omitted or
     * empty values are simply not applied.
     *
     * @param array $filters keys: technician_id, wo_id, category_id,
     *                       search (matches material item_code/description),
     *                       date_from, date_to (both compared against
     *                       DATE(ul.created_at), i.e. the day the log was
     *                       recorded), include_deleted (bool -- when false,
     *                       soft-deleted logs are excluded entirely; when
     *                       true, they're included so the view can render
     *                       them greyed-out/read-only)
     */
    public static function getFiltered(array $filters): array
    {
        $pdo = Database::getConnection();

        $sql = "SELECT ul.id, ul.wo_id, ul.technician_id, ul.material_id, ul.serial_number,
                       ul.qty_used, ul.is_deleted, ul.deleted_at, ul.created_at,
                       m.item_code, m.description AS material_description, m.unit,
                       m.tracking_type, m.category_id,
                       c.name AS category_name,
                       wo.wo_no, wo.status AS wo_status,
                       u.name AS technician_name
                FROM usage_logs ul
                JOIN materials m ON m.id = ul.material_id
                JOIN categories c ON c.id = m.category_id
                JOIN work_orders wo ON wo.id = ul.wo_id
                JOIN users u ON u.id = ul.technician_id
                WHERE 1=1";
        $params = [];

        if (empty($filters['include_deleted'])) {
            $sql .= ' AND ul.is_deleted = 0';
        }
        if (!empty($filters['technician_id'])) {
            $sql .= ' AND ul.technician_id = :technician_id';
            $params['technician_id'] = $filters['technician_id'];
        }
        if (!empty($filters['wo_id'])) {
            $sql .= ' AND ul.wo_id = :wo_id';
            $params['wo_id'] = $filters['wo_id'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND m.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (m.description LIKE :search_desc OR m.item_code LIKE :search_code)';
            $params['search_desc'] = '%' . $filters['search'] . '%';
            $params['search_code'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND DATE(ul.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND DATE(ul.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY ul.created_at DESC, ul.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Phase 7 -- edits the qty_used on a quantity-tracked log. Reverts
     * the old amount back into stock first, then re-validates the new
     * amount against that reverted figure, so a technician correcting
     * "used 30m" down to "used 10m" (or up to "used 40m") is always
     * checked against true availability rather than the stale pre-edit
     * stock_qty.
     *
     * @throws RuntimeException if the log/material can't be found, is
     *                          already deleted, isn't quantity-tracked,
     *                          or the new amount doesn't fit in stock
     *                          once the old amount is reverted -- message
     *                          is safe to show the user directly.
     */
    public static function updateQuantity(int $logId, float $newQty): void
    {
        if ($newQty <= 0) {
            throw new RuntimeException('Jumlah yang digunakan harus lebih dari 0.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM usage_logs WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $logId]);
            $log = $stmt->fetch();

            if (!$log || (int) $log['is_deleted'] === 1) {
                throw new RuntimeException('Log tidak ditemukan atau sudah dihapus.');
            }

            $stmtM = $pdo->prepare('SELECT * FROM materials WHERE id = :id FOR UPDATE');
            $stmtM->execute(['id' => $log['material_id']]);
            $material = $stmtM->fetch();

            if (!$material || $material['tracking_type'] !== 'quantity') {
                throw new RuntimeException('Material ini bukan material quantity-tracked.');
            }

            $oldQty = (float) $log['qty_used'];
            $availableAfterRevert = (float) $material['stock_qty'] + $oldQty;

            if ($availableAfterRevert < $newQty) {
                $remaining = rtrim(rtrim(number_format($availableAfterRevert, 2, '.', ''), '0'), '.');
                throw new RuntimeException("Stok tidak cukup. Sisa stok yang bisa dipakai: {$remaining} {$material['unit']}.");
            }

            $newStock = $availableAfterRevert - $newQty;

            $pdo->prepare('UPDATE materials SET stock_qty = :stock WHERE id = :id')
                ->execute(['stock' => $newStock, 'id' => $material['id']]);

            $pdo->prepare('UPDATE usage_logs SET qty_used = :qty WHERE id = :id')
                ->execute(['qty' => $newQty, 'id' => $logId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Phase 7 -- edits a serial-tracked log to point at a different SN
     * (e.g. technician grabbed the wrong physical unit). Reverts the
     * currently-attached SN to 'available', locks and validates the
     * newly-chosen SN, and re-points the log + SN status accordingly.
     * Net stock_qty is unchanged either way (still exactly one unit
     * consumed), so this never touches materials.stock_qty.
     *
     * No-ops cleanly if the "new" SN is actually the one already
     * attached to this log (nothing to revert or reassign).
     *
     * @throws RuntimeException if the log can't be found/is deleted,
     *                          isn't serial-tracked, or the chosen SN
     *                          isn't available (belongs to a different
     *                          material, or already used elsewhere) --
     *                          message is safe to show the user directly.
     */
    public static function updateSerial(int $logId, int $newSerialId): void
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM usage_logs WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $logId]);
            $log = $stmt->fetch();

            if (!$log || (int) $log['is_deleted'] === 1) {
                throw new RuntimeException('Log tidak ditemukan atau sudah dihapus.');
            }

            $stmtCur = $pdo->prepare('SELECT * FROM material_serials WHERE used_in_log_id = :log_id FOR UPDATE');
            $stmtCur->execute(['log_id' => $logId]);
            $currentSerial = $stmtCur->fetch();

            if ($currentSerial && (int) $currentSerial['id'] === $newSerialId) {
                // Same SN re-submitted -- nothing to change.
                $pdo->commit();
                return;
            }

            $stmtNew = $pdo->prepare(
                'SELECT * FROM material_serials WHERE id = :id AND material_id = :material_id FOR UPDATE'
            );
            $stmtNew->execute(['id' => $newSerialId, 'material_id' => $log['material_id']]);
            $newSerial = $stmtNew->fetch();

            if (!$newSerial || $newSerial['status'] !== 'available') {
                throw new RuntimeException('SN yang dipilih sudah tidak tersedia. Silakan pilih SN lain.');
            }

            if ($currentSerial) {
                $pdo->prepare(
                    "UPDATE material_serials SET status = 'available', used_in_log_id = NULL WHERE id = :id"
                )->execute(['id' => $currentSerial['id']]);
            }

            $pdo->prepare(
                "UPDATE material_serials SET status = 'used', used_in_log_id = :log_id WHERE id = :id"
            )->execute(['log_id' => $logId, 'id' => $newSerialId]);

            $pdo->prepare('UPDATE usage_logs SET serial_number = :sn WHERE id = :id')
                ->execute(['sn' => $newSerial['serial_number'], 'id' => $logId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
