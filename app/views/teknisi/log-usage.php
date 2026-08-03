<?php
/**
 * teknisi/log-usage.php
 * Expects: $assignedWOs, $categories, $materials (each with
 * available_serials), $flash, $user
 */
?>
<div class="container">
    <div class="topbar">
        <span class="topbar-greeting">Halo, <?= htmlspecialchars($user['name']) ?></span>
        <a href="index.php?page=logout" class="topbar-logout">Logout</a>
    </div>

    <h2>Log Usage</h2>

    <?php if ($flash && !empty($flash['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($flash['success']) ?></div>
    <?php endif; ?>
    <?php if ($flash && !empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $err): ?>
            <div class="alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($assignedWOs)): ?>
        <p class="placeholder-note">Kamu tidak punya WO open yang ditugaskan saat ini, jadi belum ada yang bisa dilog.</p>
    <?php else: ?>
        <form method="POST" action="index.php?page=usage-log-save" id="material-form">
            <label for="wo_id">Work Order</label>
            <select id="wo_id" name="wo_id" required>
                <option value="">-- pilih WO --</option>
                <?php foreach ($assignedWOs as $wo): ?>
                    <option value="<?= $wo['id'] ?>">
                        <?= htmlspecialchars($wo['wo_no']) ?> — <?= htmlspecialchars($wo['customer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Material</label>
            <input type="text" id="material-search" placeholder="Cari kode, deskripsi, merk..." autocomplete="off">

            <div class="chip-row" id="material-category-chips">
                <span class="chip active" data-category="">Semua</span>
                <?php foreach ($categories as $cat): ?>
                    <span class="chip" data-category="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></span>
                <?php endforeach; ?>
            </div>

            <?php if (empty($materials)): ?>
                <p class="placeholder-note">Belum ada material yang bisa dilog.</p>
            <?php endif; ?>

            <div class="card-list" id="material-picker-list">
                <?php foreach ($materials as $m): ?>
                    <?php
                        $stockDisplay = rtrim(rtrim(number_format((float) $m['stock_qty'], 2, '.', ''), '0'), '.');
                        $searchBlob = strtolower($m['item_code'] . ' ' . $m['description'] . ' ' . ($m['merk'] ?? ''));
                    ?>
                    <label class="material-card material-picker-card"
                           data-category="<?= $m['category_id'] ?>"
                           data-search="<?= htmlspecialchars($searchBlob) ?>">
                        <input type="radio" name="material_id" value="<?= $m['id'] ?>"
                               class="material-picker-radio"
                               data-tracking="<?= $m['tracking_type'] ?>"
                               data-stock="<?= $m['stock_qty'] ?>"
                               data-unit="<?= htmlspecialchars($m['unit']) ?>"
                               data-description="<?= htmlspecialchars($m['description']) ?>">
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
                            <strong><?= $stockDisplay ?></strong>
                            <span class="stock-unit"><?= htmlspecialchars($m['unit']) ?></span>
                            <span class="stock-label"><?= $m['tracking_type'] === 'serial' ? 'tersedia' : 'stok' ?></span>
                        </div>
                    </label>

                    <?php if ($m['tracking_type'] === 'serial'): ?>
                        <select name="serial_id" class="sn-select" data-material-id="<?= $m['id'] ?>" disabled style="display:none;">
                            <option value="">-- pilih SN --</option>
                            <?php foreach ($m['available_serials'] as $sn): ?>
                                <option value="<?= $sn['id'] ?>"><?= htmlspecialchars($sn['serial_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="usage-input-section" id="usage-input-section" style="display:none;">
                <label id="usage-input-label" for="qty_used">Jumlah Digunakan</label>
                <div id="qty-input-group" style="display:none;">
                    <input type="number" step="0.01" min="0.01" id="qty_used" name="qty_used" disabled>
                    <span class="stock-hint" id="qty-stock-hint"></span>
                </div>
                <p class="stock-hint" id="sn-empty-hint" style="display:none;">
                    Tidak ada SN tersedia untuk material ini.
                </p>
            </div>

            <button type="submit" class="btn-primary" id="log-usage-submit" disabled>Catat Penggunaan</button>
        </form>

        <div id="wo-logs-panels">
            <?php foreach ($assignedWOs as $wo): ?>
                <div class="wo-logs-panel" data-wo-id="<?= $wo['id'] ?>" style="display:none;">
                    <h3>Log Terbaru — <?= htmlspecialchars($wo['wo_no']) ?></h3>
                    <?php if (empty($wo['logs'])): ?>
                        <p class="placeholder-note">Belum ada material yang dilog untuk WO ini.</p>
                    <?php else: ?>
                        <div class="serial-list">
                            <?php foreach ($wo['logs'] as $log): ?>
                                <div class="serial-row log-row">
                                    <span><?= htmlspecialchars($log['material_description']) ?></span>
                                    <div class="log-row-actions">
                                        <span class="mono-code">
                                            <?= $log['serial_number']
                                                ? htmlspecialchars($log['serial_number'])
                                                : rtrim(rtrim(number_format((float) $log['qty_used'], 2, '.', ''), '0'), '.') . ' ' . htmlspecialchars($log['unit']) ?>
                                        </span>
                                        <a href="index.php?page=usage-log-edit&id=<?= $log['id'] ?>&return_to=log-usage" class="btn-small">Edit</a>
                                        <form method="POST" action="index.php?page=usage-log-delete" style="display:inline;"
                                              onsubmit="return confirm('Yakin ingin menghapus log ini? Stok akan dikembalikan.');">
                                            <input type="hidden" name="id" value="<?= $log['id'] ?>">
                                            <input type="hidden" name="return_to" value="log-usage">
                                            <button type="submit" class="btn-small btn-danger-small">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="<?= asset('assets/js/log-usage.js') ?>"></script>
