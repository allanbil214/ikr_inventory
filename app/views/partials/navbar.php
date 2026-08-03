<?php
/**
 * partials/navbar.php
 * Bottom navbar, role-aware. Requires an active Auth session (only
 * included on already-protected pages).
 */

$role = Auth::user()['role'] ?? null;
$currentPage = $_GET['page'] ?? '';

$adminNav = [
    ['page' => 'dashboard',   'label' => 'Home',      'icon' => '🏠'],
    ['page' => 'materials',   'label' => 'Materials',  'icon' => '📦'],
    ['page' => 'logs',        'label' => 'Logs',       'icon' => '📋'],
    ['page' => 'workorders',  'label' => 'WOs',        'icon' => '🧾'],
];

$teknisiNav = [
    ['page' => 'home',        'label' => 'Home',       'icon' => '🏠'],
    ['page' => 'log-usage',   'label' => 'Log Usage',  'icon' => '➕'],
    ['page' => 'history',     'label' => 'History',    'icon' => '🕒'],
    ['page' => 'profile',     'label' => 'Profile',    'icon' => '👤'],
];

$navItems = $role === 'admin' ? $adminNav : $teknisiNav;
?>
<nav class="bottom-navbar">
    <?php foreach ($navItems as $item): ?>
        <a href="index.php?page=<?= htmlspecialchars($item['page']) ?>"
           class="nav-item<?= $currentPage === $item['page'] ? ' active' : '' ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
