<?php
/**
 * teknisi/history.php
 * Phase 8. Expects: $logs, $workOrders, $categories, $showDeleted,
 * $flash, $user, and the raw filter values in $_GET (wo, category, q,
 * date_from, date_to, show_deleted).
 */
$currentWo = $_GET['wo'] ?? '';
$currentCategory = $_GET['category'] ?? '';
$currentSearch = htmlspecialchars($_GET['q'] ?? '');
$currentDateFrom = htmlspecialchars($_GET['date_from'] ?? '');
$currentDateTo = htmlspecialchars($_GET['date_to'] ?? '');
?>
<div class="container">
    <div class="topbar">
        <span class="topbar-greeting">Halo, <?= htmlspecialchars($user['name']) ?></span>
        <a href="index.php?page=logout" class="topbar-logout">Logout</a>
    </div>

    <div class="section-header">
        <h2>History</h2>
    </div>

    <?php if ($flash && !empty($flash['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($flash['success']) ?></div>
    <?php endif; ?>
    <?php if ($flash && !empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $err): ?>
            <div class="alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="GET" action="index.php" class="filter-panel">
        <input type="hidden" name="page" value="history">

        <div class="filter-row">
            <input type="text" name="q" value="<?= $currentSearch ?>" placeholder="Cari kode/deskripsi material...">
        </div>

        <div class="chip-row">
            <a href="index.php?page=history<?= $currentWo !== '' ? '&wo=' . urlencode($currentWo) : '' ?><?= $currentSearch !== '' ? '&q=' . urlencode($currentSearch) : '' ?><?= $currentDateFrom !== '' ? '&date_from=' . urlencode($currentDateFrom) : '' ?><?= $currentDateTo !== '' ? '&date_to=' . urlencode($currentDateTo) : '' ?><?= $showDeleted ? '&show_deleted=1' : '' ?>"
               class="chip<?= $currentCategory === '' ? ' active' : '' ?>">Semua</a>
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?page=history&category=<?= $cat['id'] ?><?= $currentWo !== '' ? '&wo=' . urlencode($currentWo) : '' ?><?= $currentSearch !== '' ? '&q=' . urlencode($currentSearch) : '' ?><?= $currentDateFrom !== '' ? '&date_from=' . urlencode($currentDateFrom) : '' ?><?= $currentDateTo !== '' ? '&date_to=' . urlencode($currentDateTo) : '' ?><?= $showDeleted ? '&show_deleted=1' : '' ?>"
                   class="chip<?= (string) $currentCategory === (string) $cat['id'] ? ' active' : '' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($currentCategory !== ''): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($currentCategory) ?>">
        <?php endif; ?>

        <div class="filter-row">
            <label>Work Order
                <select name="wo">
                    <option value="">Semua WO</option>
                    <?php foreach ($workOrders as $wo): ?>
                        <option value="<?= $wo['id'] ?>" <?= (string) $currentWo === (string) $wo['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wo['wo_no']) ?> (<?= $wo['status'] === 'open' ? 'Open' : 'Completed' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Dari
                <input type="date" name="date_from" value="<?= $currentDateFrom ?>">
            </label>
            <label>Sampai
                <input type="date" name="date_to" value="<?= $currentDateTo ?>">
            </label>
        </div>

        <div class="filter-row">
            <label class="filter-checkbox">
                <input type="checkbox" name="show_deleted" value="1" <?= $showDeleted ? 'checked' : '' ?>>
                Tampilkan yang dihapus
            </label>
            <button type="submit" class="btn-search">Terapkan Filter</button>
        </div>
    </form>

    <?php if (empty($logs)): ?>
        <p class="placeholder-note">Tidak ada log yang cocok.</p>
    <?php else: ?>
        <div class="serial-list">
            <?php foreach ($logs as $log): ?>
                <?php
                    $isDeleted = (int) $log['is_deleted'] === 1;
                    $canManage = !$isDeleted && $log['wo_status'] === 'open';
                    $qtyDisplay = $log['serial_number']
                        ? $log['serial_number']
                        : rtrim(rtrim(number_format((float) $log['qty_used'], 2, '.', ''), '0'), '.') . ' ' . $log['unit'];
                ?>
                <div class="serial-row log-row<?= $isDeleted ? ' log-row-deleted' : '' ?>">
                    <div class="log-row-info">
                        <div><?= htmlspecialchars($log['material_description']) ?></div>
                        <div class="material-meta">
                            <?= htmlspecialchars($log['wo_no']) ?> ·
                            <?= htmlspecialchars($log['category_name']) ?> ·
                            <?= htmlspecialchars(substr($log['created_at'], 0, 16)) ?>
                        </div>
                    </div>
                    <div class="log-row-actions">
                        <span class="mono-code"><?= htmlspecialchars($qtyDisplay) ?></span>
                        <?php if ($isDeleted): ?>
                            <span class="badge badge-deleted">Dihapus</span>
                        <?php elseif ($canManage): ?>
                            <a href="index.php?page=usage-log-edit&id=<?= $log['id'] ?>&return_to=history" class="btn-small">Edit</a>
                            <form method="POST" action="index.php?page=usage-log-delete" style="display:inline;"
                                  onsubmit="return confirm('Yakin ingin menghapus log ini? Stok akan dikembalikan.');">
                                <input type="hidden" name="id" value="<?= $log['id'] ?>">
                                <input type="hidden" name="return_to" value="history">
                                <button type="submit" class="btn-small btn-danger-small">Hapus</button>
                            </form>
                        <?php else: ?>
                            <span class="badge badge-quantity">Selesai</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
