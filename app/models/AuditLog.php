<?php
/**
 * AuditLog.php
 * Phase 9. Records CRUD actions going forward only (see handoff doc
 * Section 3 `audit_log` and Phase 9 note): Phases 4-7 never wrote to
 * this table, so there is no real history to backfill for the seeded
 * data / past actions -- fabricating entries for them would just be
 * invented records, not a real audit trail. record() is called from
 * the controllers that actually perform materials / work_orders /
 * usage_logs mutations (see MaterialController, WorkOrderController,
 * UsageLogController), never from inside a model transaction, so a
 * logging failure can never roll back the real mutation it's
 * describing.
 */

require_once __DIR__ . '/../core/Database.php';

class AuditLog
{
    /**
     * @param int        $userId    acting user (Auth::user()['id'])
     * @param string     $action    'create' | 'update' | 'delete'
     * @param string     $tableName 'materials' | 'work_orders' | 'usage_logs'
     * @param int        $recordId  primary key of the affected row
     * @param array|null $oldValue  snapshot before the change (null on create)
     * @param array|null $newValue  snapshot after the change (null on delete)
     */
    public static function record(
        int $userId,
        string $action,
        string $tableName,
        int $recordId,
        ?array $oldValue = null,
        ?array $newValue = null
    ): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, action, table_name, record_id, old_value, new_value)
             VALUES (:user_id, :action, :table_name, :record_id, :old_value, :new_value)'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'action'     => $action,
            'table_name' => $tableName,
            'record_id'  => $recordId,
            'old_value'  => $oldValue !== null ? json_encode($oldValue) : null,
            'new_value'  => $newValue !== null ? json_encode($newValue) : null,
        ]);
    }

    /**
     * Latest N entries, joined with the acting user's name, for the
     * Dashboard "recent activity" feed.
     */
    public static function getRecent(int $limit = 8): array
    {
        $limit = max(1, $limit);
        $pdo = Database::getConnection();
        // LIMIT can't be a bound param under real (non-emulated) prepared
        // statements in all MySQL driver versions -- $limit is caller-
        // controlled (an int cast above), so interpolating it is safe.
        $stmt = $pdo->prepare(
            "SELECT a.*, u.name AS user_name
             FROM audit_log a
             JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Full filterable Audit Log page query. All filters optional.
     *
     * @param array $filters keys: table_name, action, user_id, date_from,
     *                       date_to (both compared against DATE(created_at))
     */
    public static function getFiltered(array $filters): array
    {
        $pdo = Database::getConnection();

        $sql = "SELECT a.*, u.name AS user_name
                FROM audit_log a
                JOIN users u ON u.id = a.user_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['table_name'])) {
            $sql .= ' AND a.table_name = :table_name';
            $params['table_name'] = $filters['table_name'];
        }
        if (!empty($filters['action'])) {
            $sql .= ' AND a.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= ' AND a.user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND DATE(a.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND DATE(a.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY a.created_at DESC, a.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
