<?php
/**
 * admin/materials/form.php
 * Expects: $material (array|null), $categories, $flash, $user
 */
$old = $flash['old'] ?? [];
$val = function (string $key, $default = '') use ($old, $material) {
    if (isset($old[$key])) {
        return $old[$key];
    }
    if ($material !== null && array_key_exists($key, $material)) {
        return $material[$key];
    }
    return $default;
};

$isEdit = $material !== null;
$selectedCategory = (string) $val('category_id', $material['category_id'] ?? '');
$selectedTracking = $val('tracking_type', $material['tracking_type'] ?? '');
?>
<div class="container">
    <div class="topbar">
        <a href="index.php?page=materials" class="topbar-back">&larr; Materials</a>
    </div>

    <h2><?= $isEdit ? 'Edit Material' : 'Tambah Material' ?></h2>

    <?php if ($flash && !empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $err): ?>
            <div class="alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($isEdit): ?>
        <div class="form-static-row">
            <span class="form-static-label">Kode Item</span>
            <span class="mono-code"><?= htmlspecialchars($material['item_code']) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=material-save" id="material-form">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $material['id'] ?>">
        <?php endif; ?>

        <label for="category_id">Kategori</label>
        <select id="category_id" name="category_id" required>
            <option value="">-- pilih kategori --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $selectedCategory === (string) $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?> (<?= htmlspecialchars($cat['code_prefix']) ?>)
                </option>
            <?php endforeach; ?>
            <option value="__new__" <?= $selectedCategory === '__new__' ? 'selected' : '' ?>>+ Kategori baru...</option>
        </select>

        <div id="new-category-fields" class="inline-subfields" style="display:none;">
            <label for="new_category_name">Nama Kategori Baru</label>
            <input type="text" id="new_category_name" name="new_category_name"
                   value="<?= htmlspecialchars($val('new_category_name', '')) ?>" placeholder="mis. Splitter">

            <label for="new_category_prefix">Prefix Kode</label>
            <input type="text" id="new_category_prefix" name="new_category_prefix"
                   value="<?= htmlspecialchars($val('new_category_prefix', '')) ?>"
                   placeholder="mis. ASPL" maxlength="10" style="text-transform:uppercase;">
        </div>

        <label for="description">Deskripsi</label>
        <input type="text" id="description" name="description"
               value="<?= htmlspecialchars($val('description')) ?>"
               placeholder="mis. ZTE-ONT ZXHN-F672Y (DUAL BAND)" required>

        <label for="merk">Merk</label>
        <input type="text" id="merk" name="merk" value="<?= htmlspecialchars($val('merk')) ?>" placeholder="mis. ZTE">

        <?php if ($isEdit): ?>
            <label>Tipe Tracking</label>
            <div class="form-static-value">
                <span class="badge badge-<?= $material['tracking_type'] ?>">
                    <?= $material['tracking_type'] === 'serial' ? 'Serial' : 'Quantity' ?>
                </span>
                <span class="form-static-hint">tidak bisa diubah setelah dibuat</span>
            </div>
        <?php else: ?>
            <label>Tipe Tracking</label>
            <div class="radio-row">
                <label class="radio-option">
                    <input type="radio" name="tracking_type" value="serial"
                           <?= $selectedTracking === 'serial' ? 'checked' : '' ?>> Serial (per unit, punya SN)
                </label>
                <label class="radio-option">
                    <input type="radio" name="tracking_type" value="quantity"
                           <?= $selectedTracking === 'quantity' ? 'checked' : '' ?>> Quantity (per satuan, mis. meter)
                </label>
            </div>
        <?php endif; ?>

        <label for="unit">Unit</label>
        <input type="text" id="unit" name="unit" value="<?= htmlspecialchars($val('unit')) ?>" placeholder="pcs / meter" required>

        <?php if ($isEdit && $material['tracking_type'] === 'quantity'): ?>
            <label for="stock_qty">Stok Saat Ini</label>
            <input type="number" step="0.01" min="0" id="stock_qty" name="stock_qty"
                   value="<?= htmlspecialchars($val('stock_qty')) ?>">
        <?php elseif ($isEdit): ?>
            <label>Stok Saat Ini</label>
            <div class="form-static-value">
                <?= rtrim(rtrim(number_format((float) $material['stock_qty'], 2, '.', ''), '0'), '.') ?> <?= htmlspecialchars($material['unit']) ?>
                <span class="form-static-hint">dikelola lewat daftar SN</span>
            </div>
        <?php else: ?>
            <div id="initial-stock-field" style="display:none;">
                <label for="stock_qty">Stok Awal</label>
                <input type="number" step="0.01" min="0" id="stock_qty" name="stock_qty" value="<?= htmlspecialchars($val('stock_qty', '0')) ?>">
            </div>
            <p class="form-hint" id="serial-stock-hint" style="display:none;">
                Material serial dimulai dengan stok 0 — tambahkan SN lewat halaman "Lihat SN" setelah disimpan.
            </p>
        <?php endif; ?>

        <label for="low_stock_threshold">Ambang Stok Rendah <span class="form-static-hint">(opsional)</span></label>
        <input type="number" step="0.01" min="0" id="low_stock_threshold" name="low_stock_threshold"
               value="<?= htmlspecialchars($val('low_stock_threshold', $material['low_stock_threshold'] ?? '')) ?>"
               placeholder="mis. 5 -- kosongkan jika tidak perlu peringatan">

        <button type="submit" class="btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Tambah Material' ?></button>
    </form>
</div>

<script src="<?= asset('assets/js/materials.js') ?>"></script>
