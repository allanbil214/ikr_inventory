<div class="container">
    <div class="topbar">
        <span class="topbar-greeting">Halo, <?= htmlspecialchars($user['name']) ?></span>
        <a href="index.php?page=logout" class="topbar-logout">Logout</a>
    </div>

    <h2>Home</h2>
    <p class="placeholder-note">
        Live stock snapshot cards and the "Log Usage" CTA land here in
        Phase 6. The list below is a Phase 5 preview so assigned WOs
        can be verified end-to-end.
    </p>

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

    <h3>WO Ditugaskan (Open)</h3>
    <?php if (empty($assignedWOs)): ?>
        <p class="placeholder-note">Belum ada WO open yang ditugaskan ke kamu.</p>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($assignedWOs as $wo): ?>
                <div class="material-card">
                    <div class="material-card-top">
                        <span class="mono-code"><?= htmlspecialchars($wo['wo_no']) ?></span>
                        <span class="badge badge-serial">Open</span>
                    </div>
                    <div class="material-desc"><?= htmlspecialchars($wo['customer_name']) ?></div>
                    <div class="material-meta"><?= htmlspecialchars($wo['wo_date']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>