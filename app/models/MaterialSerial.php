<?php
/**
 * MaterialSerial.php
 * Only populated for materials where tracking_type = 'serial'.
 * Full usage/consumption logic (marking a serial 'used' against a
 * usage_log) lands in Phase 6 -- this model currently only supports
 * what Phase 4 needs: listing SNs for the admin sub-view, and adding
 * new SNs to stock (both arrive as 'available').
 */

require_once __DIR__ . '/../core/Database.php';

class MaterialSerial
{
    public static function getByMaterial(int $materialId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM material_serials
             WHERE material_id = :material_id
             ORDER BY status ASC, serial_number ASC'
        );
        $stmt->execute(['material_id' => $materialId]);
        return $stmt->fetchAll();
    }

    /**
     * Available (not yet used) SNs for a material -- powers the SN picker
     * on the teknisi Log Usage screen (Phase 6).
     */
    public static function getAvailableByMaterial(int $materialId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, serial_number FROM material_serials
             WHERE material_id = :material_id AND status = 'available'
             ORDER BY serial_number ASC"
        );
        $stmt->execute(['material_id' => $materialId]);
        return $stmt->fetchAll();
    }

    /**
     * Flips a specific SN to 'used' and links it to the usage_log that
     * consumed it. Caller (UsageLog::create) is responsible for running
     * this inside the same transaction as the log insert + stock decrement.
     */
    public static function markUsed(int $serialId, int $logId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE material_serials SET status = 'used', used_in_log_id = :log_id WHERE id = :id"
        );
        $stmt->execute(['log_id' => $logId, 'id' => $serialId]);
    }

    /**
     * SNs selectable for a Phase 7 log edit: every currently-available
     * SN for the material, plus (if not already in that list) the SN
     * presently attached to $currentLogId -- its status is 'used' so
     * it wouldn't otherwise show up in getAvailableByMaterial(), but it
     * must stay selectable so "keep the same SN" is a valid choice.
     */
    public static function getSelectableForEdit(int $materialId, int $currentLogId): array
    {
        $available = self::getAvailableByMaterial($materialId);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, serial_number FROM material_serials WHERE used_in_log_id = :log_id LIMIT 1'
        );
        $stmt->execute(['log_id' => $currentLogId]);
        $current = $stmt->fetch();

        if ($current && !in_array($current['id'], array_column($available, 'id'), true)) {
            array_unshift($available, $current);
        }

        return $available;
    }

    public static function countByStatus(int $materialId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT status, COUNT(*) AS total
             FROM material_serials
             WHERE material_id = :material_id
             GROUP BY status"
        );
        $stmt->execute(['material_id' => $materialId]);

        $counts = ['available' => 0, 'used' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function serialExists(string $serialNumber): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM material_serials WHERE serial_number = :sn LIMIT 1');
        $stmt->execute(['sn' => $serialNumber]);
        return (bool) $stmt->fetch();
    }

    /**
     * Adds a new SN to stock for a serial-tracked material. New units
     * always arrive as 'available'; caller is responsible for calling
     * Material::syncSerialStock() afterward to keep stock_qty in sync.
     */
    public static function create(int $materialId, string $serialNumber): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO material_serials (material_id, serial_number, status, used_in_log_id)
             VALUES (:material_id, :serial_number, 'available', NULL)"
        );
        $stmt->execute([
            'material_id'   => $materialId,
            'serial_number' => $serialNumber,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
