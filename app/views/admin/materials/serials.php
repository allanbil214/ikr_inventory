<?php
/**
 * admin/materials/serials.php
 * Expects: $material, $serialList, $counts, $flash, $user
 */
?>
<div class="container">
    <div class="topbar">
        <a href="index.php?page=materials" class="topbar-back">&larr; Materials</a>
    </div>

    <h2><?= htmlspecialchars($material['description']) ?></h2>
    <p class="material-meta">
        <span class="mono-code"><?= htmlspecialchars($material['item_code']) ?></span>
        · <?= htmlspecialchars($material['category_name']) ?><?= $material['merk'] ? ' · ' . htmlspecialchars($material['merk']) : '' ?>
    </p>

    <?php if ($flash && !empty($flash['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($flash['success']) ?></div>
    <?php endif; ?>
    <?php if ($flash && !empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $err): ?>
            <div class="alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="stat-card-row">
        <div class="stat-card">
            <span class="stat-number"><?= $counts['available'] ?></span>
            <span class="stat-label">Available</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $counts['used'] ?></span>
            <span class="stat-label">Used</span>
        </div>
    </div>

    <form method="POST" action="index.php?page=material-serial-add" class="add-serial-form">
        <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
        <input type="text" name="serial_number" placeholder="Tambah SN baru..." required>
        <button type="submit" class="btn-small btn-add">+ Tambah</button>
    </form>

    <div class="serial-list">
        <?php if (empty($serialList)): ?>
            <p class="placeholder-note">Belum ada SN untuk material ini.</p>
        <?php endif; ?>

        <?php foreach ($serialList as $sn): ?>
            <div class="serial-row">
                <span class="mono-code"><?= htmlspecialchars($sn['serial_number']) ?></span>
                <span class="badge badge-<?= $sn['status'] === 'available' ? 'serial' : 'used' ?>">
                    <?= $sn['status'] === 'available' ? 'Available' : 'Used' ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
