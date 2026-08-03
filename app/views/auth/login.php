<div class="auth-page">
    <div class="auth-card">
        <h1 class="auth-title">IKR Inventory</h1>
        <p class="auth-subtitle">Masuk untuk melanjutkan</p>

        <?php if (!empty($error)): ?>
            <div class="alert-error">Username atau password salah.</div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=do_login">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit" class="btn-primary">Login</button>
        </form>
    </div>
</div>
