<?php
/**
 * AuthController.php
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/User.php';

class AuthController
{
    public function showLogin(): void
    {
        // Already logged in? bounce to the right landing page.
        if (Auth::check()) {
            $role = Auth::user()['role'];
            header('Location: index.php?page=' . ($role === 'admin' ? 'dashboard' : 'home'));
            exit;
        }

        $error = $_GET['error'] ?? null;
        $pageTitle = 'Login';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/auth/login.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = User::findByUsername($username);

        // Plain text compare per project spec (no hashing).
        if (!$user || $user['password'] !== $password) {
            header('Location: index.php?page=login&error=1');
            exit;
        }

        Auth::login($user);
        header('Location: index.php?page=' . ($user['role'] === 'admin' ? 'dashboard' : 'home'));
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: index.php?page=login');
        exit;
    }
}
