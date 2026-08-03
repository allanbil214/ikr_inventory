<?php
/**
 * admin/materials/list.php
 * Expects: $materials, $categories, $flash, $user, $_GET['q'], $_GET['category']
 */
$currentSearch = htmlspecialchars($_GET['q'] ?? '');
$currentCategory = $_GET['category'] ?? '';
?>
<div class="container">
    <div class="topbar">
        <span class="topbar-greeting">Halo, <?= htmlspecialchars($user['name']) ?></span>
        <a href="index.php?page=logout" class="topbar-logout">Logout</a>
    </div>

    <div class="section-header">
        <h2>Materials</h2>
        <a href="index.php?page=material-form" class="btn-small btn-add">+ Tambah</a>
    </div>

    <?php if ($flash && !empty($flash['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($flash['success']) ?></div>
    <?php endif; ?>
    <?php if ($flash && !empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $err): ?>
            <div class="alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="GET" action="index.php" class="search-bar">
        <input type="hidden" name="page" value="materials">
        <?php if ($currentCategory !== ''): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($currentCategory) ?>">
        <?php endif; ?>
        <input type="text" name="q" value="<?= $currentSearch ?>" placeholder="Cari kode, deskripsi, merk...">
        <button type="submit" class="btn-search">Cari</button>
    </form>

    <div class="chip-row">
        <a href="index.php?page=materials<?= $currentSearch !== '' ? '&q=' . urlencode($currentSearch) : '' ?>"
           class="chip<?= $currentCategory === '' ? ' active' : '' ?>">Semua</a>
        <?php foreach ($categories as $cat): ?>
            <a href="index.php?page=materials&category=<?= $cat['id'] ?><?= $currentSearch !== '' ? '&q=' . urlencode($currentSearch) : '' ?>"
               class="chip<?= (string) $currentCategory === (string) $cat['id'] ? ' active' : '' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($materials)): ?>
        <p class="placeholder-note">Tidak ada material yang cocok.</p>
    <?php endif; ?>

    <div class="card-list">
        <?php foreach ($materials as $m): ?>
            <div class="material-card">
                <div class="material-card-top">
                    <span class="mono-code"><?= htmlspecialchars($m['item_code']) ?></span>
                    <span class="badge badge-<?= $m['tracking_type'] ?>">
                        <?= $m['tracking_type'] === 'serial' ? 'Serial' : 'Quantity' ?>
                    </span>
                </div>
                <div class="material-desc"><?= htmlspecialchars($m['description']) ?></div>
                <div class="material-meta">
                    <?= htmlspecialchars($m['category_name']) ?><?= $m['merk'] ? ' · ' . htmlspecialchars($m['merk']) : '' ?>
                </div>
                <div class="material-stock">
                    <strong><?= rtrim(rtrim(number_format((float) $m['stock_qty'], 2, '.', ''), '0'), '.') ?></strong>
                    <span class="stock-unit"><?= htmlspecialchars($m['unit']) ?></span>
                    <span class="stock-label">stok</span>
                </div>

                <div class="material-actions">
                    <a href="index.php?page=material-form&id=<?= $m['id'] ?>" class="btn-small">Edit</a>

                    <?php if ($m['tracking_type'] === 'serial'): ?>
                        <a href="index.php?page=material-serials&id=<?= $m['id'] ?>" class="btn-small">Lihat SN</a>
                    <?php else: ?>
                        <form method="POST" action="index.php?page=material-stock-adjust" class="stock-adjust-form">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <input type="number" step="0.01" min="0" name="new_stock_qty"
                                   value="<?= htmlspecialchars((string) $m['stock_qty']) ?>" aria-label="Stok baru">
                            <button type="submit" class="btn-small">Simpan Stok</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
