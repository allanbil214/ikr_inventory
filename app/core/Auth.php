<?php
/**
 * Auth.php
 * Plain PHP session helper. No password hashing per project spec (local mockup).
 */

class Auth
{
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::ensureSession();
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'name'     => $user['name'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ];
    }

    public static function logout(): void
    {
        self::ensureSession();
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        self::ensureSession();
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        self::ensureSession();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Redirects to login if not authenticated.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: index.php?page=login');
            exit;
        }
    }

    /**
     * Redirects to login if not authenticated, or to the user's own
     * landing page if authenticated but with the wrong role.
     */
    public static function requireRole(string $role): void
    {
        self::requireLogin();

        if (self::user()['role'] !== $role) {
            $ownLanding = self::user()['role'] === 'admin' ? 'dashboard' : 'home';
            header('Location: index.php?page=' . $ownLanding);
            exit;
        }
    }
}
