<?php
/**
 * admin/workorders/detail.php
 * Expects: $workOrder, $loggedMaterials, $flash, $user
 */
?>
<div class="container">
    <div class="topbar">
        <a href="index.php?page=workorders" class="topbar-back">&larr; Work Orders</a>
    </div>

    <div class="section-header">
        <h2><?= htmlspecialchars($workOrder['wo_no']) ?></h2>
        <span class="badge badge-<?= $workOrder['status'] === 'open' ? 'serial' : 'quantity' ?>">
            <?= $workOrder['status'] === 'open' ? 'Open' : 'Completed' ?>
        </span>
    </div>

    <?php if ($flash && !empty($flash['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($flash['success']) ?></div>
    <?php endif; ?>

    <div class="wo-info-card">
        <div class="wo-info-row">
            <span class="wo-info-label">Pelanggan</span>
            <span class="wo-info-value"><?= htmlspecialchars($workOrder['customer_name']) ?></span>
        </div>
        <?php if ($workOrder['customer_address']): ?>
            <div class="wo-info-row">
                <span class="wo-info-label">Alamat</span>
                <span class="wo-info-value"><?= htmlspecialchars($workOrder['customer_address']) ?></span>
            </div>
        <?php endif; ?>
        <div class="wo-info-row">
            <span class="wo-info-label">Teknisi</span>
            <span class="wo-info-value"><?= htmlspecialchars($workOrder['technician_name']) ?></span>
        </div>
        <div class="wo-info-row">
            <span class="wo-info-label">Tanggal</span>
            <span class="wo-info-value"><?= htmlspecialchars($workOrder['wo_date']) ?></span>
        </div>
        <?php if ($workOrder['port_fat']): ?>
            <div class="wo-info-row">
                <span class="wo-info-label">Port FAT</span>
                <span class="wo-info-value"><?= htmlspecialchars($workOrder['port_fat']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($workOrder['notes']): ?>
            <div class="wo-info-row">
                <span class="wo-info-label">Catatan</span>
                <span class="wo-info-value"><?= htmlspecialchars($workOrder['notes']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="material-actions" style="margin-bottom: 20px;">
        <a href="index.php?page=workorder-form&id=<?= $workOrder['id'] ?>" class="btn-small">Edit</a>
        <form method="POST" action="index.php?page=workorder-toggle-status" style="display:inline;">
            <input type="hidden" name="id" value="<?= $workOrder['id'] ?>">
            <input type="hidden" name="return_to" value="detail">
            <button type="submit" class="btn-small">
                <?= $workOrder['status'] === 'open' ? 'Tandai Selesai' : 'Buka Kembali' ?>
            </button>
        </form>
    </div>

    <h3>Material Terpakai</h3>
    <?php if (empty($loggedMaterials)): ?>
        <p class="placeholder-note">Belum ada material yang dilog untuk WO ini.</p>
    <?php else: ?>
        <div class="serial-list">
            <?php foreach ($loggedMaterials as $log): ?>
                <div class="serial-row log-row">
                    <span><?= htmlspecialchars($log['material_description']) ?></span>
                    <div class="log-row-actions">
                        <span class="mono-code">
                            <?= $log['serial_number']
                                ? htmlspecialchars($log['serial_number'])
                                : rtrim(rtrim(number_format((float) $log['qty_used'], 2, '.', ''), '0'), '.') . ' ' . htmlspecialchars($log['unit']) ?>
                        </span>
                        <a href="index.php?page=usage-log-edit&id=<?= $log['id'] ?>&return_to=workorder-detail" class="btn-small">Edit</a>
                        <form method="POST" action="index.php?page=usage-log-delete" style="display:inline;"
                              onsubmit="return confirm('Yakin ingin menghapus log ini? Stok akan dikembalikan.');">
                            <input type="hidden" name="id" value="<?= $log['id'] ?>">
                            <input type="hidden" name="return_to" value="workorder-detail">
                            <button type="submit" class="btn-small btn-danger-small">Hapus</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>