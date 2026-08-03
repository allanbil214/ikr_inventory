<?php
/**
 * db-test.php
 * TEMPORARY — verifies Database.php can connect and schema.sql was imported correctly.
 * Delete this file once Phase 1 testing is done; it is not part of the app.
 */

require_once __DIR__ . '/../app/core/Database.php';

try {
    $pdo = Database::getConnection();
    echo "Connected to ikr_inventory successfully.<br><br>";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Tables found (" . count($tables) . "):<br><ul>";
    foreach ($tables as $table) {
        echo "<li>{$table}</li>";
    }
    echo "</ul>";

    $expected = ['users', 'materials', 'material_serials', 'work_orders', 'usage_logs', 'audit_log'];
    $missing = array_diff($expected, $tables);

    if (empty($missing)) {
        echo "<br>All 6 expected tables are present.";
    } else {
        echo "<br>Missing tables: " . implode(', ', $missing);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
