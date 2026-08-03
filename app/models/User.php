<?php
/**
 * User.php
 * Minimal model for auth purposes. Full CRUD not in scope (no user
 * management screen defined in the project).
 */

require_once __DIR__ . '/../core/Database.php';

class User
{
    public static function findByUsername(string $username): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * All users with role = teknisi, for the WO assign dropdown/filter
     * (Section 6 admin screen 5, Section 8 Phase 5).
     */
    public static function getAllTeknisi(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, name, username FROM users WHERE role = 'teknisi' ORDER BY name ASC");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Every user regardless of role, for the Phase 9 Audit Log's "user"
     * filter -- unlike getAllTeknisi(), admin actions (Tias herself) also
     * appear in audit_log and need to be filterable.
     */
    public static function getAll(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, name, username, role FROM users ORDER BY name ASC');
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

