<?php
/**
 * profile.php
 * Expects: $user. Deliberately minimal per handoff doc Section 6
 * ("Profile -- name, logout") -- no edit/settings scope was ever
 * specified, so this isn't guessing at fields that aren't asked for.
 */
?>
<div class="container">
    <div class="topbar">
        <span class="topbar-greeting">Halo, <?= htmlspecialchars($user['name']) ?></span>
        <a href="index.php?page=logout" class="topbar-logout">Logout</a>
    </div>

    <h2>Profile</h2>

    <div class="wo-info-card">
        <div class="wo-info-row">
            <span class="wo-info-label">Nama</span>
            <span class="wo-info-value"><?= htmlspecialchars($user['name']) ?></span>
        </div>
        <div class="wo-info-row">
            <span class="wo-info-label">Username</span>
            <span class="wo-info-value"><?= htmlspecialchars($user['username']) ?></span>
        </div>
        <div class="wo-info-row">
            <span class="wo-info-label">Role</span>
            <span class="wo-info-value"><?= $user['role'] === 'admin' ? 'Admin' : 'Teknisi' ?></span>
        </div>
    </div>

    <a href="index.php?page=logout" class="btn-primary" style="display:block; text-align:center; text-decoration:none;">
        Logout
    </a>
</div>
