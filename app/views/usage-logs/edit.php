<?php
/**
 * usage-logs/edit.php
 * Phase 7 -- shared edit form for a single usage_logs row. Used by both
 * admin (linked from admin/workorders/detail.php) and teknisi (linked
 * from teknisi/log-usage.php's mini log list); the role-aware navbar
 * and $backUrl below are the only things that differ per role.
 *
 * Expects: $log (UsageLog::find() row), $selectableSerials (serial-
 * tracked only), $flash, $user, $returnTo
 */
if ($returnTo === 'workorder-detail') {
    $backUrl = 'index.php?page=workorder-detail&id=' . $log['wo_id'];
} elseif ($returnTo === 'history') {
    $backUrl = 'index.php?page=history';
} elseif ($returnTo === 'logs') {
    $backUrl = 'index.php?page=logs';
} else {
    $backUrl = 'index.php?page=log-usage';
}
?>
<div class="container">
    <div class="topbar">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="topbar-back">&larr; Kembali</a>
    </div>

    <h2>Edit Log Usage</h2>

    <?php if ($flash && !empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $err): ?>
            <div class="alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="wo-info-card">
        <div class="wo-info-row">
            <span class="wo-info-label">Work Order</span>
            <span class="wo-info-value"><?= htmlspecialchars($log['wo_no']) ?></span>
        </div>
        <div class="wo-info-row">
            <span class="wo-info-label">Material</span>
            <span class="wo-info-value"><?= htmlspecialchars($log['material_description']) ?></span>
        </div>
        <div class="wo-info-row">
            <span class="wo-info-label">Dicatat</span>
            <span class="wo-info-value"><?= htmlspecialchars($log['created_at']) ?></span>
        </div>
    </div>

    <form method="POST" action="index.php?page=usage-log-update" id="usage-log-edit-form">
        <input type="hidden" name="id" value="<?= $log['id'] ?>">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">

        <?php if ($log['tracking_type'] === 'quantity'): ?>
            <label for="qty_used">Jumlah Digunakan (<?= htmlspecialchars($log['unit']) ?>)</label>
            <input type="number" step="0.01" min="0.01" id="qty_used" name="qty_used"
                   value="<?= htmlspecialchars(rtrim(rtrim(number_format((float) $log['qty_used'], 2, '.', ''), '0'), '.')) ?>"
                   required>
            <p class="stock-hint">Stok akan disesuaikan otomatis berdasarkan selisih jumlah lama dan baru.</p>
        <?php else: ?>
            <label for="serial_id">Serial Number</label>
            <select id="serial_id" name="serial_id" required>
                <?php foreach ($selectableSerials as $sn): ?>
                    <option value="<?= $sn['id'] ?>" <?= $sn['serial_number'] === $log['serial_number'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sn['serial_number']) ?><?= $sn['serial_number'] === $log['serial_number'] ? ' (saat ini)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="stock-hint">Pilih SN lain jika SN yang dicatat sebelumnya salah.</p>
        <?php endif; ?>

        <button type="submit" class="btn-primary">Simpan Perubahan</button>
    </form>

    <form method="POST" action="index.php?page=usage-log-delete" class="usage-log-delete-form"
          onsubmit="return confirm('Yakin ingin menghapus log ini? Stok akan dikembalikan.');">
        <input type="hidden" name="id" value="<?= $log['id'] ?>">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
        <button type="submit" class="btn-small btn-danger-small">Hapus Log</button>
    </form>
</div>