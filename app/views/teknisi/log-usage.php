<?php
/**
 * teknisi/log-usage.php
 * Expects: $assignedWOs, $categories, $materials (each with
 * available_serials), $flash, $user
 *
 * Phase "multi-log UX": material picking switched from a single radio
 * (one material per submit) to checkboxes with an inline qty/SN input
 * expanding directly under each selected card, so a technician can log
 * everything used on a WO -- ONT, cable, etc -- in one submit, and the
 * input field they need is always right where they clicked, not in a
 * separate shared block at the bottom of a long scrollable list.
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

            <label>Material yang digunakan</label>
            <p class="placeholder-note">Pilih semua material yang dipakai untuk WO ini, isi jumlah/SN masing-masing, lalu catat sekaligus.</p>
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
                        $isSerial = $m['tracking_type'] === 'serial';
                        $isOutOfStock = $isSerial ? empty($m['available_serials']) : (float) $m['stock_qty'] <= 0;
                    ?>
                    <div class="material-picker-item" data-category="<?= $m['category_id'] ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
                        <label class="material-card material-picker-card<?= $isOutOfStock ? ' material-card-disabled' : '' ?>">
                            <input type="checkbox" name="material_ids[]" value="<?= $m['id'] ?>"
                                   class="material-picker-checkbox"
                                   data-tracking="<?= $m['tracking_type'] ?>"
                                   data-stock="<?= $m['stock_qty'] ?>"
                                   data-unit="<?= htmlspecialchars($m['unit']) ?>"
                                   data-description="<?= htmlspecialchars($m['description']) ?>"
                                   data-material-id="<?= $m['id'] ?>"
                                   <?= $isOutOfStock ? 'disabled' : '' ?>>
                            <div class="material-card-top">
                                <span class="mono-code"><?= htmlspecialchars($m['item_code']) ?></span>
                                <span class="badge badge-<?= $m['tracking_type'] ?>">
                                    <?= $isSerial ? 'Serial' : 'Quantity' ?>
                                </span>
                            </div>
                            <div class="material-desc"><?= htmlspecialchars($m['description']) ?></div>
                            <div class="material-meta">
                                <?= htmlspecialchars($m['category_name']) ?><?= $m['merk'] ? ' · ' . htmlspecialchars($m['merk']) : '' ?>
                            </div>
                            <div class="material-stock">
                                <strong><?= $stockDisplay ?></strong>
                                <span class="stock-unit"><?= htmlspecialchars($m['unit']) ?></span>
                                <span class="stock-label"><?= $isSerial ? 'tersedia' : 'stok' ?></span>
                            </div>
                            <?php if ($isOutOfStock): ?>
                                <span class="stock-hint stock-hint-warning">
                                    <?= $isSerial ? 'Tidak ada SN tersedia' : 'Stok habis' ?>
                                </span>
                            <?php endif; ?>
                        </label>

                        <?php if (!$isOutOfStock): ?>
                            <div class="inline-usage-input" id="usage-input-<?= $m['id'] ?>" style="display:none;">
                                <?php if ($isSerial): ?>
                                    <label for="serial-select-<?= $m['id'] ?>">Pilih SN — <?= htmlspecialchars($m['description']) ?></label>
                                    <select id="serial-select-<?= $m['id'] ?>" name="serial_id[<?= $m['id'] ?>]"
                                            class="sn-select-inline" data-material-id="<?= $m['id'] ?>" disabled>
                                        <option value="">-- pilih SN --</option>
                                        <?php foreach ($m['available_serials'] as $sn): ?>
                                            <option value="<?= $sn['id'] ?>"><?= htmlspecialchars($sn['serial_number']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <label for="qty-input-<?= $m['id'] ?>">Jumlah Digunakan — <?= htmlspecialchars($m['description']) ?></label>
                                    <input type="number" step="0.01" min="0.01" max="<?= $m['stock_qty'] ?>"
                                           id="qty-input-<?= $m['id'] ?>" name="qty_used[<?= $m['id'] ?>]"
                                           class="qty-input-inline" data-material-id="<?= $m['id'] ?>" disabled>
                                    <span class="stock-hint">Sisa stok: <?= $stockDisplay ?> <?= htmlspecialchars($m['unit']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="log-usage-summary" id="log-usage-summary">
                <span id="log-usage-summary-text">Belum ada material dipilih</span>
                <button type="submit" class="btn-primary" id="log-usage-submit" disabled>Catat Penggunaan</button>
            </div>
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
