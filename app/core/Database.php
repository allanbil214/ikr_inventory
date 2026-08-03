<?php
/**
 * Database.php
 * Minimal PDO connection helper for the IKR Inventory mockup.
 * Default XAMPP credentials: host=localhost, user=root, password=''
 */

class Database
{
    // --- Connection config ---
    private static string $host = 'localhost';
    private static string $dbName = 'ikr_inventory';
    private static string $user = 'root';
    private static string $pass = '';
    private static string $charset = 'utf8mb4';

    private static ?PDO $connection = null;

    /**
     * Returns a shared PDO instance (singleton).
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbName . ";charset=" . self::$charset;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$connection = new PDO($dsn, self::$user, self::$pass, $options);
            } catch (PDOException $e) {
                // For a local mockup, surfacing the error directly is fine.
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }
}
