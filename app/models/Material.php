<?php
/**
 * Material.php
 * Phase 4. Master list, one row per material *type*. Joins categories
 * for display (name + prefix) since Phase 4 introduced categories.id
 * as the FK instead of a free-text category column (see handoff doc
 * Section 3 / migration_phase4_categories.sql).
 */

require_once __DIR__ . '/../core/Database.php';

class Material
{
    /**
     * @param string|null $search  matches item_code, description, or merk
     * @param int|null    $categoryId  filter by category
     */
    public static function getAll(?string $search = null, ?int $categoryId = null): array
    {
        $pdo = Database::getConnection();

        $sql = 'SELECT m.*, c.name AS category_name, c.code_prefix AS category_prefix
                FROM materials m
                JOIN categories c ON c.id = m.category_id
                WHERE 1=1';
        $params = [];

        if ($search !== null && $search !== '') {
            // Real (non-emulated) prepared statements don't allow the same
            // named placeholder to be reused more than once in a query, so
            // each LIKE clause gets its own placeholder bound to the same value.
            $sql .= ' AND (m.item_code LIKE :search1 OR m.description LIKE :search2 OR m.merk LIKE :search3)';
            $searchTerm = '%' . $search . '%';
            $params['search1'] = $searchTerm;
            $params['search2'] = $searchTerm;
            $params['search3'] = $searchTerm;
        }

        if ($categoryId !== null) {
            $sql .= ' AND m.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $sql .= ' ORDER BY c.name ASC, m.item_code ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Materials at or below their configured low_stock_threshold, for the
     * teknisi Home stock snapshot (Phase 6). Materials with a NULL
     * threshold never show up here (no threshold configured = no warning).
     */
    public static function getLowStock(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT m.*, c.name AS category_name, c.code_prefix AS category_prefix
             FROM materials m
             JOIN categories c ON c.id = m.category_id
             WHERE m.low_stock_threshold IS NOT NULL
               AND m.stock_qty <= m.low_stock_threshold
             ORDER BY (m.stock_qty / NULLIF(m.low_stock_threshold, 0)) ASC, m.item_code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Total stock summed per category, for the Phase 9 Dashboard's
     * per-category stock overview cards. Categories with no materials
     * yet are excluded (inner join) rather than shown as an empty
     * zero-stock card. `units` is a distinct list in case a category
     * ever ends up mixing "pcs" and "meter" -- the view shows it
     * plainly instead of silently summing incompatible units together.
     */
    public static function getStockByCategory(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            "SELECT c.id AS category_id, c.name AS category_name,
                    SUM(m.stock_qty) AS total_stock,
                    COUNT(m.id) AS material_count,
                    GROUP_CONCAT(DISTINCT m.unit ORDER BY m.unit SEPARATOR ', ') AS units
             FROM categories c
             JOIN materials m ON m.category_id = c.id
             GROUP BY c.id, c.name
             ORDER BY c.name ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Total number of material *types* (rows in this master table), for
     * the Phase 9 Dashboard's aggregate stat card. Deliberately not the
     * same number as total stock -- this counts distinct materials, not units.
     */
    public static function countAll(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT COUNT(*) AS total FROM materials');
        return (int) $stmt->fetch()['total'];
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT m.*, c.name AS category_name, c.code_prefix AS category_prefix
             FROM materials m
             JOIN categories c ON c.id = m.category_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array $data keys: category_id, description, merk, tracking_type,
     *                    unit, stock_qty (ignored/forced to 0 for serial-tracked,
     *                    since serial stock is derived from material_serials)
     * @return int newly created material id
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();

        $trackingType = $data['tracking_type'];
        $stockQty = $trackingType === 'serial' ? 0 : (float) ($data['stock_qty'] ?? 0);

        $itemCode = self::generateItemCode((int) $data['category_id']);
        $lowStockThreshold = isset($data['low_stock_threshold']) && $data['low_stock_threshold'] !== null
            ? (float) $data['low_stock_threshold']
            : null;

        $stmt = $pdo->prepare(
            'INSERT INTO materials (item_code, category_id, description, merk, tracking_type, unit, stock_qty, low_stock_threshold)
             VALUES (:item_code, :category_id, :description, :merk, :tracking_type, :unit, :stock_qty, :low_stock_threshold)'
        );

        try {
            $stmt->execute([
                'item_code'           => $itemCode,
                'category_id'         => $data['category_id'],
                'description'         => $data['description'],
                'merk'                => $data['merk'] ?? null,
                'tracking_type'       => $trackingType,
                'unit'                => $data['unit'],
                'stock_qty'           => $stockQty,
                'low_stock_threshold' => $lowStockThreshold,
            ]);
        } catch (PDOException $e) {
            // Extremely unlikely collision on item_code unique constraint;
            // regenerate once and retry.
            if ($e->getCode() === '23000') {
                $itemCode = self::generateItemCode((int) $data['category_id']);
                $stmt->execute([
                    'item_code'           => $itemCode,
                    'category_id'         => $data['category_id'],
                    'description'         => $data['description'],
                    'merk'                => $data['merk'] ?? null,
                    'tracking_type'       => $trackingType,
                    'unit'                => $data['unit'],
                    'stock_qty'           => $stockQty,
                    'low_stock_threshold' => $lowStockThreshold,
                ]);
            } else {
                throw $e;
            }
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * Edits material master fields. tracking_type and item_code are
     * intentionally not editable here (changing tracking_type after
     * creation would orphan/duplicate stock tracking; item_code is a
     * generated identifier). stock_qty is only updated here for
     * quantity-tracked materials -- serial-tracked stock is derived
     * from material_serials and managed via the SN sub-view instead.
     */
    public static function update(int $id, array $data): void
    {
        $pdo = Database::getConnection();

        $material = self::find($id);
        if ($material === null) {
            return;
        }

        $sql = 'UPDATE materials
                SET category_id = :category_id,
                    description = :description,
                    merk = :merk,
                    unit = :unit,
                    low_stock_threshold = :low_stock_threshold';
        $params = [
            'id'                  => $id,
            'category_id'         => $data['category_id'],
            'description'         => $data['description'],
            'merk'                => $data['merk'] ?? null,
            'unit'                => $data['unit'],
            'low_stock_threshold' => isset($data['low_stock_threshold']) && $data['low_stock_threshold'] !== null
                ? (float) $data['low_stock_threshold']
                : null,
        ];

        if ($material['tracking_type'] === 'quantity' && isset($data['stock_qty'])) {
            $sql .= ', stock_qty = :stock_qty';
            $params['stock_qty'] = (float) $data['stock_qty'];
        }

        $sql .= ' WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Manual stock adjustment -- quantity-tracked materials only.
     * Sets stock_qty to an absolute new value (simplest, least
     * ambiguous mockup behavior for an admin correcting a count).
     */
    public static function adjustStock(int $id, float $newQty): bool
    {
        $material = self::find($id);
        if ($material === null || $material['tracking_type'] !== 'quantity') {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE materials SET stock_qty = :qty WHERE id = :id');
        $stmt->execute(['qty' => $newQty, 'id' => $id]);
        return true;
    }

    /**
     * Recalculates stock_qty for a serial-tracked material from the
     * count of 'available' rows in material_serials. Called after
     * adding a new SN so the materials list stays in sync without
     * the caller having to remember to bump stock_qty separately.
     */
    public static function syncSerialStock(int $materialId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE materials m
             SET stock_qty = (
                 SELECT COUNT(*) FROM material_serials
                 WHERE material_id = :material_id AND status = 'available'
             )
             WHERE m.id = :material_id2"
        );
        $stmt->execute(['material_id' => $materialId, 'material_id2' => $materialId]);
    }

    /**
     * Generates the next item_code for a category: {prefix}{8-digit sequence}
     * e.g. AONT00000011, ICAB00000133 -- matches the existing seed pattern.
     * The sequence is derived from the highest existing number under that
     * prefix, not a stored counter, so it stays correct even if rows are
     * deleted or seeded out of order.
     */
    public static function generateItemCode(int $categoryId): string
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT code_prefix FROM categories WHERE id = :id');
        $stmt->execute(['id' => $categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            throw new InvalidArgumentException("Category {$categoryId} not found");
        }

        $prefix = $category['code_prefix'];
        $prefixLen = strlen($prefix);

        $stmt = $pdo->prepare(
            'SELECT MAX(CAST(SUBSTRING(item_code, :offset) AS UNSIGNED)) AS max_seq
             FROM materials
             WHERE item_code LIKE :like'
        );
        $stmt->execute([
            'offset' => $prefixLen + 1,
            'like'   => $prefix . '%',
        ]);
        $row = $stmt->fetch();

        $nextSeq = ((int) ($row['max_seq'] ?? 0)) + 1;

        return $prefix . str_pad((string) $nextSeq, 8, '0', STR_PAD_LEFT);
    }
}
