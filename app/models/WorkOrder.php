<?php
/**
 * WorkOrder.php
 * Phase 5. Light reference version of a Work Order (see handoff doc
 * Section 3) -- ties usage logs to a customer/technician, not a full
 * replica of the paper form.
 */

require_once __DIR__ . '/../core/Database.php';

class WorkOrder
{
    /**
     * @param string|null $status      'open' | 'completed' | null (all)
     * @param int|null    $technicianId filter by assigned technician
     */
    public static function getAll(?string $status = null, ?int $technicianId = null): array
    {
        $pdo = Database::getConnection();

        $sql = 'SELECT wo.*, u.name AS technician_name
                FROM work_orders wo
                JOIN users u ON u.id = wo.technician_id
                WHERE 1=1';
        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= ' AND wo.status = :status';
            $params['status'] = $status;
        }

        if ($technicianId !== null) {
            $sql .= ' AND wo.technician_id = :technician_id';
            $params['technician_id'] = $technicianId;
        }

        $sql .= ' ORDER BY wo.wo_date DESC, wo.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count of WOs in a given status, for the Phase 9 Dashboard's
     * aggregate stat card (open WO count). A plain COUNT query rather
     * than count(getAll()) so this doesn't have to pull every WO's
     * joined technician_name just to size the result.
     */
    public static function countByStatus(string $status): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM work_orders WHERE status = :status');
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetch()['total'];
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT wo.*, u.name AS technician_name
             FROM work_orders wo
             JOIN users u ON u.id = wo.technician_id
             WHERE wo.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByWoNo(string $woNo): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM work_orders WHERE wo_no = :wo_no LIMIT 1');
        $stmt->execute(['wo_no' => $woNo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Assigned + still-open WOs for a technician -- powers the "assigned
     * open WOs" preview added to teknisi Home in Phase 5, ahead of the
     * full teknisi Home build (stock snapshot + Log Usage CTA) in Phase 6.
     */
    public static function getAssignedOpen(int $technicianId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM work_orders
             WHERE technician_id = :technician_id AND status = 'open'
             ORDER BY wo_date ASC, id ASC"
        );
        $stmt->execute(['technician_id' => $technicianId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO work_orders (wo_no, wo_date, technician_id, customer_name, customer_address, port_fat, status, notes)
             VALUES (:wo_no, :wo_date, :technician_id, :customer_name, :customer_address, :port_fat, :status, :notes)'
        );
        $stmt->execute([
            'wo_no'             => $data['wo_no'],
            'wo_date'           => $data['wo_date'],
            'technician_id'     => $data['technician_id'],
            'customer_name'     => $data['customer_name'],
            'customer_address'  => $data['customer_address'] ?? null,
            'port_fat'          => $data['port_fat'] ?? null,
            'status'            => $data['status'] ?? 'open',
            'notes'             => $data['notes'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Edits WO fields, including status -- Phase 5 confirmed manual
     * open/completed toggling is in scope for this phase.
     */
    public static function update(int $id, array $data): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE work_orders
             SET wo_no = :wo_no,
                 wo_date = :wo_date,
                 technician_id = :technician_id,
                 customer_name = :customer_name,
                 customer_address = :customer_address,
                 port_fat = :port_fat,
                 status = :status,
                 notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            'id'                => $id,
            'wo_no'             => $data['wo_no'],
            'wo_date'           => $data['wo_date'],
            'technician_id'     => $data['technician_id'],
            'customer_name'     => $data['customer_name'],
            'customer_address'  => $data['customer_address'] ?? null,
            'port_fat'          => $data['port_fat'] ?? null,
            'status'            => $data['status'],
            'notes'             => $data['notes'] ?? null,
        ]);
    }

    /**
     * Flips a WO's status between 'open' and 'completed'.
     */
    public static function toggleStatus(int $id): void
    {
        $wo = self::find($id);
        if ($wo === null) {
            return;
        }

        $newStatus = $wo['status'] === 'open' ? 'completed' : 'open';

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE work_orders SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $newStatus, 'id' => $id]);
    }
}