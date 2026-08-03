<?php
/**
 * admin/workorders/form.php
 * Expects: $workOrder (array|null), $teknisiList, $flash, $user
 */
$old = $flash['old'] ?? [];
$val = function (string $key, $default = '') use ($old, $workOrder) {
    if (isset($old[$key])) {
        return $old[$key];
    }
    if ($workOrder !== null && array_key_exists($key, $workOrder)) {
        return $workOrder[$key];
    }
    return $default;
};

$isEdit = $workOrder !== null;
$selectedTechnician = (string) $val('technician_id', $workOrder['technician_id'] ?? '');
?>
<div class="container">
    <div class="topbar">
        <a href="index.php?page=workorders" class="topbar-back">&larr; Work Orders</a>
    </div>

    <h2><?= $isEdit ? 'Edit Work Order' : 'Tambah Work Order' ?></h2>

    <?php if ($flash && !empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $err): ?>
            <div class="alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="POST" action="index.php?page=workorder-save" id="material-form">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $workOrder['id'] ?>">
        <?php endif; ?>

        <label for="wo_no">Nomor WO</label>
        <input type="text" id="wo_no" name="wo_no" value="<?= htmlspecialchars($val('wo_no')) ?>"
               placeholder="mis. WO-28072026-3419501" required>

        <label for="wo_date">Tanggal WO</label>
        <input type="date" id="wo_date" name="wo_date" value="<?= htmlspecialchars($val('wo_date')) ?>" required>

        <label for="technician_id">Teknisi</label>
        <select id="technician_id" name="technician_id" required>
            <option value="">-- pilih teknisi --</option>
            <?php foreach ($teknisiList as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $selectedTechnician === (string) $t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="customer_name">Nama Pelanggan</label>
        <input type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($val('customer_name')) ?>" required>

        <label for="customer_address">Alamat Pelanggan</label>
        <input type="text" id="customer_address" name="customer_address" value="<?= htmlspecialchars($val('customer_address')) ?>">

        <label for="port_fat">Port FAT</label>
        <input type="text" id="port_fat" name="port_fat" value="<?= htmlspecialchars($val('port_fat')) ?>" placeholder="mis. JKT-MKS-D11-S02-H01-A12/5">

        <label for="notes">Catatan</label>
        <input type="text" id="notes" name="notes" value="<?= htmlspecialchars($val('notes')) ?>">

        <?php if ($isEdit): ?>
            <label>Status</label>
            <div class="form-static-value">
                <span class="badge badge-<?= $workOrder['status'] === 'open' ? 'serial' : 'quantity' ?>">
                    <?= $workOrder['status'] === 'open' ? 'Open' : 'Completed' ?>
                </span>
                <span class="form-static-hint">ubah lewat tombol "Tandai Selesai" / "Buka Kembali" di daftar atau detail WO</span>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Tambah Work Order' ?></button>
    </form>
</div>