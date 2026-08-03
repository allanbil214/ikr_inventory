<?php
/**
 * admin/dashboard.php
 * Phase 9. Expects: $user, $totalMaterialTypes, $openWoCount,
 * $lowStockMaterials, $stockByCategory, $recentActivity.
 */
$actionLabels = [
    'create' => 'Dibuat',
    'update' => 'Diubah',
    'delete' => 'Dihapus',
];
$tableLabels = [
    'materials'   => 'Material',
    'work_orders' => 'Work Order',
    'usage_logs'  => 'Usage Log',
];
?>
<div class="container">
    <div class="topbar">
        <span class="topbar-greeting">Halo, <?= htmlspecialchars($user['name']) ?></span>
        <a href="index.php?page=logout" class="topbar-logout">Logout</a>
    </div>

    <h2>Dashboard</h2>

    <div class="stat-card-row">
        <div class="stat-card">
            <span class="stat-number"><?= $totalMaterialTypes ?></span>
            <span class="stat-label">Jenis Material</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $openWoCount ?></span>
            <span class="stat-label">WO Open</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= count($lowStockMaterials) ?></span>
            <span class="stat-label">Stok Menipis</span>
        </div>
    </div>

    <h3>Stok per Kategori</h3>
    <?php if (empty($stockByCategory)): ?>
        <p class="placeholder-note">Belum ada material tercatat.</p>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($stockByCategory as $cat): ?>
                <div class="material-card">
                    <div class="material-card-top">
                        <span class="mono-code"><?= htmlspecialchars($cat['category_name']) ?></span>
                        <span class="badge badge-serial"><?= (int) $cat['material_count'] ?> jenis</span>
                    </div>
                    <div class="material-stock">
                        <strong><?= rtrim(rtrim(number_format((float) $cat['total_stock'], 2, '.', ''), '0'), '.') ?></strong>
                        <span class="stock-unit"><?= htmlspecialchars($cat['units']) ?></span>
                        <span class="stock-label">total stok</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h3>Stok Menipis</h3>
    <?php if (empty($lowStockMaterials)): ?>
        <p class="placeholder-note">Tidak ada material dengan stok menipis saat ini.</p>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($lowStockMaterials as $m): ?>
                <div class="material-card">
                    <div class="material-card-top">
                        <span class="mono-code"><?= htmlspecialchars($m['item_code']) ?></span>
                        <span class="badge badge-lowstock">Stok Rendah</span>
                    </div>
                    <div class="material-desc"><?= htmlspecialchars($m['description']) ?></div>
                    <div class="material-meta"><?= htmlspecialchars($m['category_name']) ?></div>
                    <div class="material-stock">
                        <strong><?= rtrim(rtrim(number_format((float) $m['stock_qty'], 2, '.', ''), '0'), '.') ?></strong>
                        <span class="stock-unit"><?= htmlspecialchars($m['unit']) ?></span>
                        <span class="stock-label">sisa (ambang: <?= rtrim(rtrim(number_format((float) $m['low_stock_threshold'], 2, '.', ''), '0'), '.') ?>)</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h3>Aktivitas Terbaru</h3>
        <a href="index.php?page=audit-log" class="btn-small">Lihat Audit Log</a>
    </div>
    <?php if (empty($recentActivity)): ?>
        <p class="placeholder-note">Belum ada aktivitas tercatat sejak Phase 9 diaktifkan.</p>
    <?php else: ?>
        <div class="serial-list">
            <?php foreach ($recentActivity as $entry): ?>
                <?php
                    $actionClass = [
                        'create' => 'badge-serial',
                        'update' => 'badge-quantity',
                        'delete' => 'badge-used',
                    ][$entry['action']] ?? 'badge-serial';
                    $tableLabel = $tableLabels[$entry['table_name']] ?? htmlspecialchars($entry['table_name']);
                    $actionLabel = $actionLabels[$entry['action']] ?? htmlspecialchars($entry['action']);
                ?>
                <div class="log-row">
                    <div class="log-row-info">
                        <div>
                            <span class="badge <?= $actionClass ?>"><?= $actionLabel ?></span>
                            <?= $tableLabel ?> #<?= (int) $entry['record_id'] ?>
                        </div>
                        <div class="material-meta">
                            <?= htmlspecialchars($entry['user_name']) ?> ·
                            <?= htmlspecialchars(substr($entry['created_at'], 0, 16)) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
